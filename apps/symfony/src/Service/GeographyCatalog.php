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

    public static function validCode(string $code): bool
    {
        return preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $code) === 1;
    }

    public function coverage(): array
    {
        $db = $this->connection();
        if ($db === null) {
            return ['available' => false, 'count' => 0, 'year' => 2026, 'postals' => false,
                'label' => 'Référentiel national INSEE non installé : aucune commune de substitution affichée.'];
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
                $row['pilotId'] = null;
                $row['source'] = 'https://www.insee.fr/fr/information/8740222';
                $row['postalCodes'] = $this->postals($db, $code);

                return $row;
            }

            // Never substitute a former pilot when a newer COG no longer lists its code.
            return null;
        }

        return null;
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
            if (preg_match('/\A[0-9]{2,5}\z/D', $term)) {
                if (!$hasPostals) {
                    return []; // Never mislabel an INSEE code as a postal result.
                }
                $q = $db->prepare('SELECT communes.code, communes.name, communes.department_name AS department,'
                    . ' communes.region_name AS region,'
                    // Only matching postal codes are returned to the UI; never mix other postcodes.
                    . ' GROUP_CONCAT(DISTINCT p.postal_code) AS postalCodes'
                    . ' FROM postal_codes p JOIN communes ON communes.code=p.code'
                    . (strlen($term) === 5 ? ' WHERE p.postal_code = ?' : ' WHERE p.postal_code LIKE ?')
                    . ' GROUP BY communes.code ORDER BY communes.name, communes.code LIMIT ? OFFSET ?');
                $q->bindValue(1, strlen($term) === 5 ? $term : $term . '%');
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

        // Sans index officiel, aucun code postal ou INSEE ne doit être inventé.
        return [];
    }

    /**
     * National paginated atlas: the database filters before returning any rows.
     * No aggregation or pairwise comparison of all 34,875 municipalities in PHP.
     *
     * @return array{rows: array, total: int, page: int, pages: int, first: int, last: int}
     */
    public function convergenceDirectory(
        string $term = '',
        string $region = '',
        string $department = '',
        int $page = 1,
    ): array {
        $empty = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'first' => 0, 'last' => 0];
        $db = $this->connection();
        if ($db === null) {
            return $empty;
        }
        $term = trim($term);
        if (strlen($term) > 100
            || ($region !== '' && preg_match('/\A[0-9A-Z]{1,3}\z/D', $region) !== 1)
            || ($department !== '' && preg_match('/\A[0-9A-Z]{1,3}\z/D', $department) !== 1)) {
            return $empty;
        }
        $where = [];
        $bind = [];
        if ($region !== '') {
            $where[] = 'communes.region_code = :region';
            $bind[':region'] = $region;
        }
        if ($department !== '') {
            $where[] = 'communes.department_code = :department';
            $bind[':department'] = $department;
        }
        $postal = preg_match('/\A[0-9]{2,5}\z/D', $term) === 1;
        $postalComparison = strlen($term) === 5 ? '=' : 'LIKE';
        $postalValue = strlen($term) === 5 ? $term : $term . '%';
        if ($postal) {
            if (!$this->hasPostals($db)) {
                return $empty;
            }
            $where[] = 'EXISTS (SELECT 1 FROM postal_codes p WHERE p.code = communes.code AND p.postal_code '
                . $postalComparison . ' :postal)';
            $bind[':postal'] = $postalValue;
        } elseif (preg_match('/\A(?:INSEE|COG)\s*:\s*((?:[0-9]{5}|2[AB][0-9]{3}))\z/iD', $term, $match) === 1) {
            $where[] = 'communes.code = :insee';
            $bind[':insee'] = strtoupper($match[1]);
        } elseif ($term !== '') {
            $folded = strtolower(function_exists('iconv')
                ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $term) ?: $term) : $term);
            $where[] = "communes.search_name LIKE :name ESCAPE '!'";
            $bind[':name'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $folded) . '%';
        }
        $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $totalQuery = $db->prepare('SELECT COUNT(*) FROM communes' . $condition);
        foreach ($bind as $key => $value) {
            $totalQuery->bindValue($key, $value);
        }
        $totalQuery->execute();
        $total = (int) $totalQuery->fetchColumn();
        $size = 36;
        $pages = max(1, (int) ceil($total / $size));
        $page = max(1, min($pages, $page));
        $offset = ($page - 1) * $size;
        $postalCodes = $this->hasPostals($db)
            ? " (SELECT GROUP_CONCAT(postal_code, ', ') FROM postal_codes p WHERE p.code = communes.code"
                . ($postal ? ' AND p.postal_code ' . $postalComparison . ' :shownPostal' : '')
                . ') AS postalCodes'
            : " '' AS postalCodes";
        $sql = 'SELECT communes.code, communes.name, communes.region_name AS region,'
            . ' communes.department_name AS department, communes.department_code AS departmentCode,'
            . $postalCodes . ' FROM communes' . $condition
            . ' ORDER BY communes.name, communes.code LIMIT :limit OFFSET :offset';
        $query = $db->prepare($sql);
        foreach ($bind as $key => $value) {
            $query->bindValue($key, $value);
        }
        if ($postal) {
            $query->bindValue(':shownPostal', $postalValue);
        }
        $query->bindValue(':limit', $size, \PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $query->execute();

        return [
            'rows' => $query->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total, 'page' => $page, 'pages' => $pages,
            'first' => $total ? $offset + 1 : 0,
            'last' => min($offset + $size, $total),
        ];
    }

    public function regions(): array
    {
        if (($db = $this->connection()) !== null) {
            return $db->query('SELECT region_code AS code, region_name AS name, COUNT(*) AS count FROM communes GROUP BY region_code,region_name ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
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

        return [];
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

        return ['rows' => [], 'total' => 0];
    }
}
