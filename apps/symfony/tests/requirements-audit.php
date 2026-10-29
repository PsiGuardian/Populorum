<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Service\SimulationEngine;

// Sondes de recette intégrées à composer test : les écarts corrigés
// doivent rester satisfaits, sans prétendre couvrir les 68 scénarios complets.
$engine = new SimulationEngine();
$fixture = json_decode(
    file_get_contents(dirname(__DIR__) . '/data/scenario.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$fixture['keyPair'] = null;
$results = [];
$apply = static fn (
    array $state,
    string $type,
    array $payload,
): array => $engine->apply($state, $type, $payload, bin2hex(random_bytes(16)));
$probe = static function (
    string $id,
    string $requirement,
    callable $check,
) use (&$results): void {
    try {
        $passed = $check();
        $detail = $passed
            ? 'Exigence satisfaite sur ce cas.'
            : 'Exigence non satisfaite sur ce cas.';
    } catch (Throwable $exception) {
        $passed = false;
        $detail = $exception::class . ' : ' . $exception->getMessage();
    }
    $results[] = [
        'id' => $id,
        'requirement' => $requirement,
        'passed' => $passed,
        'detail' => $detail,
    ];
};
$proposalPayload = static function (array $proposal): array {
    $payload = $proposal;
    $payload['citizenId'] = $proposal['authorId'];
    $payload['tags'] = implode(', ', $proposal['tags']);
    $payload['source'] = 'Source fictive pour la recette';

    return $payload;
};

$probe(
    'AUD-01',
    '§21 : enregistrer un brouillon incomplet',
    static function () use ($apply, $fixture): bool {
        $state = $apply($fixture, 'proposal.create', [
            'citizenId' => 'u7',
            'territoryId' => 't3',
            'title' => 'Brouillon encore incomplet',
        ]);

        return end($state['proposals'])['status'] === 'draft';
    },
);
$probe(
    'AUD-02',
    '§16 : ajouter une catégorie causale sans perdre les précédentes',
    static function () use ($apply, $fixture, $proposalPayload): bool {
        $payload = $proposalPayload($fixture['proposals'][2]);
        $payload['nodes'][] = [
            'category' => 'Facteur complémentaire',
            'content' => 'Élément fictif additionnel',
            'kind' => 'hypothesis',
        ];
        $state = $apply($fixture, 'proposal.create', $payload);

        return count(end($state['proposals'])['nodes']) === 13;
    },
);
$probe(
    'AUD-03',
    '§18 : enregistrer des relations causales typées',
    static function () use ($apply, $fixture, $proposalPayload): bool {
        $payload = $proposalPayload($fixture['proposals'][2]);
        $payload['edges'] = [
            ['from' => 'n1', 'to' => 'n0', 'type' => 'cause de'],
        ];
        $state = $apply($fixture, 'proposal.create', $payload);

        return end($state['proposals'])['edges'] === [
            ['from' => 'n1', 'to' => 'n0', 'relation' => 'cause de'],
        ];
    },
);
$probe(
    'AUD-04',
    '§19 : réviser aussi le problème et la cause',
    static function () use ($apply, $fixture): bool {
        $state = $apply($fixture, 'proposal.revise', [
            'citizenId' => 'u7',
            'proposalId' => 'p3',
            'reason' => 'Nouvelles observations fictives',
            'problem' => 'Problème fictif réexaminé',
            'cause' => 'Cause fictive réexaminée',
        ]);

        return $state['proposals'][2]['problem'] ===
            'Problème fictif réexaminé'
            && $state['proposals'][2]['cause'] === 'Cause fictive réexaminée';
    },
);
$probe(
    'AUD-05',
    '§13 : effacer un nom jusque dans les exports de journal',
    static function () use ($apply, $fixture, $engine): bool {
        $name = 'SENTINELLE-NOM-FICTIF-A-EFFACER';
        $state = $apply($fixture, 'citizen.update', [
            'citizenId' => 'u1',
            'name' => $name,
        ]);
        $state = $apply($state, 'citizen.erase', ['citizenId' => 'u1']);

        return !str_contains(json_encode($engine->publicState($state)), $name);
    },
);
$probe(
    'AUD-06',
    '§33 et §40 : conserver les versions des bilans',
    static function () use ($apply, $fixture): bool {
        $payload = [
            'citizenId' => 'u4',
            'experimentId' => 'e1',
            'summary' => 'Premier bilan fictif documenté',
            'decision' => 'stop',
        ];
        $state = $apply($fixture, 'experiment.evaluate', $payload);
        $payload['summary'] = 'Second bilan fictif différent';
        try {
            $next = $apply($state, 'experiment.evaluate', $payload);
        } catch (DomainException) {
            return true;
        }

        return str_contains(
            json_encode($next['experiments'][0], JSON_UNESCAPED_UNICODE),
            'Premier bilan fictif documenté',
        );
    },
);
$probe(
    'AUD-07',
    '§28 : échéance distincte de la proclamation définitive',
    static function () use ($fixture, $engine): bool {
        $consultation = $fixture['consultations'][0];
        $consultation['closesAt'] = '2000-01-01T00:00:00Z';

        $result = $engine->tally($consultation, $fixture['votes']);

        return !$result['provisional']
            && $result['phase'] === 'expired'
            && $result['awaitingProclamation'];
    },
);
$probe(
    'AUD-08',
    'DK-036 : recalcul indépendant identique',
    static function () use ($fixture, $engine): bool {
        $consultation = $fixture['consultations'][1];
        $consultation['votes'] = array_values(
            array_filter(
                $fixture['votes'],
                static fn (array $vote): bool => $vote['consultationId'] ===
                    $consultation['id'],
            ),
        );
        $exportPath = tempnam(sys_get_temp_dir(), 'populorum-recount-');
        try {
            file_put_contents(
                $exportPath,
                json_encode($consultation, JSON_THROW_ON_ERROR),
            );
            $command = [
                PHP_BINARY,
                '-c',
                php_ini_loaded_file(),
                dirname(__DIR__) . '/tools/recount.php',
                $exportPath,
            ];
            $process = proc_open(
                $command,
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Impossible de démarrer le vérificateur indépendant.');
            }
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException($error);
            }
            $independent = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $expected = $engine->tally($consultation, $fixture['votes']);
            foreach ($independent as $field => $value) {
                if ($value != $expected[$field]) {
                    return false;
                }
            }

            return true;
        } finally {
            unlink($exportPath);
        }
    },
);
$probe(
    'AUD-09',
    'DK-006 : reprise locale sans consultation automatique',
    static function () use ($fixture, $apply): bool {
        $state = $apply($fixture, 'territory.adapt', [
            'citizenId' => 'u7',
            'proposalId' => 'p1',
            'territoryId' => 't3',
            'type' => 'adaptation',
            'reason' => 'Contexte fictif différent',
            'characteristic' => 'Contexte portuaire fictif',
            'source' => 'Source pédagogique fictive',
            'alternative' => 'Navette locale fictive adaptée',
            'review' => 'Après trois mois simulés',
        ]);
        $adapted = end($state['proposals']);

        return $adapted['originId'] === 'p1'
            && $adapted['territoryId'] === 't3'
            && $adapted['status'] === 'draft'
            && count($state['consultations']) === count($fixture['consultations']);
    },
);
$probe(
    'AUD-10',
    'DK-051 : signalement d’intégrité non confirmé par défaut',
    static function () use ($fixture, $apply): bool {
        $state = $apply($fixture, 'integrity.alert', [
            'citizenId' => 'u1',
            'reason' => 'Divergence fictive à vérifier',
        ]);

        return end($state['alerts'])['status'] === 'to_verify';
    },
);
$probe(
    'AUD-11',
    'DK-055 : refuser une signature supprimée',
    static function () use ($fixture, $apply, $engine): bool {
        $state = $engine->publicState(
            $apply($fixture, 'citizen.withdraw', ['citizenId' => 'u1']),
        );
        $state['events'][0]['signature'] = '';

        return !$engine->verify($state)['valid'];
    },
);
$probe(
    'AUD-12',
    '§24 : conserver le texte figé après une révision postérieure',
    static function () use ($fixture, $apply): bool {
        $frozen = $fixture['consultations'][1]['frozen'];
        $state = $apply($fixture, 'proposal.revise', [
            'citizenId' => 'u4',
            'proposalId' => 'p2',
            'reason' => 'Nouvelle hypothèse fictive',
            'solution' => 'Solution fictive révisée',
        ]);

        return $state['consultations'][1]['frozen'] === $frozen
            && $state['proposals'][1]['version'] === 2;
    },
);

$failed = count(
    array_filter(
        $results,
        static fn (array $result): bool => !$result['passed'],
    ),
);
echo json_encode(
    [
        'date' => '2026-10-29',
        'scope' => 'Sondes complémentaires ; ne couvre pas les 68 scénarios complets.',
        'checks' => count($results),
        'failed' => $failed,
        'results' => $results,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . "\n";
exit($failed > 0 ? 1 : 0);
