<?php

declare(strict_types=1);

namespace App\Service;

/** Les noms appartiennent au profil effaçable, pas aux charges immuables du journal. */
final class AuditPrivacy
{
    public function changes(string $action, array $payload): array
    {
        if (in_array($action, ['citizen.create', 'citizen.update'], true)) {
            unset($payload['name']);
        }

        return $payload;
    }

    /**
     * Compatibilité des simulations antérieures : une attestation signée rend
     * l’expurgation explicite, tout en conservant l’empreinte et la signature d’origine.
     * Les exports déjà téléchargés ne peuvent pas être rappelés.
     */
    public function eraseLegacyNames(
        array &$state,
        string $citizenId,
        callable $digest,
    ): void {
        foreach ($state['events'] as &$event) {
            if (
                !in_array(
                    $event['type'],
                    ['citizen.create', 'citizen.update'],
                    true,
                )
                || $event['object'] !== $citizenId
                || !isset($event['payload']['changes']['name'])
            ) {
                continue;
            }
            unset($event['payload']['changes']['name']);
            $content = $event;
            unset(
                $content['hash'],
                $content['signature'],
                $content['redaction'],
            );
            $attestation = [
                'originalHash' => $event['hash'],
                'redactedDigest' => $digest($content),
                'fields' => ['payload.changes.name'],
                'reason' => 'Effacement du nom demandé par le personnage concerné',
                'at' => gmdate("Y-m-d\TH:i:s\Z"),
            ];
            if (
                !openssl_sign(
                    $digest($attestation),
                    $signature,
                    $state['keyPair']['privateKey'],
                    OPENSSL_ALGO_SHA256,
                )
            ) {
                throw new \RuntimeException('Attestation d’expurgation impossible.');
            }
            $event['redaction'] = $attestation + [
                'signature' => base64_encode($signature),
            ];
        }
    }

    public function verifiesRedaction(
        array $event,
        string $originalHash,
        string $publicKey,
        callable $digest,
    ): bool {
        $attestation = $event['redaction'] ?? null;
        if (
            !is_array($attestation)
            || !is_string($attestation['signature'] ?? null)
        ) {
            return false;
        }
        unset($event['redaction']);
        $signature = $attestation['signature'];
        unset($attestation['signature']);

        return ($attestation['originalHash'] ?? '') === $originalHash
            && ($attestation['fields'] ?? []) === ['payload.changes.name']
            && hash_equals(
                $attestation['redactedDigest'] ?? '',
                $digest($event),
            )
            && openssl_verify(
                $digest($attestation),
                base64_decode($signature, true) ?: '',
                $publicKey,
                OPENSSL_ALGO_SHA256,
            ) === 1;
    }
}
