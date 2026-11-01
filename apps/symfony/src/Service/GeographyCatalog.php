<?php

declare(strict_types=1);

namespace App\Service;

/** Unique INSEE COG commune reference for all read-only citizen views.
 * It never creates fictitious residents, votes or financial records.
 */
final class GeographyCatalog implements \Symfony\Contracts\Service\ResetInterface
{
    private ?\PDO $db = null;
    private bool $attempted = false;
    private ?array $pilots = null;
    private ?bool $hasPostals = null;

    public function __construct(private readonly string $projectDir)
    {
    }

    public function reset(): void
    {
        $this->db = null;
        $this->attempted = false;
        $this->hasPostals = null;
    }

    private function connection(): ?\PDO
    {
        if ($this->attempted) {
            return $this->db;
        }
        $this->attempted = true;
        $path = $this->projectDir . '/data/geography/communes-2026.sqlite';
        if (!is_file($path)) {
            return null;
        }
        try {
            $db = new \PDO('sqlite:file:' . $path . '?mode=ro', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $db->exec('PRAGMA query_only=ON');
            $version = $db->query("SELECT value FROM metadata WHERE key='vintage'")->fetchColumn();
            if ($version !== '2026') {
                return null;
            }

            return $this->db = $db;
        } catch (\PDOException) {
            return null;
        }
    }

    private function hasPostals(\PDO $db): bool
    {
        if ($this->hasPostals === null) {
            $this->hasPostals = (bool) $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='postal_codes'")->fetchColumn();
        }

        return $this->hasPostals;
    }

    private function postals(\PDO $db, string $code): array
    {
        if (!$this->hasPostals($db)) {
            return [];
        }
        $statement = $db->prepare('SELECT postal_code FROM postal_codes WHERE code = ? ORDER BY postal_code LIMIT 20');
        $statement->execute([$code]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function pilotList(): array
    {
        if ($this->pilots !== null) {
            return $this->pilots;
        }
        $path = $this->projectDir . '/data/cities.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $list = [];
        foreach (is_array($data) ? $data : [] as $item) {
            $code = (string) ($item['code'] ?? '');
            if (!self::validCode($code)) {
                continue;
            }
            $list[$code] = [
                'code' => $code, 'name' => (string) $item['name'],
                'regionCode' => (string) ($item['regionCode'] ?? ''),
                'region' => (string) ($item['region'] ?? ''),
                'departmentCode' => (string) ($item['departmentCode'] ?? ''),
                'department' => (string) ($item['department'] ?? ''),
                'pilotId' => $item['id'], 'source' => (string) ($item['sourceUrl'] ?? ''), 'postalCodes' => [],
            ];
        }

        return $this->pilots = $list;
    }

    public static function validCode(string $code): bool
    {
        return preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $code) === 1;
    }

    public function coverage(): array
    {
        $db = $this->connection();
        if ($db === null) {
            return ['available' => false, 'count' => count($this->pilotList()), 'year' => 2026, 'postals' => false,
                'label' => 'Référentiel national INSEE non importé : seules les communes pilotes sont visibles.'];
        }

        return [
            'available' => true,
            'postals' => $this->hasPostals($db),
            'count' => (int) $db->query('SELECT COUNT(*) FROM communes')->fetchColumn(),
            'year' => 2026,
            'label' => 'Communes du Code officiel géographique INSEE au 1er janvier 2026 (hors arrondissements municipaux et communes déléguées).',
        ];
    }

    public function find(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!self::validCode($code)) {
            return null;
        }
        if (($db = $this->connection()) !== null) {
            $query = $db->prepare('SELECT code, name, region_code AS regionCode, region_name AS region, department_code AS departmentCode, department_name AS department FROM communes WHERE code = ?');
            $query->execute([$code]);
            $row = $query->fetch(\PDO::FETCH_ASSOC);
            if ($row !== false) {
                $row['pilotId'] = $this->pilotList()[$code]['pilotId'] ?? null;
                $row['source'] = 'https://www.insee.fr/fr/information/8740222';
                $row['postalCodes'] = $this->postals($db, $code);

                return $row;
            }

            // Never substitute a former pilot when a newer COG no longer lists its code.
            return null;
        }

        return $this->pilotList()[$code] ?? null;
    }

    public function pilots(): array
    {
        return array_values($this->pilotList());
    }

    public function search(string $term, int $limit = 30, int $offset = 0): array
    {
        $term = trim($term);
        if (strlen($term) < 2 || strlen($term) > 200) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $offset = max(0, min(50000, $offset));
        if (($db = $this->connection()) !== null) {
            $folded = strtolower(function_exists('iconv')
                ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $term) ?: $term)
                : $term);
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $folded);
            $hasPostals = $this->hasPostals($db);
            // Five-digit text is a postal code by default, never an INSEE identifier.
            // For a precise administrative lookup the user must enter INSEE:76351.
            if (preg_match('/\A(?:INSEE|COG)\s*:\s*((?:[0-9]{5}|2[AB][0-9]{3}))\z/iD', $term, $match)) {
                $q = $db->prepare('SELECT code, name, department_name AS department, region_name AS region'
                    . ($hasPostals ? ", (SELECT GROUP_CONCAT(postal_code, ', ') FROM postal_codes p WHERE p.code=communes.code) AS postalCodes" : ", '' AS postalCodes")
                    . ' FROM communes WHERE code = ? LIMIT 1');
                $q->execute([strtoupper($match[1])]);

                return $q->fetchAll(\PDO::FETCH_ASSOC);
            }
            if (preg_match('/\A[0-9]{5}\z/D', $term)) {
                if (!$hasPostals) {
                    return []; // Never mislabel an INSEE code as a postal result.
                }
                $q = $db->prepare('SELECT communes.code, communes.name, communes.department_name AS department,'
                    . ' communes.region_name AS region,'
                    . " (SELECT GROUP_CONCAT(postal_code, ', ') FROM postal_codes p2 WHERE p2.code=communes.code) AS postalCodes"
                    . ' FROM postal_codes p JOIN communes ON communes.code=p.code'
                    . ' WHERE p.postal_code=? ORDER BY communes.name, communes.code LIMIT ? OFFSET ?');
                $q->bindValue(1, $term);
                $q->bindValue(2, $limit, \PDO::PARAM_INT);
                $q->bindValue(3, $offset, \PDO::PARAM_INT);
                $q->execute();

                return $q->fetchAll(\PDO::FETCH_ASSOC);
            }
            $postalWhere = $hasPostals
                ? " OR EXISTS (SELECT 1 FROM postal_codes p WHERE p.code=communes.code AND p.postal_code LIKE ? ESCAPE '!')"
                : '';
            $postalSelect = $hasPostals
                ? ", (SELECT GROUP_CONCAT(postal_code, ', ') FROM postal_codes p WHERE p.code=communes.code) AS postalCodes"
                : ", '' AS postalCodes";
            $sql = 'SELECT code, name, department_name AS department, region_name AS region'
                . $postalSelect
                . " FROM communes WHERE search_name LIKE ? ESCAPE '!' OR code LIKE ? ESCAPE '!'"
                . $postalWhere
                . ' ORDER BY CASE WHEN code=? THEN 0 WHEN search_name=? THEN 1'
                . " WHEN search_name LIKE ? ESCAPE '!' THEN 2 ELSE 3 END, name, code LIMIT ? OFFSET ?";
            $params = ['%' . $escaped . '%', '%' . $escaped . '%'];
            if ($hasPostals) {
                $params[] = $escaped . '%';
            }
            array_push($params, strtoupper($term), $escaped, $escaped . '%');
            $stmt = $db->prepare($sql);
            $index = 1;
            foreach ($params as $value) {
                $stmt->bindValue($index++, $value);
            }
            $stmt->bindValue($index++, $limit, \PDO::PARAM_INT);
            $stmt->bindValue($index, $offset, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        if (preg_match('/\A[0-9]{5}\z/D', $term)) {
            return []; // Postal search is unavailable without the official crosswalk.
        }
        if (preg_match('/\A(?:INSEE|COG)\s*:\s*((?:[0-9]{5}|2[AB][0-9]{3}))\z/iD', $term, $match)) {
            $term = strtoupper($match[1]);
        }

        return array_slice(array_values(array_filter(array_map(static fn (array $r): array => [
            'code' => $r['code'], 'name' => $r['name'], 'department' => $r['department'],
            'region' => $r['region'], 'postalCodes' => '',
        ], $this->pilotList()), static fn (array $r): bool => stripos($r['name'] . ' ' . $r['code'], $term) !== false)), $offset, $limit);
    }

    public function regions(): array
    {
        if (($db = $this->connection()) !== null) {
            return $db->query('SELECT region_code AS code, region_name AS name, COUNT(*) AS count FROM communes GROUP BY region_code,region_name ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC);
        }
        $regions = [];
        foreach ($this->pilotList() as $city) {
            $k = $city['regionCode'];
            $regions[$k] ??= ['code' => $k, 'name' => $city['region'], 'count' => 0];
            ++$regions[$k]['count'];
        }

        return array_values($regions);
    }

    public function departments(string $region): array
    {
        if (!preg_match('/\A[0-9A-Z]{1,3}\z/D', $region)) {
            return [];
        }
        if (($db = $this->connection()) !== null) {
            $q = $db->prepare('SELECT department_code AS code, department_name AS name, COUNT(*) AS count FROM communes WHERE region_code = ? GROUP BY department_code,department_name ORDER BY name');
            $q->execute([$region]);

            return $q->fetchAll(\PDO::FETCH_ASSOC);
        }
        $rows = [];
        foreach ($this->pilotList() as $city) {
            if ($city['regionCode'] === $region) {
                $key = $city['departmentCode'];
                $rows[$key] ??= ['code' => $key, 'name' => $city['department'], 'count' => 0];
                ++$rows[$key]['count'];
            }
        }

        return array_values($rows);
    }

    public function cityPage(string $region, string $department, int $offset, int $limit = 40): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, min(50000, $offset));
        if (!preg_match('/\A[0-9A-Z]{1,3}\z/D', $region) || !preg_match('/\A[0-9A-Z]{1,3}\z/D', $department)) {
            return ['rows' => [], 'total' => 0];
        }
        if (($db = $this->connection()) !== null) {
            $count = $db->prepare('SELECT COUNT(*) FROM communes WHERE region_code = ? AND department_code = ?');
            $count->execute([$region, $department]);
            $q = $db->prepare('SELECT code, name FROM communes WHERE region_code=? AND department_code=? ORDER BY name,code LIMIT ? OFFSET ?');
            $q->bindValue(1, $region);
            $q->bindValue(2, $department);
            $q->bindValue(3, $limit, \PDO::PARAM_INT);
            $q->bindValue(4, $offset, \PDO::PARAM_INT);
            $q->execute();

            return ['rows' => $q->fetchAll(\PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
        }
        $rows = [];
        foreach ($this->pilotList() as $city) {
            if ($city['regionCode'] === $region && $city['departmentCode'] === $department) {
                $rows[] = ['code' => $city['code'], 'name' => $city['name']];
            }
        }

        return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
    }
}
