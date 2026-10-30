<?php

declare(strict_types=1);

namespace App\Service;

/** Agent CLI volontaire. Il ne surveille ni la navigation ni l’usage des autres applications. */
final class VoluntaryAgent
{
    public function __construct(
        private ReplicaProtocol $protocol,
        private ?\Closure $openSource = null,
    ) {
    }

    public function synchronize(string $configFile): array
    {
        $config = $this->configuration($configFile);
        $this->permitted($config);
        $directory = $config['directory'];
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new \RuntimeException('Stockage de copie inaccessible.');
        }
        $lock = fopen($directory . '/agent.lock', 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new \RuntimeException('Une synchronisation est déjà en cours.');
        }
        $temporary = tempnam($directory, 'incoming-');
        $usageFile = $directory . '/usage-' . gmdate('Y-m') . '.json';
        $usage = is_file($usageFile)
            ? json_decode(
                file_get_contents($usageFile),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )
            : ['bytes' => 0];
        $received = 0;
        $started = microtime(true);
        try {
            $url = $config['url'];
            if (
                parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_USER)
                || parse_url($url, PHP_URL_PASS)
            ) {
                throw new \RuntimeException('Une URL HTTPS sans identifiants est requise.');
            }
            if ($usage['bytes'] >= $config['monthlyBytes']) {
                throw new \RuntimeException('Plafond mensuel déjà atteint.');
            }
            $ssl = ['verify_peer' => true, 'verify_peer_name' => true];
            if (isset($config['caFile'])) {
                $ssl['cafile'] = $config['caFile'];
            }
            $stream = $this->openSource
                ? ($this->openSource)($url)
                : @fopen(
                    $url,
                    'rb',
                    false,
                    stream_context_create([
                        'http' => [
                            'timeout' => 10,
                            'follow_location' => 0,
                            'header' => "Accept: application/json\r\n",
                        ],
                        'ssl' => $ssl,
                    ]),
                );
            if (!$stream) {
                throw new \RuntimeException('Relais indisponible ; la dernière copie saine est conservée.');
            }
            $output = fopen($temporary, 'wb');
            try {
                while (!feof($stream)) {
                    // Relire la configuration et le fichier STOP rend la révocation effective pendant le transfert.
                    $current = $this->configuration($configFile);
                    $this->permitted($current);
                    if (is_file($directory . '/STOP')) {
                        throw new \RuntimeException('Relais arrêté à la demande de son propriétaire.');
                    }
                    $budget = min(
                        4096,
                        $current['maxCopyBytes'] - $received,
                        $current['monthlyBytes'] - $usage['bytes'],
                    );
                    if ($budget < 1) {
                        throw new \RuntimeException('Plafond de volume atteint avant une nouvelle lecture.');
                    }
                    $chunk = fread($stream, $budget);
                    if ($chunk === false) {
                        throw new \RuntimeException('Lecture interrompue.');
                    }
                    $received += strlen($chunk);
                    $usage['bytes'] += strlen($chunk);
                    if (
                        $received > $current['maxCopyBytes']
                        || $usage['bytes'] > $current['monthlyBytes']
                    ) {
                        throw new \RuntimeException('Plafond de volume atteint.');
                    }
                    fwrite($output, $chunk);
                    $limit = min(
                        $current['maxBytesPerSecond'],
                        (int) floor(
                            ($current['authorizedUplinkBytesPerSecond'] *
                                $current['activePercent']) /
                                100,
                        ),
                    );
                    if ($limit < 1) {
                        throw new \RuntimeException('Aucune bande passante autorisée.');
                    }
                    $target = $started + $received / $limit;
                    while (microtime(true) < $target) {
                        if (
                            is_file($directory . '/STOP')
                            || !$this->configuration($configFile)['enabled']
                        ) {
                            throw new \RuntimeException('Relais arrêté pendant la limitation de débit.');
                        }
                        usleep(
                            (int) min(
                                100000,
                                max(1, ($target - microtime(true)) * 1000000),
                            ),
                        );
                    }
                }
                /** @var array<string, mixed> $metadata Les wrappers comme php://temp n’exposent pas tous timed_out. */
                $metadata = stream_get_meta_data($stream);
                if ($metadata['timed_out'] ?? false) {
                    throw new \RuntimeException('Délai de réception dépassé.');
                }
            } finally {
                fclose($stream);
                fclose($output);
            }
            $envelope = json_decode(
                file_get_contents($temporary),
                true,
                128,
                JSON_THROW_ON_ERROR,
            );
            $destination = $directory . '/replica.json';
            $previous = null;
            $recoveryCheckpoint = null;
            if (is_file($destination)) {
                try {
                    $previous = json_decode(
                        file_get_contents($destination),
                        true,
                        128,
                        JSON_THROW_ON_ERROR,
                    );
                    $this->protocol->verify($previous, $config['authority']);
                } catch (\DomainException|\JsonException) {
                    $previous = null;
                    $checkpointFile = $directory . '/checkpoint.json';
                    if (!is_file($checkpointFile)) {
                        throw new \RuntimeException('Copie locale corrompue ; restaurer son point de contrôle indépendant.');
                    }
                    $recoveryCheckpoint = json_decode(
                        file_get_contents($checkpointFile),
                        true,
                        64,
                        JSON_THROW_ON_ERROR,
                    );
                    $this->protocol->verifyCheckpoint(
                        $recoveryCheckpoint,
                        $config['authority'],
                    );
                }
            }
            $result = $this->protocol->accept(
                $envelope,
                $config['authority'],
                $previous,
            );
            if (
                $recoveryCheckpoint
                && ($envelope['count'] < $recoveryCheckpoint['count']
                    || ($envelope['state']['events'][
                        $recoveryCheckpoint['count'] - 1
                    ]['hash'] ??
                        '') !==
                        $recoveryCheckpoint['head'])
            ) {
                throw new \DomainException('La copie saine ne prolonge pas le dernier point de contrôle approuvé.');
            }
            $checkpointTemporary = tempnam($directory, 'checkpoint-');
            file_put_contents(
                $checkpointTemporary,
                json_encode(
                    $this->protocol->checkpoint($envelope),
                    JSON_THROW_ON_ERROR,
                ),
            );
            chmod($checkpointTemporary, 0600);
            rename($checkpointTemporary, $directory . '/checkpoint.json');
            chmod($temporary, 0600);
            if (!rename($temporary, $destination)) {
                throw new \RuntimeException('Remplacement atomique de la copie impossible.');
            }
            file_put_contents(
                $directory . '/status.json',
                json_encode(
                    $result + [
                        'at' => gmdate('c'),
                        'receivedBytes' => $received,
                        'protocol' => 'populorum-public-v1',
                    ],
                    JSON_THROW_ON_ERROR,
                ),
                LOCK_EX,
            );

            return $result + ['receivedBytes' => $received];
        } finally {
            file_put_contents(
                $usageFile,
                json_encode($usage, JSON_THROW_ON_ERROR),
                LOCK_EX,
            );
            if (file_exists($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function configuration(string $file): array
    {
        $config = json_decode(
            file_get_contents($file),
            true,
            64,
            JSON_THROW_ON_ERROR,
        );
        foreach (['url', 'directory', 'authority'] as $key) {
            if (!is_string($config[$key] ?? null) || $config[$key] === '') {
                throw new \RuntimeException('Configuration incomplète : ' . $key);
            }
        }
        foreach (
            ['enabled', 'metered', 'batteryLow', 'temperatureExcessive'] as $boolean
        ) {
            if (isset($config[$boolean]) && !is_bool($config[$boolean])) {
                throw new \RuntimeException('Valeur booléenne requise : ' . $boolean);
            }
        }
        $config += [
            'enabled' => false,
            'maxBytesPerSecond' => 16384,
            'authorizedUplinkBytesPerSecond' => 163840,
            'activePercent' => 10,
            'monthlyBytes' => 100000000,
            'maxCopyBytes' => 10000000,
            'hours' => [0, 24],
            'metered' => false,
            'batteryLow' => false,
            'temperatureExcessive' => false,
        ];
        foreach (
            [
                'maxBytesPerSecond',
                'authorizedUplinkBytesPerSecond',
                'monthlyBytes',
                'maxCopyBytes',
            ] as $key
        ) {
            if (!is_int($config[$key]) || $config[$key] < 1) {
                throw new \RuntimeException('Plafond entier positif requis : ' . $key);
            }
        }
        if (
            !is_array($config['hours'])
            || count($config['hours']) !== 2
            || !is_int($config['hours'][0])
            || !is_int($config['hours'][1])
            || $config['hours'][0] < 0
            || $config['hours'][1] > 24
            || $config['hours'][0] >= $config['hours'][1]
        ) {
            throw new \RuntimeException('Plage horaire UTC invalide.');
        }
        if (
            !is_numeric($config['activePercent'])
            || $config['activePercent'] < 1
            || $config['activePercent'] > 50
        ) {
            throw new \RuntimeException('Le plafond volontaire doit rester entre 1 et 50 %.');
        }

        return $config;
    }

    private function permitted(array $config): void
    {
        foreach (
            glob('/sys/class/power_supply/*/capacity') ?: [] as $capacityFile
        ) {
            $statusFile = dirname($capacityFile) . '/status';
            if (
                is_file($statusFile)
                && trim(file_get_contents($statusFile)) === 'Discharging'
                && (int) trim(file_get_contents($capacityFile)) <
                    ($config['batteryMinimumPercent'] ?? 20)
            ) {
                throw new \RuntimeException('Relais suspendu : batterie faible détectée localement.');
            }
        }
        foreach (
            glob('/sys/class/thermal/thermal_zone*/temp') ?: [] as $temperatureFile
        ) {
            $temperature =
                (float) trim(file_get_contents($temperatureFile)) / 1000;
            if (
                $temperature >= ($config['temperatureLimitC'] ?? 80)
                && $temperature <= 150
            ) {
                throw new \RuntimeException('Relais suspendu : température excessive détectée localement.');
            }
        }
        $hour = (int) gmdate('G');
        if (
            !$config['enabled']
            || $config['metered']
            || $config['batteryLow']
            || $config['temperatureExcessive']
            || $hour < $config['hours'][0]
            || $hour >= $config['hours'][1]
        ) {
            throw new \RuntimeException('Agent désactivé, hors plage UTC ou suspendu par les limites matérielles déclarées.');
        }
    }
}
