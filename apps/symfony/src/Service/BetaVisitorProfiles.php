<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * IP-only pseudonymous beta profile. Not an authentication or authorization layer.
 * A shared public address has one shared, unprivileged demo profile.
 */
final class BetaVisitorProfiles
{
    private const LIFETIME = 30 * 86400;

    public function __construct(
        private readonly Connection $connection,
        private readonly GeographyCatalog $geography,
        private readonly string $appSecret,
    ) {
    }

    private function fingerprint(Request $request): string
    {
        $ip = $request->getClientIp();
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new \DomainException('Connexion non identifiable pour le profil de test.', 400);
        }

        return hash_hmac('sha256', 'populorum-beta-profile-v1:' . $ip, $this->appSecret);
    }

    public function find(Request $request): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT first_name, last_name, age, commune_code, created_at, expires_at
             FROM beta_visitor_profiles WHERE ip_fingerprint = ? AND expires_at > ?',
            [$this->fingerprint($request), time()],
        );
        if ($row === false) {
            return null;
        }
        $commune = $this->geography->find((string) $row['commune_code']);

        // Identité de participation stable pour la durée du profil, sans exposer l'IP ni son empreinte.
        $citizenId = 'beta_' . substr(hash_hmac(
            'sha256',
            'populorum-beta-citizen-v1:' . $this->fingerprint($request) . ':' . $row['created_at'],
            $this->appSecret,
        ), 0, 24);

        return [
            'citizenId' => $citizenId,
            'expiresAt' => (int) $row['expires_at'],
            'firstName' => (string) $row['first_name'],
            'lastName' => (string) $row['last_name'],
            'age' => (int) $row['age'],
            'communeCode' => (string) $row['commune_code'],
            'communeName' => $commune['name'] ?? (string) $row['commune_code'],
        ];
    }

    public function create(Request $request, string $firstName, string $lastName, string $age, string $commune): array
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $commune = strtoupper(trim($commune));
        foreach ([$firstName, $lastName] as $value) {
            if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) < 2 || mb_strlen($value) > 80
                || preg_match('/[\x00-\x1f\x7f<>]/u', $value) === 1) {
                throw new \DomainException('Prénom et nom : entre 2 et 80 caractères.', 400);
            }
        }
        if (preg_match('/\A[0-9]{1,3}\z/D', $age) !== 1 || (int) $age < 1 || (int) $age > 120) {
            throw new \DomainException('Âge : indiquez un nombre de 1 à 120.', 400);
        }
        if (!GeographyCatalog::validCode($commune) || $this->geography->find($commune) === null) {
            throw new \DomainException('Sélectionnez une commune du référentiel INSEE 2026.', 400);
        }
        $fingerprint = $this->fingerprint($request);
        $this->connection->transactional(function (Connection $db) use (
            $fingerprint, $firstName, $lastName, $age, $commune,
        ): void {
            $db->executeStatement('DELETE FROM beta_visitor_profiles WHERE expires_at <= ?', [time()]);
            // Same IP always refers to the same profile while it exists; no overwrites or extra profiles.
            $db->executeStatement(
                'INSERT OR IGNORE INTO beta_visitor_profiles
                    (ip_fingerprint, first_name, last_name, age, commune_code, created_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$fingerprint, $firstName, $lastName, (int) $age, $commune, gmdate('Y-m-d\TH:i:s\Z'), time() + self::LIFETIME],
            );
        });

        return $this->find($request) ?? throw new \RuntimeException('Impossible de créer le profil.');
    }

    public function delete(Request $request): void
    {
        $this->connection->executeStatement(
            'DELETE FROM beta_visitor_profiles WHERE ip_fingerprint = ?',
            [$this->fingerprint($request)],
        );
    }

    /** Stateless CSRF token: no cookie or session needed for the registration form. */
    public function formToken(Request $request, string $action, ?int $time = null): string
    {
        $hour = intdiv($time ?? time(), 3600);

        return hash_hmac('sha256', $this->fingerprint($request) . ':' . $action . ':' . $hour, $this->appSecret);
    }

    public function validFormToken(Request $request, string $action, string $token): bool
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
            return false;
        }

        return hash_equals($this->formToken($request, $action), $token)
            || hash_equals($this->formToken($request, $action, time() - 3600), $token);
    }
}
