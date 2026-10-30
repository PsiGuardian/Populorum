<?php

declare(strict_types=1);

namespace App\Service;

/** Real accounts are read-only. Scenarios do not alter tax law or erase public obligations. */
final class PublicBudget implements \Symfony\Contracts\Service\ResetInterface
{
    private ?array $aggregates = null;
    private ?array $balances = null;

    public function reset(): void
    {
        $this->aggregates = $this->balances = null;
    }

    public function __construct(private string $projectDir)
    {
    }

    public function dataset(): array
    {
        if ($this->aggregates !== null) {
            return $this->aggregates;
        }
        $file =
            $this->projectDir . '/data/research/municipal-finance-2025.json';

        return $this->aggregates = is_file($file)
            ? json_decode(
                file_get_contents($file),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )
            : [
                'rows' => [],
                'coverage' => 'Source financière indisponible. Aucun montant inventé.',
            ];
    }

    public function ledger(string $code, string $prefix, int $page): array
    {
        $sirens = [
            '75056' => '217500016',
            '13055' => '211300553',
            '76351' => '217603513',
        ];
        if (
            !isset($sirens[$code])
            || !preg_match('/^\d{0,8}$/D', $prefix)
            || $page < 1
            || $page > 10000
        ) {
            throw new \DomainException('Filtre de balance invalide.', 400);
        }
        $path =
            $this->projectDir .
            '/data/research/municipal-balances-2025.json.gz';
        if (!is_file($path)) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => $page,
                'available' => false,
            ];
        }
        $indexed = $this->indexedLedger($path, $sirens[$code], $prefix, $page);
        if ($indexed !== null) {
            return $indexed;
        }
        $dataset = $this->balances ??= json_decode(
            gzdecode(file_get_contents($path)),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $rows = array_values(
            array_filter(
                $dataset['rows'],
                static fn (array $row): bool => $row['siren'] ===
                    $sirens[$code] && str_starts_with($row['compte'], $prefix),
            ),
        );
        usort(
            $rows,
            static fn (array $a, array $b): int => [
                $a['lbudg'],
                $a['compte'],
            ] <=> [$b['lbudg'], $b['compte']],
        );

        return [
            'rows' => array_slice($rows, ($page - 1) * 50, 50),
            'total' => count($rows),
            'page' => $page,
            'available' => true,
            'source' => $dataset['source'],
            'retrievedAt' => $dataset['retrievedAt'],
        ];
    }

    private function indexedLedger(
        string $source,
        string $siren,
        string $prefix,
        int $page,
    ): ?array {
        $path =
            $this->projectDir . '/data/research/public-balances-index.sqlite';
        if (!is_file($path)) {
            return null;
        }
        try {
            $pdo = new \PDO(
                'sqlite:' . $path,
                options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
            );
            $pdo->exec('PRAGMA query_only=ON');
            $metadata = $pdo
                ->query('SELECT source_hash, data FROM metadata WHERE id = 1')
                ->fetch(\PDO::FETCH_ASSOC);
            if (
                !$metadata
                || !hash_equals(
                    $metadata['source_hash'],
                    hash_file('sha256', $source),
                )
            ) {
                return null;
            }
            $count = $pdo->prepare(
                'SELECT COUNT(*) FROM balances WHERE siren = ? AND account LIKE ?',
            );
            $count->execute([$siren, $prefix . '%']);
            $query = $pdo->prepare(
                'SELECT payload FROM balances WHERE siren = ? AND account LIKE ? ORDER BY budget, account_number, ordinal LIMIT 50 OFFSET ?',
            );
            $query->bindValue(1, $siren);
            $query->bindValue(2, $prefix . '%');
            $query->bindValue(3, ($page - 1) * 50, \PDO::PARAM_INT);
            $query->execute();
            $metadata = json_decode(
                $metadata['data'],
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            return [
                'rows' => array_map(
                    static fn (string $json): array => json_decode(
                        $json,
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    ),
                    $query->fetchAll(\PDO::FETCH_COLUMN),
                ),
                'total' => (int) $count->fetchColumn(),
                'page' => $page,
                'available' => true,
                'source' => $metadata['source'],
                'retrievedAt' => $metadata['retrievedAt'],
            ];
        } catch (\PDOException|\JsonException) {
            // A missing, stale or damaged derived index never replaces the official source.
            return null;
        }
    }

    public function cities(array $state): array
    {
        $dataset = $this->dataset();
        $cities = [];
        foreach ($state['territories'] as $city) {
            $rows = array_values(
                array_filter(
                    $dataset['rows'],
                    static fn (array $row): bool => $row['com_code'] ===
                        $city['code'],
                ),
            );
            $amounts = [];
            foreach ($rows as $row) {
                $amounts[$row['agregat']] = (int) round($row['montant'] * 100);
            }
            $population = $rows[0]['ptot'] ?? null;
            $cities[] = [
                'city' => $city,
                'rows' => $rows,
                'amounts' => $amounts,
                'population' => $population,
                'targetCents' => $population !== null ? $population * 100 : null,
                'totalCents' => $amounts['Dépenses totales'] ?? null,
                'protectedCents' => ($amounts['Frais de personnel'] ?? 0) +
                    ($amounts['Charges financières'] ?? 0) +
                    ($amounts["Remboursements d'emprunts hors GAD"] ?? 0),
                'coverage' => $dataset['coverage'],
            ];
        }

        return $cities;
    }

    public function scenario(
        array $city,
        int $savingsPercent,
        int $otherResourcesCents,
    ): array {
        if (
            $savingsPercent < 0
            || $savingsPercent > 100
            || $otherResourcesCents < 0
            || $otherResourcesCents > 10000000000000
        ) {
            throw new \DomainException('Hypothèses budgétaires invalides.', 400);
        }
        if ($city['totalCents'] === null || !$city['population']) {
            return ['available' => false];
        }
        $adjustable = max(0, $city['totalCents'] - $city['protectedCents']);
        $savings = intdiv($adjustable * $savingsPercent, 100);
        $residual = $city['totalCents'] - $savings;
        $localContribution = max(0, $residual - $otherResourcesCents);

        return [
            'available' => true,
            'savingsCents' => $savings,
            'residualCents' => $residual,
            'resourcesCents' => $otherResourcesCents,
            'localContributionCents' => $localContribution,
            'perResidentCents' => $localContribution / $city['population'],
            'gapCents' => max(0, $localContribution - $city['targetCents']),
            'targetWithinModel' => $localContribution <= $city['targetCents'],
            'validatedFeasible' => false,
        ];
    }
}
