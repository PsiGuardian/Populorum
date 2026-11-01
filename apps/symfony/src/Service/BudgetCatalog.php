<?php

declare(strict_types=1);

namespace App\Service;

/** Read-only geographical index, populated from the OFGL consolidated municipal accounts. */
final class BudgetCatalog
{
    private ?\PDO $connection = null;
    private bool $attempted = false;

    public function __construct(private readonly string $projectDir, private readonly PublicBudget $publicBudget, private readonly GeographyCatalog $geography)
    {
    }

    private function db(): ?\PDO
    {
        if ($this->attempted) {
            return $this->connection;
        }
        $this->attempted = true;
        $file = $this->projectDir . '/data/research/municipal-national-2025.sqlite';
        if (!is_file($file)) {
            return null;
        }
        try {
            $pdo = new \PDO('sqlite:file:' . $file . '?mode=ro', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $pdo->exec('PRAGMA query_only=ON');
            $year = $pdo->query("SELECT value FROM metadata WHERE key = 'year'")->fetchColumn();
            if ($year !== '2025') {
                return null;
            }
            return $this->connection = $pdo;
        } catch (\PDOException) {
            return null;
        }
    }

    private function pilotRows(): array
    {
        $indexed = [];
        foreach ($this->publicBudget->dataset()['rows'] ?? [] as $row) {
            $code = (string) ($row['com_code'] ?? '');
            if (!isset($indexed[$code])) {
                $indexed[$code] = [
                    'code' => $code, 'name' => $row['com_name'] ?? $code,
                    'regionCode' => (string) ($row['reg_code'] ?? ''),
                    'region' => (string) ($row['reg_name'] ?? ''),
                    'departmentCode' => (string) ($row['dep_code'] ?? ''),
                    'department' => (string) ($row['dep_name'] ?? ''),
                    'population' => (int) ($row['ptot'] ?? 0), 'rows' => [],
                ];
            }
            $indexed[$code]['rows'][] = $row;
        }
        return $indexed;
    }

    public function coverage(): array
    {
        $db = $this->db();
        if ($db !== null) {
            return [
                'kind' => 'national_import',
                'cities' => (int) $db->query('SELECT COUNT(*) FROM municipalities')->fetchColumn(),
                'year' => '2025',
                'label' => 'Communes recensées dans l’import OFGL 2025 (périmètre communal uniquement, couverture éventuellement incomplète).',
            ];
        }
        return [
            'kind' => 'pilot', 'cities' => count($this->pilotRows()), 'year' => '2025',
            'label' => 'Extrait pilote : seuls Paris, Marseille et Le Havre sont chargés. Aucune somme nationale disponible.',
        ];
    }

    public function city(string $code): ?array
    {
        if (!preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $code)) {
            return null;
        }
        $db = $this->db();
        if ($db !== null) {
            $stmt = $db->prepare('SELECT code, name, region_code, region_name, department_code, department_name, population FROM municipalities WHERE code = ?');
            $stmt->execute([$code]);
            $city = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($city !== false) {
                $rowsStmt = $db->prepare('SELECT aggregate, amount_cents, main_cents, annex_cents, cross_cents FROM aggregates WHERE code = ?');
                $rowsStmt->execute([$code]);
                $rows = [];
                $amounts = [];
                foreach ($rowsStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $amounts[$row['aggregate']] = (int) $row['amount_cents'];
                    $rows[] = [
                        'agregat' => $row['aggregate'], 'montant' => $row['amount_cents'] / 100,
                        'montant_bp' => $row['main_cents'] / 100,
                        'montant_ba' => $row['annex_cents'] / 100,
                        'montant_flux' => $row['cross_cents'] / 100, 'exer' => '2025',
                    ];
                }
                return $this->formatCity([
                    'code' => $city['code'], 'name' => $city['name'], 'region' => $city['region_name'],
                    'regionCode' => $city['region_code'], 'departmentCode' => $city['department_code'],
                    'department' => $city['department_name'], 'population' => (int) $city['population'],
                    'rows' => $rows,
                ], $amounts);
            }
        }
        $pilot = $this->pilotRows()[$code] ?? null;
        if ($pilot === null) {
            $geo = $this->geography->find($code);
            if ($geo === null) {
                return null;
            }
            return $this->formatCity([
                'code' => $geo['code'], 'name' => $geo['name'], 'region' => $geo['region'],
                'regionCode' => $geo['regionCode'], 'departmentCode' => $geo['departmentCode'],
                'department' => $geo['department'], 'population' => null, 'rows' => [],
            ], []);
        }
        $amounts = [];
        foreach ($pilot['rows'] as $row) {
            $amounts[$row['agregat']] = (int) round((float) $row['montant'] * 100);
        }
        return $this->formatCity($pilot, $amounts);
    }

    private function formatCity(array $city, array $amounts): array
    {
        $geo = $this->geography->find($city['code']);
        return [
            'city' => ['code' => $city['code'], 'name' => $geo['name'] ?? $city['name'], 'id' => 'ofgl-' . $city['code']],
            'region' => $geo['region'] ?? $city['region'], 'regionCode' => $geo['regionCode'] ?? ($city['regionCode'] ?? ''),
            'departmentCode' => $geo['departmentCode'] ?? $city['departmentCode'], 'department' => $geo['department'] ?? $city['department'],
            'rows' => $city['rows'], 'amounts' => $amounts, 'population' => $city['population'],
            'totalCents' => $amounts['Dépenses totales'] ?? null,
        ];
    }

    /** National figures represent municipal budgets present in the source, not all public spending. */
    public function national(): array
    {
        $db = $this->db();
        $coverage = $this->coverage();
        if ($db === null) {
            return ['available' => false, 'coverage' => $coverage, 'totalCents' => null, 'population' => null, 'rows' => []];
        }
        $query = $db->query('SELECT aggregate, amount_cents AS total, main_cents AS main, annex_cents AS annex, cross_cents AS cross_amount, represented FROM national_aggregate');
        $rows = [];
        $amounts = [];
        foreach ($query->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $amounts[$r['aggregate']] = (int) $r['total'];
            $rows[] = [
                'agregat' => $r['aggregate'], 'montant' => $r['total'] / 100,
                'montant_bp' => $r['main'] / 100, 'montant_ba' => $r['annex'] / 100,
                'montant_flux' => $r['cross_amount'] / 100, 'exer' => '2025',
            ];
        }
        // National population is computed only for municipalities with an expense total.
        $population = (int) $db->query("SELECT SUM(m.population) FROM municipalities m JOIN aggregates a ON a.code=m.code AND a.aggregate='Dépenses totales'")->fetchColumn();
        return [
            'available' => isset($amounts['Dépenses totales']), 'coverage' => $coverage,
            'totalCents' => $amounts['Dépenses totales'] ?? null, 'population' => $population,
            'rows' => $rows, 'amounts' => $amounts,
            'city' => ['code' => 'FR', 'name' => 'Communes recensées en France', 'id' => 'fr'],
        ];
    }

    public function regions(): array
    {
        return $this->geography->regions();
    }

    public function departments(string $region): array
    {
        return $this->geography->departments($region);
    }

    public function cityPage(string $region, string $department, int $offset, int $limit = 40): array
    {
        return $this->geography->cityPage($region, $department, $offset, $limit);
    }

    public function search(string $term): array
    {
        return $this->geography->search($term);
    }
}
