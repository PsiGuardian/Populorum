<?php

declare(strict_types=1);

namespace App\Service;

/** Comparaisons explicites de contenus et d’indicateurs ; aucune donnée de valeur d’une personne. */
final class ConvergenceAnalyzer
{
    private function normalized(string $value): string
    {
        return trim(preg_replace("/\s+/u", ' ', mb_strtolower($value)));
    }

    private function comparison(array $first, array $second): array
    {
        $common = array_values(
            array_intersect($first['tags'], $second['tags']),
        );
        $sameSolution =
            $this->normalized($first['solution']) !== ''
            && $this->normalized($first['solution']) ===
                $this->normalized($second['solution']);
        $sharedIndicator =
            $this->normalized($first['indicator']) !== ''
            && $this->normalized($first['indicator']) ===
                $this->normalized($second['indicator']);
        $status = $sameSolution
            ? 'complète'
            : ($sharedIndicator || count($common) >= 2
                ? 'partielle'
                : ($common
                    ? 'thématique'
                    : 'distincte'));

        return [
            'common' => $common,
            'sharedIndicator' => $sharedIndicator,
            'sameSolution' => $sameSolution,
            'status' => $status,
        ];
    }

    public function analyze(array $state): array
    {
        $comparisons = [];
        foreach ($state['proposals'] as $index => $first) {
            foreach (array_slice($state['proposals'], $index + 1) as $second) {
                $current = $this->comparison($first, $second);
                $dates = [$first['createdAt'], $second['createdAt']];
                foreach (
                    array_merge($first['revisions'], $second['revisions']) as $revision
                ) {
                    $dates[] = $revision['at'];
                }
                sort($dates);
                $history = [];
                foreach (array_unique($dates) as $date) {
                    $pastFirst = $this->versionAt($first, $date);
                    $pastSecond = $this->versionAt($second, $date);
                    if ($pastFirst && $pastSecond) {
                        $history[] =
                            ['at' => $date] +
                            $this->comparison($pastFirst, $pastSecond);
                    }
                }
                $wasClose = (bool) array_filter(
                    $history,
                    static fn (array $point): bool => in_array(
                        $point['status'],
                        ['complète', 'partielle'],
                        true,
                    ),
                );
                $scenario =
                    $wasClose
                    && !in_array(
                        $current['status'],
                        ['complète', 'partielle'],
                        true,
                    )
                        ? 'convergence suivie de divergence'
                        : ($current['status'] === 'complète'
                            ? 'convergence complète'
                            : ($current['status'] === 'partielle'
                                ? 'convergence partielle'
                                : 'absence de convergence'));
                $comparisons[] =
                    [
                        'a' => $first['id'],
                        'b' => $second['id'],
                        'scenario' => $scenario,
                        'history' => $history,
                        'observed' => $this->observedComparison(
                            $state,
                            $first['id'],
                            $second['id'],
                        ),
                        'method' => 'Texte de solution normalisé, thèmes déclarés et indicateur explicite. Une ressemblance n’établit pas une causalité.',
                    ] + $current;
            }
        }

        return $comparisons;
    }

    private function versionAt(array $proposal, string $date): ?array
    {
        if (strtotime($date) < strtotime($proposal['createdAt'])) {
            return null;
        }
        foreach ($proposal['revisions'] as $revision) {
            if (strtotime($date) < strtotime($revision['at'])) {
                return $revision['snapshot'];
            }
        }

        return $proposal;
    }

    private function observedComparison(
        array $state,
        string $firstId,
        string $secondId,
    ): array {
        $experiments = [];
        foreach ($state['experiments'] as $experiment) {
            if (
                in_array(
                    $experiment['proposalId'],
                    [$firstId, $secondId],
                    true,
                )
                && $experiment['observations']
            ) {
                $experiments[$experiment['proposalId']] = $experiment;
            }
        }
        if (count($experiments) !== 2) {
            return [
                'status' => 'preuves insuffisantes',
                'reason' => 'Deux expériences avec observations sont nécessaires.',
            ];
        }
        $first = $experiments[$firstId];
        $second = $experiments[$secondId];
        if (
            $first['unit'] !== $second['unit']
            || $first['indicator'] !== $second['indicator']
            || $first['baseline'] == 0
            || $second['baseline'] == 0
        ) {
            return [
                'status' => 'non comparable',
                'reason' => 'Unités, indicateurs ou états initiaux ne permettent pas cette comparaison.',
            ];
        }
        $firstObservations = $first['observations'];
        $secondObservations = $second['observations'];
        usort(
            $firstObservations,
            static fn ($a, $b) => strcmp($a['date'], $b['date']),
        );
        usort(
            $secondObservations,
            static fn ($a, $b) => strcmp($a['date'], $b['date']),
        );
        $firstLast = end($firstObservations);
        $secondLast = end($secondObservations);
        $firstPct =
            (100 * ($firstLast['value'] - $first['baseline'])) /
            abs($first['baseline']);
        $secondPct =
            (100 * ($secondLast['value'] - $second['baseline'])) /
            abs($second['baseline']);
        $tolerance =
            100 *
            ($firstLast['uncertainty'] / abs($first['baseline']) +
                $secondLast['uncertainty'] / abs($second['baseline']));

        return [
            'status' => abs($firstPct - $secondPct) <= $tolerance
                    ? 'résultats compatibles dans l’incertitude'
                    : 'résultats distincts',
            'firstPct' => $firstPct,
            'secondPct' => $secondPct,
            'tolerancePct' => $tolerance,
            'simulated' => true,
            'reason' => 'Comparaison descriptive ; contextes, périodes et facteurs externes restent à examiner.',
        ];
    }

    public function evaluation(array $experiment, array $proposal): array
    {
        if (!$experiment['observations'] || $experiment['baseline'] == 0) {
            return [
                'classification' => 'non mesurable',
                'reason' => 'Observation ou état initial non nul requis.',
            ];
        }
        $observations = $experiment['observations'];
        usort(
            $observations,
            static fn ($a, $b) => strcmp($a['date'], $b['date']),
        );
        $last = end($observations);
        $forecast =
            $experiment['frozenProposal']['forecast'] ?? $proposal['forecast'];
        $delta =
            (100 * ($last['value'] - $experiment['baseline'])) /
            abs($experiment['baseline']);
        $uncertainty =
            (100 * $last['uncertainty']) / abs($experiment['baseline']);
        $classification =
            abs($forecast - $delta) <= $uncertainty
                ? 'conforme dans l’incertitude'
                : ($forecast * $delta < 0
                    ? 'sens différent'
                    : 'partiellement conforme');

        return [
            'classification' => $classification,
            'forecastPct' => $forecast,
            'observedPct' => $delta,
            'uncertaintyPct' => $uncertainty,
            'source' => $last['source'],
            'date' => $last['date'],
            'unexpected' => $last['unexpected'],
            'simulated' => true,
        ];
    }
}
