<?php

declare(strict_types=1);

namespace App\Service;

/** Jeu reproductible : les personnages et leurs choix ne représentent aucune population réelle. */
final class MunicipalFixtureBuilder
{
    public function build(string $dataDirectory): array
    {
        $read = static fn (string $file): array => json_decode(
            file_get_contents($dataDirectory . '/' . $file),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $state = $read('reference-scenario.json');
        foreach (
            [
                'citizens',
                'proposals',
                'consultations',
                'votes',
                'experiments',
                'finance',
                'events',
                'operations',
            ] as $collection
        ) {
            $state[$collection] = [];
        }
        $state['preferences']['dataset'] = 'municipal-2026-v3';
        $state['preferences']['votingUnit'] = 'family';
        $state['preferences']['notice'] =
            '300 personnages, choix et résultats entièrement fictifs ; aucun sondage ni effet juridique. Sources publiques datées séparées des hypothèses.';
        $sources = array_column($read('research/sources.json'), null, 'id');
        $definitions = $read('municipal-proposals.json')['proposals'];
        $nextCitizen = 10;
        foreach ($state['territories'] as $cityIndex => $territory) {
            $cityCitizens = [];
            $familyNumber = 0;
            $remaining = 0;
            for ($position = 1; $position <= 100; ++$position) {
                $id =
                    'u' .
                    ($position <= 3
                        ? $cityIndex * 3 + $position
                        : $nextCitizen++);
                if ($remaining === 0) {
                    $remaining = [1, 2, 3, 4, 5, 6, 7][
                        ($familyNumber + $cityIndex) % 7
                    ];
                    ++$familyNumber;
                }
                --$remaining;
                $cityCitizens[] = $id;
                $state['citizens'][] = [
                    ...(new FictionalNames())->identity($cityIndex, $familyNumber, $position),
                    'id' => $id,
                    'territoryId' => $territory['id'],
                    'familyId' => $territory['id'] . '_family_' . $familyNumber,
                    'consent' => true,
                    'role' => [
                        2 => 'conciliator',
                        3 => 'auditor',
                        4 => 'governor',
                        5 => 'conciliator',
                        6 => 'auditor',
                        7 => 'governor',
                    ][$position] ?? 'citizen',
                    'suspended' => false,
                    'fictional' => true,
                    'history' => [],
                ];
            }
            $localDefinitions = array_values(
                array_filter(
                    $definitions,
                    static fn (array $item): bool => $item['city'] ===
                        $territory['name'],
                ),
            );
            foreach ($localDefinitions as $index => $definition) {
                $proposal = $this->proposal(
                    $definition,
                    $sources,
                    $territory['id'],
                    $cityCitizens[$index],
                    $cityIndex * 9 + $index + 1,
                );
                $proposal['status'] = 'consultation';
                $state['consultations'][] = [
                    'id' => 'c' . ($cityIndex * 9 + $index + 1),
                    'proposalId' => $proposal['id'],
                    'territoryId' => $territory['id'],
                    'version' => 1,
                    'frozen' => $proposal,
                    'rules' => [
                        'quorum' => 50,
                        'majority' => 'Majorité stricte des exprimés ; égalité = non adoptée',
                        'changeAllowed' => true,
                        'abstention' => 'Participe au quorum sans suffrage exprimé',
                    ],
                    'eligibleIds' => $cityCitizens,
                    'opensAt' => '2026-10-01T00:00:00Z',
                    'closesAt' => '2027-10-29T00:00:00Z',
                    'status' => 'open',
                    'simulated' => true,
                ];
                $state['proposals'][] = $proposal;
            }
        }
        foreach ($state['consultations'] as &$consultation) {
            $consultation['rules']['votingUnit'] = 'family';
            $consultation['rules']['familyAggregation'] =
                FamilySuffrage::VERSION;
            $consultation['rules']['majority'] =
                'Majorité stricte des familles exprimant POUR ou CONTRE ; égalité = non adoptée';
            $consultation['rules']['abstention'] =
                'Familles abstentionnistes dans le quorum, hors familles exprimées';
            $consultation['eligibleFamilies'] = new FamilySuffrage()->freeze(
                $state['citizens'],
                $consultation['eligibleIds'],
                $consultation['territoryId'],
            );
        }
        unset($consultation);
        $state = new MentalSimulation()->initialize($state);
        // Trois pilotes ne sont ouverts que si les choix narratifs aboutissent réellement à une adoption.
        foreach ([3, 12, 21] as $consultationIndex) {
            $consultation = &$state['consultations'][$consultationIndex];
            $consultation['status'] = 'closed';
            $consultation['closesAt'] = '2026-10-20T00:00:00Z';
            if (
                new SimulationEngine()->tally($consultation, $state['votes'])[
                    'adopted'
                ]
            ) {
                $proposal = &$state['proposals'][$consultationIndex];
                $proposal['status'] = 'experiment';
                $definition = $definitions[$consultationIndex];
                $state['experiments'][] = $this->experiment(
                    $consultation['frozen'],
                    $consultation['id'],
                    count($state['experiments']) + 1,
                    $definition['unit'],
                );
                unset($proposal);
            }
            unset($consultation);
        }
        $state['keyPair'] = null;

        $state['causalAccounts'] = (new AccountFixtures())->build($state);
        $state['workers'] = (new EssentialServices())->fixtures($state);
        $state['missions'] = [];

        return $state;
    }

    private function proposal(
        array $definition,
        array $sources,
        string $territoryId,
        string $authorId,
        int $number,
    ): array {
        $risk =
            'Déplacement du problème, coût disproportionné ou accès inégal : vérifier les effets indésirables avant toute extension.';
        $reversibility =
            'Pilote réversible de six mois ; suspension en cas d’atteinte aux droits ou de risque de sécurité, bilan public contradictoire avant prolongation.';
        $texts = [
            $definition['problem'],
            $definition['cause'],
            'Hypothèse : contraintes d’organisation ou d’accès aux services ; leur importance reste à mesurer.',
            'Cause probable à tester, aucune causalité établie par la seule actualité.',
            $definition['documentedContext'],
            $definition['solution'],
            'Hypothèse de baisse de ' .
            abs($definition['forecast']) .
            ' % de l’indicateur ; objectif pédagogique, aucune efficacité démontrée.',
            $risk,
            'Mesurer « ' .
            $definition['indicator'] .
            ' » avec protocole, dates et incertitude ; aucun résultat réel disponible.',
            'Un report vers d’autres lieux ou publics est possible ; recueil contradictoire à prévoir.',
            $definition['alternative'],
            $reversibility,
        ];
        $nodes = [];
        foreach (SimulationEngine::CATEGORIES as $index => $category) {
            $nodes[] = [
                'id' => 'n' . $index,
                'category' => $category,
                'kind' => $index === 4 ? 'observation' : 'hypothesis',
                'evidenceType' => $index === 4
                        ? $sources[$definition['sourceRefs'][0]]['type']
                        : 'hypothesis',
                'content' => $texts[$index],
            ];
        }

        return [
            'id' => 'p' . $number,
            'title' => $definition['title'],
            'territoryId' => $territoryId,
            'authorId' => $authorId,
            'theme' => $definition['priorityRank'] === 1
                    ? 'Sécurité et prévention'
                    : 'Cadre de vie et services',
            'summary' => $definition['problem'],
            'problem' => $definition['problem'],
            'cause' => $definition['cause'],
            'consequence' => 'Une difficulté d’accès ou une exposition accrue au risque pourrait persister ; hypothèse à vérifier.',
            'solution' => $definition['solution'],
            'justification' => 'Comparer le pilote à une période de référence et discuter les biais. Compétence : ' .
                $definition['scope'],
            'indicator' => $definition['indicator'],
            'risk' => $risk,
            'reversibility' => $reversibility,
            'status' => 'published',
            'version' => 1,
            'tags' => [
                $definition['priorityRank'] === 1 ? 'prévention' : 'services',
                'expérimentation réversible',
            ],
            'forecast' => $definition['forecast'],
            'createdAt' => '2026-10-01T08:00:00Z',
            'sources' => array_map(
                static fn (string $id): array => $sources[$id],
                $definition['sourceRefs'],
            ),
            'nodes' => $nodes,
            'edges' => [
                ['from' => 'n1', 'to' => 'n0', 'relation' => 'contribue à'],
                [
                    'from' => 'n5',
                    'to' => 'n6',
                    'relation' => 'produit potentiellement',
                ],
                [
                    'from' => 'n5',
                    'to' => 'n7',
                    'relation' => 'produit potentiellement',
                ],
                ['from' => 'n10', 'to' => 'n5', 'relation' => 'alternative à'],
            ],
            'revisions' => [],
            'priorityRank' => $definition['priorityRank'],
            'scope' => $definition['scope'],
            'simulated' => true,
        ];
    }

    private function experiment(
        array $proposal,
        string $consultationId,
        int $number,
        string $unit,
    ): array {
        return [
            'id' => 'e' . $number,
            'proposalVersion' => $proposal['version'],
            'frozenProposal' => $proposal,
            'consultationId' => $consultationId,
            'proposalId' => $proposal['id'],
            'territoryId' => $proposal['territoryId'],
            'objective' => $proposal['indicator'],
            'hypothesis' => $proposal['solution'],
            'baseline' => 20,
            'unit' => $unit,
            'indicator' => $proposal['indicator'],
            'start' => '2026-10-21',
            'end' => '2027-04-21',
            'stop' => 'Arrêt immédiat si risque de sécurité ou atteinte aux droits ; bilan contradictoire si coût excessif.',
            'method' => 'Exercice fictif : mêmes créneaux et périmètres avant/après, relevés datés, contrôle des facteurs externes.',
            'limitations' => 'Valeurs inventées uniquement pour tester le logiciel. Aucun effet réel ni représentativité statistique.',
            'status' => 'running',
            'simulated' => true,
            'observations' => array_map(
                static fn (int $i): array => [
                    'id' => 'o' . $number . '_' . $i,
                    'date' => '2026-10-' . (21 + $i),
                    'baseline' => 20,
                    'value' => 20 - $i,
                    'source' => 'Fixture fictive reproductible — aucun relevé de terrain',
                    'uncertainty' => 3,
                    'external' => 'Contexte pédagogique identique',
                    'unexpected' => 'Aucun effet réel mesuré',
                    'simulated' => true,
                ],
                [1, 2],
            ),
        ];
    }
}
