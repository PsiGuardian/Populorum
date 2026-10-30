<?php

declare(strict_types=1);

namespace App\Service;

/** Naming is independent of psychological traits and votes; IDs and family memberships never change. */
final class FictionalNames
{
    private const GIVEN = [
        'Alix',
        'Camille',
        'Sacha',
        'Noémie',
        'Jules',
        'Inès',
        'Émile',
        'Léonie',
        'Robin',
        'Adèle',
        'Noé',
        'Louise',
        'Malo',
        'Iris',
        'Simon',
        'Clara',
        'Gabriel',
        'Jeanne',
        'Arthur',
        'Lina',
        'Nino',
        'Alice',
        'Raphaël',
        'Zoé',
        'Léon',
        'Maya',
        'Hugo',
        'Anna',
        'Oscar',
        'Rose',
        'Eliott',
        'Nora',
    ];
    private const FAMILY = [
        'Clairvallon',
        'Brumelac',
        'Rochebrise',
        'Valdorée',
        'Boisaurore',
        'Riveclaire',
        'Montécume',
        'Lunedune',
        'Sablétoile',
        'Chantebrume',
        'Vauxlumière',
        'Aubépinel',
        'Vermeilpré',
        'Lacargile',
        'Boisgalet',
        'Belleondée',
        'Dunefleur',
        'Clairhorizon',
        'Rosevallée',
        'Montserein',
        'Riveambrée',
        'Valécorce',
        'Chantemerle',
        'Ormeazur',
        'Préaurore',
        'Rochecendre',
        'Ventsaule',
        'Lysrivage',
        'Aulneclair',
        'Nacrelune',
    ];

    public function identity(
        int $cityIndex,
        int $familyNumber,
        int $position,
    ): array {
        // Territory suffix keeps unrelated declared families distinguishable.
        $suffix = ['des Rives', 'des Collines', 'de l’Estuaire'][
            $cityIndex % 3
        ];
        $lastName =
            self::FAMILY[($familyNumber - 1) % count(self::FAMILY)] .
            ' ' .
            $suffix;
        $firstName = self::GIVEN[($position - 1) % count(self::GIVEN)];

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'name' => $firstName . ' ' . $lastName . ' · fictif',
            'familyLabel' => 'Famille ' . $lastName . ' · fictive',
        ];
    }
}
