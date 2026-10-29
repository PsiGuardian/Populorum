<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/** Sauvegarde SQLite cohérente, chiffrée et authentifiée ; restauration exclusivement vers un nouveau fichier. */
final class BackupService
{
    public function __construct(private SimulationEngine $engine)
    {
    }

    public function create(
        Connection $connection,
        string $destination,
        string $key,
    ): array {
        $this->key($key);
        if (file_exists($destination)) {
            throw new \RuntimeException('La destination existe déjà ; aucune sauvegarde n’est écrasée.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'populorum-backup-');
        unlink($temporary);
        try {
            // VACUUM INTO prend un snapshot SQLite cohérent, y compris avec un journal WAL actif.
            $connection->executeStatement('VACUUM INTO ?', [$temporary]);
            $this->validate($temporary);
            $plain = file_get_contents($temporary);
            $iv = random_bytes(12);
            $encrypted = openssl_encrypt(
                gzencode($plain, 6),
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                'populorum-backup-v1',
            );
            if ($encrypted === false) {
                throw new \RuntimeException('Chiffrement impossible.');
            }
            $envelope = [
                'format' => 'populorum-backup-v1',
                'createdAt' => gmdate('c'),
                'cipher' => 'AES-256-GCM',
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'ciphertext' => base64_encode($encrypted),
            ];
            $this->writeExclusive(
                $destination,
                json_encode($envelope, JSON_THROW_ON_ERROR),
            );

            return [
                'sha256' => hash_file('sha256', $destination),
                'bytes' => filesize($destination),
                'createdAt' => $envelope['createdAt'],
            ];
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function restore(
        string $backup,
        string $destination,
        string $key,
    ): array {
        $this->key($key);
        if (file_exists($destination)) {
            throw new \RuntimeException('Restauration refusée vers un fichier existant. Choisissez une nouvelle destination.');
        }
        $envelope = json_decode(
            file_get_contents($backup),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        if (($envelope['format'] ?? '') !== 'populorum-backup-v1') {
            throw new \RuntimeException('Format de sauvegarde inconnu.');
        }
        $ciphertext = base64_decode($envelope['ciphertext'] ?? '', true);
        $iv = base64_decode($envelope['iv'] ?? '', true);
        $tag = base64_decode($envelope['tag'] ?? '', true);
        if (
            $ciphertext === false
            || $iv === false
            || strlen($iv) !== 12
            || $tag === false
            || strlen($tag) !== 16
        ) {
            throw new \RuntimeException('Enveloppe de sauvegarde invalide.');
        }
        $compressed = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'populorum-backup-v1',
        );
        if (
            $compressed === false
            || ($plain = gzdecode($compressed, 512 * 1024 * 1024)) === false
        ) {
            throw new \RuntimeException('Clé incorrecte ou sauvegarde altérée.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'populorum-restore-');
        try {
            file_put_contents($temporary, $plain);
            chmod($temporary, 0600);
            $result = $this->validate($temporary);
            $this->writeExclusive($destination, $plain);

            return $result + [
                'sha256' => hash_file('sha256', $destination),
                'bytes' => filesize($destination),
            ];
        } finally {
            unlink($temporary);
        }
    }

    public function validate(string $database): array
    {
        $pdo = new \PDO(
            'sqlite:' . $database,
            options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
            throw new \RuntimeException('Copie SQLite corrompue.');
        }
        $count = 0;
        foreach ($pdo->query('SELECT state FROM simulations') as $row) {
            $state = json_decode($row['state'], true, 512, JSON_THROW_ON_ERROR);
            if (!$this->engine->verify($state)['valid']) {
                throw new \RuntimeException('Un journal de simulation ne vérifie pas ses preuves.');
            }
            ++$count;
        }

        return ['integrity' => 'ok', 'simulationsVerified' => $count];
    }

    private function key(string $key): void
    {
        if (strlen($key) !== 32) {
            throw new \RuntimeException('Une clé binaire indépendante de 32 octets est nécessaire.');
        }
    }

    private function writeExclusive(string $path, string $content): void
    {
        $file = fopen($path, 'xb');
        if (!$file) {
            throw new \RuntimeException('Impossible de créer le fichier.');
        }
        chmod($path, 0600);
        try {
            $written = 0;
            while ($written < strlen($content)) {
                $size = fwrite($file, substr($content, $written));
                if ($size === false || $size === 0) {
                    throw new \RuntimeException('Écriture interrompue.');
                }
                $written += $size;
            }
            fflush($file);
        } finally {
            fclose($file);
        }
    }
}
