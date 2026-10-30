<?php

declare(strict_types=1);

namespace App\Service;

/** Read-only public context. These measurements are never outcomes of fictional experiments. */
final class PublicEvidence implements \Symfony\Contracts\Service\ResetInterface
{
    private ?array $dataset = null;

    public function reset(): void
    {
        $this->dataset = null;
    }

    public function __construct(private string $projectDir)
    {
    }

    public function compare(string $indicator = ''): array
    {
        $path =
            $this->projectDir .
            '/data/research/ssmsi-2024-2025-three-cities.json';
        if (!is_file($path)) {
            return ['available' => false, 'indicators' => [], 'cities' => []];
        }
        $dataset = $this->dataset ??= json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $indicators = array_values(
            array_unique(array_column($dataset['rows'], 'indicateur')),
        );
        sort($indicators);
        $indicator = in_array($indicator, $indicators, true)
            ? $indicator
            : 'Violences physiques hors cadre familial';
        $cities = [];
        foreach (
            ['75056' => 'Paris', '13055' => 'Marseille', '76351' => 'Le Havre'] as $code => $name
        ) {
            $years = [];
            foreach ($dataset['rows'] as $row) {
                if (
                    $row['CODGEO_2026'] !== (string) $code
                    || $row['indicateur'] !== $indicator
                ) {
                    continue;
                }
                $rate = str_replace(',', '.', $row['taux_pour_mille']);
                // A suppressed, missing or nonnumeric value must never become zero.
                if ($row['est_diffuse'] !== 'diff' || !is_numeric($rate)) {
                    continue;
                }
                $years[$row['annee']] = [
                    'rate' => (float) $rate,
                    'count' => is_numeric($row['nombre'])
                        ? (int) $row['nombre']
                        : null,
                    'countUnit' => $row['unite_de_compte'],
                    'populationVintage' => $row['insee_pop_millesime'],
                    'housingVintage' => $row['insee_log_millesime'],
                ];
            }
            $change = isset($years[2024], $years[2025])
                ? $years[2025]['rate'] - $years[2024]['rate']
                : null;
            $cities[] = [
                'name' => $name,
                'code' => $code,
                'years' => $years,
                'change' => $change,
            ];
        }

        $comparisons = [];
        foreach ($cities as $index => $first) {
            foreach (array_slice($cities, $index + 1) as $second) {
                $firstDirection =
                    $first['change'] === null ? null : $first['change'] <=> 0;
                $secondDirection =
                    $second['change'] === null ? null : $second['change'] <=> 0;
                $comparisons[] = [
                    'first' => $first['name'],
                    'second' => $second['name'],
                    'status' => $firstDirection === null || $secondDirection === null
                            ? 'Comparaison indisponible'
                            : ($firstDirection === $secondDirection
                                ? 'Évolutions de même sens'
                                : 'Évolutions de sens différents'),
                ];
            }
        }

        return [
            'comparisons' => $comparisons,
            'available' => true,
            'indicator' => $indicator,
            'indicators' => $indicators,
            'denominator' => $indicator === 'Cambriolages de logement'
                    ? 'logements'
                    : 'habitants',
            'cities' => $cities,
            'publisher' => $dataset['publisher'],
            'source' => $dataset['source'],
            'accessedAt' => $dataset['accessedAt'],
            'publishedAt' => $dataset['publishedAt'],
            'sourceSha256' => $dataset['sourceSha256'],
        ];
    }
}
