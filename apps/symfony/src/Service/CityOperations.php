<?php

declare(strict_types=1);

namespace App\Service;

/** Voluntary, fictional coordination; never sends a message or dispatches a real person. */
final class CityOperations
{
    private const ZONES = ['north' => 0, 'center' => 1, 'south' => 2];

    public function candidates(array $state, array $mission): array
    {
        if (
            $mission['status'] !== 'open'
            || EssentialServices::CATALOG[$mission['service']]['regulated']
        ) {
            return [];
        }
        $candidates = [];
        foreach ($state['workers'] ?? [] as $worker) {
            $busy = (bool) array_filter(
                $state['missions'] ?? [],
                static fn (array $item): bool => ($item['workerId'] ?? '') ===
                    $worker['id']
                    && in_array(
                        $item['status'],
                        ['offered', 'accepted', 'in_progress'],
                        true,
                    ),
            );
            if (
                $worker['id'] === $mission['authorId']
                || !$worker['optIn']
                || !$worker['available']
                || $busy
                || PublicProcurement::busy($state, $worker['id'])
                || $worker['territoryId'] !== $mission['territoryId']
                || !in_array($mission['service'], $worker['skills'], true)
                || $worker['capacityHours'] < $mission['hours']
            ) {
                continue;
            }
            $quote =
                $worker['hourlyCents'] * $mission['hours'] +
                $mission['materialsCents'];
            if ($quote > $mission['budgetCents']) {
                continue;
            }
            $candidates[] = $worker + [
                'zoneDistance' => abs(
                    self::ZONES[$worker['zone']] -
                        self::ZONES[$mission['zone']],
                ),
                'quoteCents' => $quote,
            ];
        }
        usort(
            $candidates,
            static fn (array $a, array $b): int => [
                $a['zoneDistance'],
                $a['quoteCents'],
                $a['id'],
            ] <=> [$b['zoneDistance'], $b['quoteCents'], $b['id']],
        );

        return $candidates;
    }

    private function text(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        if (
            !is_string($value)
            || mb_strlen(trim($value)) < 2
            || mb_strlen($value) > 1500
        ) {
            throw new \DomainException('Texte invalide : ' . $key, 400);
        }

        return trim($value);
    }

    private function integer(
        array $payload,
        string $key,
        int $max,
        int $min = 0,
    ): int {
        $value = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new \DomainException('Nombre entier invalide : ' . $key, 400);
        }

        return $value;
    }

    public function apply(
        array &$state,
        string $action,
        array $payload,
        array $actor,
    ): string {
        $state['workers'] ??= new EssentialServices()->fixtures($state);
        $state['missions'] ??= [];
        if ($action === 'worker.profile') {
            if (
                !in_array(
                    $actor['id'],
                    array_column($state['workers'], 'id'),
                    true,
                )
            ) {
                $new = new EssentialServices()->fixtures([
                    'citizens' => [$actor],
                ])[0];
                $new['available'] = false;
                $new['optIn'] = false;
                $state['workers'][] = $new;
            }

            $skills = is_array($payload['skill'] ?? null)
                ? $payload['skill']
                : [$payload['skill'] ?? ''];
            $zone = $payload['zone'] ?? '';
            if (
                !$skills
                || count($skills) > 8
                || count($skills) !== count(array_unique($skills, SORT_REGULAR))
                || !is_string($zone)
                || !isset(self::ZONES[$zone])
            ) {
                throw new \DomainException('Compétences ou zone invalides.', 400);
            }
            foreach ($skills as $skill) {
                if (
                    !is_string($skill)
                    || !isset(EssentialServices::CATALOG[$skill])
                ) {
                    throw new \DomainException('Compétence inconnue.', 400);
                }
            }
            foreach ($state['workers'] as &$worker) {
                if ($worker['id'] === $actor['id']) {
                    $worker['profession'] = $this->text($payload, 'profession');
                    $worker['skills'] = $skills;
                    $worker['zone'] = $zone;
                    $worker['hourlyCents'] = $this->integer(
                        $payload,
                        'hourlyCents',
                        100000,
                        1,
                    );
                    $worker['capacityHours'] = $this->integer(
                        $payload,
                        'capacityHours',
                        40,
                        1,
                    );

                    return $worker['id'];
                }
            }
            throw new \DomainException('Profil introuvable.', 404);
        }
        if ($action === 'worker.availability') {
            foreach ($state['workers'] as &$worker) {
                if ($worker['id'] === $actor['id']) {
                    $worker['available'] =
                        ($payload['available'] ?? false) === true;
                    $worker['optIn'] = ($payload['optIn'] ?? false) === true;

                    return $worker['id'];
                }
            }
            throw new \DomainException('Fiche de travailleur introuvable.', 404);
        }
        if ($action === 'mission.create') {
            if (count($state['missions']) >= 200) {
                throw new \DomainException('Limite pédagogique de missions atteinte.', 429);
            }
            $service = $payload['service'] ?? '';
            $zone = $payload['zone'] ?? '';
            if (
                !isset(EssentialServices::CATALOG[$service], self::ZONES[$zone])
            ) {
                throw new \DomainException('Service ou zone inconnu.', 400);
            }
            $id = 'mission_' . bin2hex(random_bytes(10));
            $state['missions'][] = [
                'id' => $id,
                'authorId' => $actor['id'],
                'territoryId' => $actor['territoryId'],
                'title' => $this->text($payload, 'title'),
                'description' => $this->text($payload, 'description'),
                'service' => $service,
                'zone' => $zone,
                'priority' => $this->integer($payload, 'priority', 4, 1),
                'hours' => $this->integer($payload, 'hours', 40, 1),
                'materialsCents' => $this->integer(
                    $payload,
                    'materialsCents',
                    10000000,
                ),
                'budgetCents' => $this->integer(
                    $payload,
                    'budgetCents',
                    10000000,
                    1,
                ),
                'status' => EssentialServices::CATALOG[$service]['regulated']
                    ? 'authority_required'
                    : 'open',
                'workerId' => null,
                'quoteCents' => null,
                'actualCents' => null,
                'history' => [],
                'fictional' => true,
            ];

            return $id;
        }
        foreach ($state['missions'] as &$mission) {
            if ($mission['id'] !== ($payload['missionId'] ?? '')) {
                continue;
            }
            $before = $mission['status'];
            if ($action === 'mission.offer') {
                if ($mission['authorId'] !== $actor['id']) {
                    throw new \DomainException('Seul le demandeur peut proposer la mission.', 403);
                }
                $candidates = $this->candidates($state, $mission);
                $candidate =
                    array_values(
                        array_filter(
                            $candidates,
                            static fn (array $item): bool => $item['id'] ===
                                ($payload['workerId'] ?? ''),
                        ),
                    )[0] ?? null;
                if (!$candidate) {
                    throw new \DomainException('Compétence, consentement, budget, capacité ou disponibilité incompatibles. Intervention réglementée : service habilité requis.', 409);
                }
                $mission['workerId'] = $candidate['id'];
                $mission['quoteCents'] = $candidate['quoteCents'];
                $mission['status'] = 'offered';
            } elseif ($action === 'mission.respond') {
                if (
                    $mission['workerId'] !== $actor['id']
                    || $before !== 'offered'
                ) {
                    throw new \DomainException('Seul le travailleur sollicité peut répondre à cette proposition.', 403);
                }
                if (($payload['accept'] ?? false) === true) {
                    $worker = array_column($state['workers'], null, 'id')[
                        $actor['id']
                    ];
                    if (
                        !$worker['available']
                        || !$worker['optIn']
                        || !in_array(
                            $mission['service'],
                            $worker['skills'],
                            true,
                        )
                        || $worker['capacityHours'] < $mission['hours']
                    ) {
                        throw new \DomainException('Disponibilité et consentement requis.', 409);
                    }
                    $mission['status'] = 'accepted';
                } else {
                    $mission['status'] = 'open';
                    $mission['workerId'] = null;
                    $mission['quoteCents'] = null;
                }
            } elseif ($action === 'mission.progress') {
                if (
                    $mission['workerId'] !== $actor['id']
                    || !in_array($before, ['accepted', 'in_progress'], true)
                ) {
                    throw new \DomainException('Travailleur affecté et mission acceptée requis.', 403);
                }
                if ($before === 'accepted') {
                    $mission['status'] = 'in_progress';
                } else {
                    $actual = $this->integer(
                        $payload,
                        'actualCents',
                        $mission['budgetCents'],
                    );
                    $mission['actualCents'] = $actual;
                    $mission['status'] = 'completed';
                }
            } elseif ($action === 'mission.cancel') {
                if (
                    !in_array(
                        $actor['id'],
                        [$mission['authorId'], $mission['workerId']],
                        true,
                    )
                    || in_array(
                        $before,
                        ['completed', 'reviewed', 'cancelled'],
                        true,
                    )
                ) {
                    throw new \DomainException('Auteur ou travailleur affecté et mission non close requis.', 403);
                }
                $mission['actualCents'] = $this->integer(
                    $payload,
                    'actualCents',
                    $mission['budgetCents'],
                );
                $mission['status'] = 'cancelled';
            } elseif ($action === 'mission.review') {
                if (
                    $mission['authorId'] !== $actor['id']
                    || $before !== 'completed'
                ) {
                    throw new \DomainException('Demandeur et réalisation déclarée requis.', 403);
                }
                $mission['status'] = 'reviewed';
            } else {
                throw new \DomainException('Action de mission inconnue.', 400);
            }
            $mission['history'][] = [
                'from' => $before,
                'to' => $mission['status'],
                'at' => gmdate('c'),
                'actorId' => $actor['id'],
                'reason' => $this->text($payload, 'reason'),
            ];

            return $mission['id'];
        }
        throw new \DomainException('Mission introuvable.', 404);
    }
}
