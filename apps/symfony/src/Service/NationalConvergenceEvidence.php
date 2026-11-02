<?php

declare(strict_types=1);

namespace App\Service;

/** Read-only, sourced SSMSI statistics; never converts undisclosed data to zero. */
final class NationalConvergenceEvidence implements \Symfony\Contracts\Service\ResetInterface
{
    private ?\PDO $db = null;
    private bool $attempted = false;

    public function __construct(
        private readonly string $projectDir,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'cache.app')]
        private readonly ?\Symfony\Contracts\Cache\CacheInterface $publicCache = null,
    ) {
    }

    public function reset(): void
    {
        $this->db = null;
        $this->attempted = false;
    }

    private function connection(): ?\PDO
    {
        if ($this->attempted) {
            return $this->db;
        }
        $this->attempted = true;
        $path = $this->projectDir . '/data/research/ssmsi-national-2025.sqlite';
        if (!is_file($path)) {
            return null;
        }
        try {
            $db = new \PDO('sqlite:file:' . $path . '?mode=ro', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $db->exec('PRAGMA query_only=ON');
            if ($db->query("SELECT value FROM metadata WHERE key='schema'")->fetchColumn()
                !== 'populorum-ssmsi-national-1') {
                return null;
            }

            return $this->db = $db;
        } catch (\PDOException) {
            return null;
        }
    }

    public function coverage(): array
    {
        $db = $this->connection();
        if ($db === null) {
            return ['available' => false, 'publishedCommunes' => 0, 'rows' => 0, 'source' => null, 'sha256' => null];
        }
        $metadata = $db->query('SELECT key, value FROM metadata')->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'available' => true,
            'publishedCommunes' => (int) ($metadata['communes_with_published_2025'] ?? 0),
            'rows' => (int) ($metadata['indexed_rows'] ?? 0),
            'source' => $metadata['source_url'] ?? null,
            'sha256' => $metadata['source_sha256'] ?? null,
        ];
    }

    public function indicators(): array
    {
        $db = $this->connection();
        if ($db === null) {
            return [];
        }

        $load = static fn (): array => $db->query('SELECT DISTINCT indicator FROM facts ORDER BY indicator')->fetchAll(\PDO::FETCH_COLUMN);
        if ($this->publicCache === null) {
            return $load();
        }
        $file = $this->projectDir . '/data/research/ssmsi-national-2025.sqlite';
        $key = 'ssmsi_indicators_' . hash('sha256', $file . ':' . filemtime($file) . ':' . filesize($file));

        return $this->publicCache->get($key, static function ($item) use ($load): array {
            $item->expiresAfter(1800);

            return $load();
        });
    }

    /** @return array<string, array{status: string, rate: ?float, count: ?int}> */
    public function publishedForPage(array $codes, string $indicator): array
    {
        $db = $this->connection();
        if ($db === null || !$codes || $indicator === '') {
            return [];
        }
        $codes = array_values(array_unique(array_filter($codes, GeographyCatalog::validCode(...))));
        if (!$codes) {
            return [];
        }
        $sql = 'SELECT code, status, rate, count FROM facts WHERE year = 2025 AND indicator = ? AND code IN ('
            . implode(',', array_fill(0, count($codes), '?')) . ')';
        $statement = $db->prepare($sql);
        $statement->execute(array_merge([$indicator], $codes));
        $results = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $results[$row['code']] = [
                'status' => $row['status'],
                'rate' => $row['status'] === 'diff' && $row['rate'] !== null ? (float) $row['rate'] : null,
                'count' => $row['status'] === 'diff' && $row['count'] !== null ? (int) $row['count'] : null,
            ];
        }

        return $results;
    }

    public function city(string $code, string $indicator): array
    {
        $db = $this->connection();
        if ($db === null || !GeographyCatalog::validCode($code) || $indicator === '') {
            return [];
        }
        $statement = $db->prepare(
            'SELECT year, status, rate, count, count_unit, population_vintage, housing_vintage'
            . ' FROM facts WHERE code = ? AND indicator = ? AND year IN (2024,2025) ORDER BY year',
        );
        $statement->execute([$code, $indicator]);
        $years = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $published = $row['status'] === 'diff' && $row['rate'] !== null;
            $years[(int) $row['year']] = [
                'status' => $row['status'],
                'rate' => $published ? (float) $row['rate'] : null,
                'count' => $published && $row['count'] !== null ? (int) $row['count'] : null,
                'countUnit' => $row['count_unit'],
                'populationVintage' => $row['population_vintage'],
                'housingVintage' => $row['housing_vintage'],
            ];
        }

        return $years;
    }
}
