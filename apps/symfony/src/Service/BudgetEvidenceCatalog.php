<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Centralised documentary catalogue. References establish where to verify facts,
 * never that a specific monetary transfer or final use actually occurred.
 */
final class BudgetEvidenceCatalog
{
    public const GROUPS = [
        'comptes' => 'Budgets et comptes exécutés',
        'etat' => 'Budgets votés et prévisions',
        'subventions' => 'Subventions et aides',
        'marches' => 'Marchés et commandes publiques',
        'associations' => 'Associations et comptes annuels',
        'international' => 'Financements internationaux',
        'methodes' => 'Méthodes et contrôles',
    ];

    private ?array $cache = null;

    public function __construct(private readonly string $projectDir)
    {
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $file = $this->projectDir . '/data/research/public-budget-sources.json';
        if (!is_file($file) || filesize($file) > 1048576) {
            return $this->cache = [];
        }
        try {
            $json = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->cache = [];
        }
        if (!is_array($json) || ($json['schema'] ?? '') !== 'populorum-budget-evidence-v1') {
            return $this->cache = [];
        }
        $result = [];
        foreach (array_slice($json['sources'] ?? [], 0, 1000) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['id'] ?? null;
            $url = $row['url'] ?? null;
            $scope = $row['scope'] ?? null;
            $group = $row['group'] ?? null;
            if (!is_string($id) || !preg_match('/\A[a-z0-9_-]{1,64}\z/D', $id) || isset($result[$id])
                || !is_string($url) || strlen($url) > 2048 || !str_starts_with($url, 'https://') || !filter_var($url, FILTER_VALIDATE_URL)
                || !is_string($scope) || ($scope !== 'FR' && !preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $scope))
                || !is_string($group) || !isset(self::GROUPS[$group])
            ) {
                continue;
            }
            $entry = ['id' => $id, 'url' => $url, 'scope' => $scope, 'group' => $group];
            foreach (['title', 'description', 'limitations', 'year', 'level', 'kind'] as $field) {
                $value = $row[$field] ?? '';
                $entry[$field] = is_string($value) ? mb_substr(trim($value), 0, 500) : '';
            }
            if ($entry['title'] === '' || $entry['description'] === '') {
                continue;
            }
            $entry['transversal'] = ($row['transversal'] ?? false) === true;
            $result[$id] = $entry;
        }

        return $this->cache = $result;
    }

    /** For a commune: direct local sources plus nationwide methodology/portals, labelled as such. */
    public function forScope(string $code, string $group = ''): array
    {
        $entries = [];
        foreach ($this->all() as $entry) {
            if ($group !== '' && $entry['group'] !== $group) {
                continue;
            }
            if ($code === 'FR' || $entry['scope'] === $code || ($entry['scope'] === 'FR' && $entry['transversal'])) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function grouped(string $code): array
    {
        $grouped = [];
        foreach (self::GROUPS as $id => $label) {
            $items = $this->forScope($code, $id);
            if ($items) {
                $grouped[$id] = ['id' => $id, 'label' => $label, 'items' => $items, 'count' => count($items)];
            }
        }

        return $grouped;
    }

    public function get(string $id): ?array
    {
        return $this->all()[$id] ?? null;
    }
}
