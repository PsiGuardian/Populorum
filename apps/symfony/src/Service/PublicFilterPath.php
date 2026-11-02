<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Canonical, readable filter paths for national public pages.
 * Example: /marches-publics/q/web/kind/market/year/2025
 * Values are validated as data; this class never builds SQL expressions.
 */
final class PublicFilterPath
{
    public const ROUTES = [
        'marches-publics' => ['q', 'kind', 'sort', 'year', 'buyer', 'supplier', 'city', 'department', 'offset'],
        'communes' => ['q', 'region', 'department', 'page'],
        'convergence' => ['communes_q', 'region', 'department', 'indicator', 'commune', 'atlas_page', 'id', 'territory', 'page'],
        'budget-et-priorites' => ['scope', 'city', 'code', 'saving', 'resources', 'ledger_city', 'account', 'ledger_prefix', 'category', 'sort', 'term', 'destination', 'trace', 'page'],
    ];

    public static function keys(string $base): array
    {
        return self::ROUTES[$base] ?? [];
    }

    public static function encode(string $base, array $values): string
    {
        $parts = [];
        foreach (self::keys($base) as $key) {
            $value = $values[$key] ?? null;
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '' || ($key === 'sort' && $value === 'date_desc')
                || (in_array($key, ['page', 'atlas_page'], true) && $value === '1')
                || ($key === 'offset' && $value === '0')) {
                continue;
            }
            self::validate($key, $value);
            $parts[] = $key;
            $parts[] = rawurlencode($value);
        }

        return $parts === [] ? '' : '/' . implode('/', $parts);
    }

    public static function decode(string $base, string $slug): array
    {
        $parts = explode('/', $slug);
        if (count($parts) % 2 !== 0 || count($parts) > 40) {
            throw new \InvalidArgumentException('Chemin de filtres invalide.');
        }
        $values = [];
        foreach (array_chunk($parts, 2) as [$key, $raw]) {
            if (!in_array($key, self::keys($base), true) || isset($values[$key])
                || $raw === '' || preg_match('/%(?![0-9a-fA-F]{2})/', $raw)) {
                throw new \InvalidArgumentException('Critère inconnu ou dupliqué.');
            }
            $value = rawurldecode($raw);
            self::validate($key, $value);
            $values[$key] = $value;
        }

        return $values;
    }

    private static function validate(string $key, string $value): void
    {
        if (!mb_check_encoding($value, 'UTF-8') || strlen($value) > 160
            || preg_match('/[\x00-\x1f\x7f\/\\\\]/', $value)
            || in_array($value, ['.', '..'], true)) {
            throw new \InvalidArgumentException('Valeur de filtre invalide.');
        }
        if (in_array($key, ['page', 'atlas_page', 'offset'], true)
            && preg_match('/\A[0-9]{1,7}\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('Pagination invalide.');
        }
        if ($key === 'year' && preg_match('/\A[0-9]{4}\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('Année invalide.');
        }
        if (in_array($key, ['saving', 'resources'], true)
            && preg_match('/\A-?[0-9]{1,12}\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('Nombre invalide.');
        }
        if (in_array($key, ['city', 'commune', 'department', 'region'], true)
            && preg_match('/\A[A-Za-z0-9]{1,8}\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('Code territorial invalide.');
        }
        if (in_array($key, ['buyer', 'supplier'], true)
            && preg_match('/\A[0-9]{9}(?:[0-9]{5})?\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('SIREN ou SIRET invalide.');
        }
    }
}
