<?php

declare(strict_types=1);

namespace App\Service;

/** Synthèse descriptive par commune, sans note ni classement des personnes. */
final class TerritorialReport
{
    public function reports(array $state): array
    {
        $reports = [];
        foreach ($state['territories'] as $territory) {
            $id = $territory['id'];
            $local = static fn (array $item): bool => ($item['territoryId'] ??
                '') ===
                $id;
            $proposals = array_values(
                array_filter($state['proposals'], $local),
            );
            $consultations = array_values(
                array_filter($state['consultations'], $local),
            );
            $experiments = array_values(
                array_filter($state['experiments'], $local),
            );
            $costs = array_values(array_filter($state['finance'], $local));
            $reports[$id] = [
                'territory' => $territory,
                'proposals' => $proposals,
                'consultations' => $consultations,
                'experiments' => $experiments,
                'adaptations' => array_values(
                    array_filter($state['adaptations'], $local),
                ),
                'expenseCents' => array_sum(
                    array_column(
                        array_filter(
                            $costs,
                            static fn (array $entry): bool => $entry['type'] ===
                                'expense',
                        ),
                        'cents',
                    ),
                ),
                'observations' => array_merge(
                    [],
                    ...array_map(
                        static fn (array $experiment): array => array_map(
                            static fn (
                                array $observation,
                            ): array => $observation + [
                                'indicator' => $experiment['indicator'],
                                'unit' => $experiment['unit'],
                                'start' => $experiment['start'],
                                'end' => $experiment['end'],
                            ],
                            $experiment['observations'],
                        ),
                        $experiments,
                    ),
                ),
            ];
        }

        return $reports;
    }
}
