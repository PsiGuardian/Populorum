<?php

declare(strict_types=1);

namespace App\Service;

/** Illustrative accounts only: never mapped to an actual incident or a local crime count. */
final class AccountFixtures
{
    public function build(array $state): array
    {
        $accounts = [];
        foreach ($state['territories'] as $index => $city) {
            $local = array_values(
                array_filter(
                    $state['citizens'],
                    static fn (array $citizen): bool => $citizen[
                        'territoryId'
                    ] === $city['id'],
                ),
            );
            $proposal = array_values(
                array_filter(
                    $state['proposals'],
                    static fn (array $item): bool => $item['territoryId'] ===
                        $city['id'],
                ),
            )[0];
            $initial = [
                'version' => 1,
                'recordedAt' => '2026-10-21T10:00:00Z',
                'year' => '2025',
                'eventDate' => null,
                'eventTime' => null,
                'timeZone' => 'Europe/Paris',
                'timePrecision' => 'year',
                'nature' => 'self_report',
                'disclosure' => 'partial',
                'cause' => 'Dans ce scénario inventé, j’ai abîmé un équipement public après une dispute. Je pensais que ma colère venait uniquement de cet échange ; je n’en connais pas encore tous les facteurs.',
                'consequence' => 'L’équipement est devenu inutilisable dans la fiction. Je souhaite comprendre le passage de ma frustration à mon geste, sans minimiser les effets pour les autres.',
                'uncertainty' => 'Mon souvenir est partiel. L’ordre de certains événements et l’importance de la fatigue restent incertains.',
                'unknownVariables' => 'Des contraintes personnelles ou un malentendu ont pu intervenir. Ce sont des pistes, pas des causes établies.',
                'source' => '',
                'reason' => 'Je commence par ce que le personnage est prêt à raconter.',
            ];
            $addition = array_replace($initial, [
                'version' => 2,
                'recordedAt' => '2026-10-23T15:00:00Z',
                'eventDate' => '2025-05-12',
                'eventTime' => '18:30',
                'timePrecision' => 'minute_declared',
                'disclosure' => 'additional',
                'cause' => 'Le personnage situe maintenant la scène au 12 mai vers 18 h 30. Il se souvient d’avoir mal compris une phrase. Cette précision est déclarée et non vérifiée.',
                'consequence' => 'Je propose une pause et un interlocuteur de médiation comme alternatives à tester dans d’autres scènes fictives.',
                'reason' => 'Un souvenir supplémentaire précise le récit ; il ne remplace pas le premier ajout.',
            ]);
            $correction = array_replace($addition, [
                'version' => 3,
                'recordedAt' => '2026-10-25T11:00:00Z',
                'nature' => 'hypothesis',
                'disclosure' => 'correction',
                'cause' => 'Je corrige mon interprétation : la dispute n’explique peut-être pas tout. Plusieurs facteurs ont pu contribuer au geste. Le même résultat peut être compatible avec des causes différentes.',
                'reason' => 'Distinguer le souvenir raconté de l’explication que j’en tire.',
            ]);
            $accounts[] = [
                'id' => 'account_fixture_' . $index . '_experience',
                'authorId' => $local[7]['id'],
                'territoryId' => $city['id'],
                'proposalId' => $proposal['id'],
                'title' => 'Un geste dommageable : récit partiel puis réexaminé',
                'category' => 'Destructions et dégradations volontaires',
                'kind' => 'experience',
                'fictional' => true,
                'entries' => [$initial, $addition, $correction],
            ];
            $concern = array_replace($initial, [
                'year' => null,
                'timePrecision' => 'unknown',
                'nature' => 'hypothesis',
                'cause' => 'Mon personnage craint que son vote soit mal interprété par sa famille. Il ne sait pas si cette crainte correspond à une réaction qui se produirait réellement.',
                'consequence' => 'Il commence par exprimer son inquiétude et proposer une discussion, sans être forcé de voter ni de tout dévoiler.',
                'uncertainty' => 'Aucune pression n’est démontrée. Écouter séparément les interprétations et rechercher des éléments contradictoires.',
                'unknownVariables' => 'D’autres expériences peuvent expliquer cette peur ; aucune surveillance ou technologie cachée n’est établie.',
                'reason' => 'Rendre dicible la crainte de s’exprimer, sans accusation.',
            ]);
            $accounts[] = [
                'id' => 'account_fixture_' . $index . '_concern',
                'authorId' => $local[8]['id'],
                'territoryId' => $city['id'],
                'proposalId' => $proposal['id'],
                'title' => 'J’hésite à donner mon avis : comprendre cette inquiétude',
                'category' => 'Liberté d’expression et inquiétude',
                'kind' => 'concern',
                'fictional' => true,
                'entries' => [$concern],
            ];
        }

        return $accounts;
    }
}
