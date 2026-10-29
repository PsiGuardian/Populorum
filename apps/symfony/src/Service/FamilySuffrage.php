<?php

declare(strict_types=1);

namespace App\Service;

/** Suffrage familial expérimental : opinions individuelles distinctes du poids d’adoption. */
final class FamilySuffrage
{
    public const VERSION = 'family-majority-1';

    /** Le rattachement déclaré est figé à l’ouverture, jamais déduit du nom. */
    public function freeze(
        array $citizens,
        array $eligibleIds,
        string $territoryId,
    ): array {
        $eligible = array_fill_keys($eligibleIds, true);
        $families = [];
        foreach ($citizens as $citizen) {
            if (
                isset($eligible[$citizen['id']])
                && $citizen['territoryId'] === $territoryId
                && !empty($citizen['familyId'])
            ) {
                $families[$citizen['familyId']][] = $citizen['id'];
            }
        }
        ksort($families);

        return $families;
    }

    /** Chaque bulletin personnel reste visible ; seule la décision agrégée de chaque famille compte. */
    public function count(array $consultation, array $votes): array
    {
        if (
            ($consultation['rules']['familyAggregation'] ?? '') !==
            self::VERSION
        ) {
            throw new \DomainException('Version de la règle familiale inconnue.', 409);
        }
        $membership = [];
        $families = [];
        foreach (
            $consultation['eligibleFamilies'] ?? [] as $familyId => $members
        ) {
            if (
                !$members
                || count($members) !== count(array_unique($members))
            ) {
                throw new \DomainException('Famille vide ou membres dupliqués.', 409);
            }
            foreach ($members as $citizenId) {
                if (
                    isset($membership[$citizenId])
                    || !in_array($citizenId, $consultation['eligibleIds'], true)
                ) {
                    throw new \DomainException('Un participant ne peut appartenir qu’à une famille admissible.', 409);
                }
                $membership[$citizenId] = $familyId;
            }
            $families[$familyId] = [
                'members' => $members,
                'pour' => 0,
                'contre' => 0,
                'abstention' => 0,
                'participants' => 0,
                'choice' => null,
            ];
        }
        $individual = [
            'pour' => 0,
            'contre' => 0,
            'abstention' => 0,
            'total' => 0,
            'unassigned' => 0,
        ];
        $seen = [];
        foreach ($votes as $vote) {
            if ($vote['consultationId'] !== $consultation['id']) {
                continue;
            }
            if (
                isset($seen[$vote['citizenId']])
                || !in_array(
                    $vote['citizenId'],
                    $consultation['eligibleIds'],
                    true,
                )
                || !in_array(
                    $vote['choice'],
                    ['pour', 'contre', 'abstention'],
                    true,
                )
            ) {
                throw new \DomainException('Prise de position invalide ou dupliquée.', 409);
            }
            $seen[$vote['citizenId']] = true;
            ++$individual[$vote['choice']];
            ++$individual['total'];
            $familyId = $membership[$vote['citizenId']] ?? null;
            if ($familyId === null) {
                ++$individual['unassigned'];
                continue;
            }
            ++$families[$familyId][$vote['choice']];
            ++$families[$familyId]['participants'];
        }
        $counts = ['pour' => 0, 'contre' => 0, 'abstention' => 0];
        foreach ($families as &$family) {
            if (!$family['participants']) {
                continue;
            }
            $family['choice'] =
                $family['pour'] > $family['contre']
                    ? 'pour'
                    : ($family['contre'] > $family['pour']
                        ? 'contre'
                        : 'abstention');
            ++$counts[$family['choice']];
        }
        unset($family);

        return [
            'counts' => $counts,
            'eligible' => count($families),
            'families' => $families,
            'individual' => $individual,
        ];
    }
}
