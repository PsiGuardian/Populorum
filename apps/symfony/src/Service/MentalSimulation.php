<?php

declare(strict_types=1);

namespace App\Service;

/** Orchestration d’un laboratoire fictif ; aucun résultat de psychologie ou sondage réel. */
final class MentalSimulation
{
    public const MAX_ROUND = 8;

    public function __construct(
        private FictionalMind $model = new FictionalMind(),
    ) {
    }

    public function initialize(array $state): array
    {
        foreach ($state['citizens'] as &$citizen) {
            $citizen['mind'] = $this->model->create($citizen['id']);
            $citizen['mindHistory'] = [
                [
                    'round' => 0,
                    'profile' => $citizen['mind'],
                    'trigger' => 'Première lecture, avant les retours fictifs.',
                ],
            ];
        }
        unset($citizen);
        $state['mentalSimulation'] = [
            'model' => FictionalMind::VERSION,
            'round' => 0,
            'maxRound' => self::MAX_ROUND,
            'rounds' => [
                [
                    'round' => 0,
                    'feedback' => [],
                    'notice' => 'Premières attentes ; aucun résultat de pilote.',
                ],
            ],
        ];
        $state['votes'] = [];
        $this->writeBallots($state, 0, true);
        $this->advance($state, true);
        $this->advance($state, true);

        return $state;
    }

    /** Les votes clôturés et les choix manuels restent intacts ; un tour est une unique mutation auditée. */
    public function advance(array &$state, bool $fixtureClock = false): string
    {
        if (
            ($state['mentalSimulation']['model'] ?? '') !==
            FictionalMind::VERSION
        ) {
            throw new \DomainException('Ouvrez un laboratoire utilisant les profils narratifs actuels.', 409);
        }
        $round = $state['mentalSimulation']['round'] + 1;
        if ($round > self::MAX_ROUND) {
            throw new \DomainException('Fin des huit tours de fiction ; ouvrez un autre laboratoire pour rejouer.', 409);
        }
        $feedback = [];
        foreach ($state['proposals'] as $proposal) {
            $feedback[$proposal['id']] = $this->model->feedback(
                $proposal,
                $round,
            );
        }
        foreach ($state['citizens'] as &$citizen) {
            if (!isset($citizen['mind'])) {
                continue;
            }
            $local = [];
            foreach ($state['proposals'] as $proposal) {
                if ($proposal['territoryId'] === $citizen['territoryId']) {
                    $local[$proposal['id']] = $feedback[$proposal['id']];
                }
            }
            $citizen['mind'] = $this->model->evolve(
                $citizen['mind'],
                $local,
                $round,
            );
            $citizen['mindHistory'][] = [
                'round' => $round,
                'profile' => $citizen['mind'],
                'trigger' => 'Retours fictifs contradictoires, coûts et imprévus ; aucune donnée de terrain.',
            ];
        }
        unset($citizen);
        $state['mentalSimulation']['round'] = $round;
        $state['mentalSimulation']['rounds'][] = [
            'round' => $round,
            'feedback' => $feedback,
            'notice' => 'Contre-factuels inventés pour éprouver les raisonnements, pas des résultats municipaux.',
        ];
        $this->writeBallots($state, $round, $fixtureClock);

        return 'mental_round_' . $round;
    }

    private function writeBallots(
        array &$state,
        int $round,
        bool $fixtureClock,
    ): void {
        $citizens = array_column($state['citizens'], null, 'id');
        $voteIndexes = [];
        foreach ($state['votes'] as $index => $vote) {
            $voteIndexes[
                $vote['consultationId'] . '/' . $vote['citizenId']
            ] = $index;
        }
        foreach ($state['consultations'] as $consultation) {
            if (
                $consultation['status'] !== 'open'
                || strtotime($consultation['closesAt']) <= time()
            ) {
                continue;
            }
            foreach ($consultation['eligibleIds'] as $id) {
                $citizen = $citizens[$id];
                if (
                    !isset($citizen['mind'])
                    || !$citizen['consent']
                    || $citizen['suspended']
                    || $citizen['territoryId'] !== $consultation['territoryId']
                ) {
                    continue;
                }
                $key = $consultation['id'] . '/' . $id;
                $index = $voteIndexes[$key] ?? null;
                if (
                    $index !== null
                    && !isset($state['votes'][$index]['deliberation'])
                ) {
                    continue;
                }
                $decision = $this->model->deliberate(
                    $citizen,
                    $consultation['frozen'],
                    $citizen['mind'],
                    $round,
                );
                $witness = [
                    'model' => FictionalMind::VERSION,
                    'profileRevision' => $citizen['mind']['revision'],
                    'round' => $round,
                    'digest' => $this->model->digest($decision),
                ];
                // Horloge pédagogique stable, indépendante de la date d’exécution du générateur.
                $at = $fixtureClock
                    ? '2026-10-' . (10 + $round) . 'T12:00:00Z'
                    : gmdate('c');
                if ($index === null) {
                    $voteIndexes[$key] = count($state['votes']);
                    $state['votes'][] = [
                        'id' => 'mv_' . $consultation['id'] . '_' . $id,
                        'consultationId' => $consultation['id'],
                        'citizenId' => $id,
                        'choice' => $decision['choice'],
                        'at' => $at,
                        'history' => [],
                        'deliberation' => $witness,
                        'simulated' => true,
                    ];
                    continue;
                }
                $vote = &$state['votes'][$index];
                $vote['history'][] = [
                    'choice' => $vote['choice'],
                    'at' => $vote['at'],
                    'deliberation' => $vote['deliberation'],
                ];
                $vote['choice'] = $decision['choice'];
                $vote['at'] = $at;
                $vote['deliberation'] = $witness;
                unset($vote);
            }
        }
    }

    public function dossier(
        array $state,
        array $vote,
        ?int $round = null,
    ): array {
        $witness = $vote['deliberation'] ?? null;
        if ($round !== null && ($witness['round'] ?? null) !== $round) {
            $witness = null;
            foreach ($vote['history'] as $previous) {
                if (($previous['deliberation']['round'] ?? null) === $round) {
                    $witness = $previous['deliberation'];
                    break;
                }
            }
        }
        if (!$witness || $witness['model'] !== FictionalMind::VERSION) {
            throw new \DomainException('Aucun raisonnement automatique pour ce vote ou ce tour.', 404);
        }
        $citizen = array_column($state['citizens'], null, 'id')[
            $vote['citizenId']
        ];
        $consultation = array_column($state['consultations'], null, 'id')[
            $vote['consultationId']
        ];
        $mind = null;
        foreach ($citizen['mindHistory'] as $entry) {
            if ($entry['profile']['revision'] === $witness['profileRevision']) {
                $mind = $entry['profile'];
                break;
            }
        }
        if (!$mind) {
            throw new \DomainException('Version du profil introuvable.', 409);
        }
        $decision = $this->model->deliberate(
            $citizen,
            $consultation['frozen'],
            $mind,
            $witness['round'],
        );
        if (!hash_equals($witness['digest'], $this->model->digest($decision))) {
            throw new \DomainException('Le raisonnement régénéré ne correspond pas à son empreinte figée.', 409);
        }

        return $decision;
    }

    public function voice(array $citizen): array
    {
        return $this->model->voice($citizen['mind']);
    }
}
