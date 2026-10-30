<?php

declare(strict_types=1);

namespace App\Service;

/** Réplication de données publiques de simulation ; autorité épinglée, pas de consensus ni d’autorité de vote. */
final class ReplicaProtocol
{
    private const PUBLIC_FIELDS = [
        'schemaVersion',
        'simulation',
        'territories',
        'citizens',
        'proposals',
        'consultations',
        'votes',
        'experiments',
        'finance',
        'resources',
        'governance',
        'cases',
        'appeals',
        'alerts',
        'nodes',
        'adaptations',
        'events',
        'publicKey',
        'ruleSets',
        'preferences',
        'mentalSimulation',
    ];

    public function __construct(private SimulationEngine $engine)
    {
    }

    public function fingerprint(string $publicKey): string
    {
        return hash('sha256', trim(str_replace("\r\n", "\n", $publicKey)));
    }

    public function publish(array $privateState): array
    {
        if (
            !$privateState['events']
            || !$this->engine->verify($privateState)['valid']
            || empty($privateState['keyPair']['privateKey'])
        ) {
            throw new \DomainException('Une simulation signée et valide est nécessaire.');
        }
        $public = $this->engine->publicState($privateState);
        $this->publicOnly($public);
        $envelope = [
            'protocol' => 'populorum-public-v1',
            'createdAt' => gmdate('c'),
            'authority' => $this->fingerprint($public['publicKey']),
            'head' => end($public['events'])['hash'],
            'count' => count($public['events']),
            'stateDigest' => $this->engine->digest($public),
            'state' => $public,
        ];
        $checkpoint = array_diff_key($envelope, ['state' => true]) + [
            'publicKey' => $public['publicKey'],
        ];
        openssl_sign(
            $this->engine->digest($checkpoint),
            $checkpointSignature,
            $privateState['keyPair']['privateKey'],
            OPENSSL_ALGO_SHA256,
        );
        $envelope['checkpointSignature'] = base64_encode($checkpointSignature);
        openssl_sign(
            $this->engine->digest($envelope),
            $signature,
            $privateState['keyPair']['privateKey'],
            OPENSSL_ALGO_SHA256,
        );

        return $envelope + ['signature' => base64_encode($signature)];
    }

    public function verify(array $envelope, string $pinnedAuthority): array
    {
        if (
            ($envelope['protocol'] ?? '') !== 'populorum-public-v1'
            || !preg_match('/^[a-f0-9]{64}$/D', $pinnedAuthority)
        ) {
            throw new \DomainException('Protocole ou autorité épinglée invalide.');
        }
        $state = $envelope['state'] ?? [];
        $this->publicOnly($state);
        if (
            !isset($state['publicKey'])
            || !hash_equals(
                $pinnedAuthority,
                $this->fingerprint($state['publicKey']),
            )
            || !hash_equals($pinnedAuthority, $envelope['authority'] ?? '')
        ) {
            throw new \DomainException('L’autorité de la copie ne correspond pas à la clé approuvée.');
        }
        $signature = base64_decode($envelope['signature'] ?? '', true);
        $unsigned = $envelope;
        unset($unsigned['signature']);
        if (
            $signature === false
            || openssl_verify(
                $this->engine->digest($unsigned),
                $signature,
                $state['publicKey'],
                OPENSSL_ALGO_SHA256,
            ) !== 1
            || !$this->engine->verify($state)['valid']
            || !$state['events']
            || !hash_equals(
                $envelope['stateDigest'],
                $this->engine->digest($state),
            )
            || !hash_equals($envelope['head'], end($state['events'])['hash'])
            || $envelope['count'] !== count($state['events'])
            || strtotime($envelope['createdAt']) > time() + 300
        ) {
            throw new \DomainException('Copie altérée, signature invalide ou horloge incohérente.');
        }

        return [
            'valid' => true,
            'head' => $envelope['head'],
            'count' => $envelope['count'],
            'authority' => $pinnedAuthority,
        ];
    }

    public function accept(
        array $envelope,
        string $authority,
        ?array $previous,
    ): array {
        $result = $this->verify($envelope, $authority);
        if ($previous) {
            $this->verify($previous, $authority);
            if ($envelope['count'] < $previous['count']) {
                throw new \DomainException('Rejeu ou copie plus ancienne refusé.');
            }
            $previousEvents = $previous['state']['events'];
            foreach ($previousEvents as $index => $event) {
                if (
                    ($envelope['state']['events'][$index]['hash'] ?? '') !==
                    $event['hash']
                ) {
                    throw new \DomainException('Divergence de branche : intervention indépendante requise.');
                }
            }
            $result['unchanged'] = $envelope['head'] === $previous['head'];
        }

        return $result;
    }

    public function checkpoint(array $envelope): array
    {
        return array_diff_key($envelope, [
            'state' => true,
            'signature' => true,
            'checkpointSignature' => true,
        ]) + [
            'publicKey' => $envelope['state']['publicKey'],
            'signature' => $envelope['checkpointSignature'],
        ];
    }

    public function verifyCheckpoint(array $checkpoint, string $authority): void
    {
        $signature = base64_decode($checkpoint['signature'] ?? '', true);
        unset($checkpoint['signature']);
        if (
            ($checkpoint['protocol'] ?? '') !== 'populorum-public-v1'
            || !is_string($checkpoint['publicKey'] ?? null)
            || !hash_equals(
                $authority,
                $this->fingerprint($checkpoint['publicKey']),
            )
            || $signature === false
            || openssl_verify(
                $this->engine->digest($checkpoint),
                $signature,
                $checkpoint['publicKey'],
                OPENSSL_ALGO_SHA256,
            ) !== 1
        ) {
            throw new \DomainException('Point de contrôle indépendant invalide.');
        }
    }

    private function publicOnly(array $state): void
    {
        if (
            ($state['simulation'] ?? false) !== true
            || array_diff(array_keys($state), self::PUBLIC_FIELDS)
        ) {
            throw new \DomainException('Seules les données publiques explicitement prévues de la simulation sont réplicables.');
        }
        $walk = function (array $value) use (&$walk): void {
            foreach ($value as $key => $item) {
                if (
                    in_array(
                        strtolower((string) $key),
                        [
                            'keypair',
                            'privatekey',
                            'password',
                            'passwordhash',
                            'password_hash',
                            'credentials',
                            'session',
                            '_token',
                            'operations',
                        ],
                        true,
                    )
                    || (is_string($item) && str_contains($item, 'PRIVATE KEY'))
                ) {
                    throw new \DomainException('Transmission de données privées refusée.');
                }
                if (is_array($item)) {
                    $walk($item);
                }
            }
        };
        $walk($state);
    }
}
