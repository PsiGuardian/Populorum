<?php

declare(strict_types=1);

namespace App\Service;

/** Modèle narratif déterministe, sans diagnostic psychologique ni inférence sur des personnes réelles. */
final class FictionalMind
{
    public const VERSION = 'fictional-mind-1';
    public const PRIORITIES = [
        'safety' => 'la sécurité du quotidien',
        'liberty' => 'les libertés et la vie privée',
        'solidarity' => 'l’entraide et l’accès aux services',
        'ecology' => 'le cadre de vie et l’environnement',
        'budget' => 'la maîtrise des dépenses',
        'accessibility' => 'l’accessibilité concrète',
    ];
    private const VOICES = [
        [
            'name' => 'direct et concret',
            'opening' => 'Moi, je pars du quotidien.',
            'doubt' => 'Je veux bien essayer, mais pas les yeux fermés.',
            'revision' => 'Si ça ne marche pas, on corrige, tout simplement.',
        ],
        [
            'name' => 'posé et analytique',
            'opening' => 'Je distingue le constat de son interprétation.',
            'doubt' => 'L’incertitude doit rester visible dans la décision.',
            'revision' => 'Je réexaminerais le protocole avant d’attribuer un effet à la mesure.',
        ],
        [
            'name' => 'oral et familier',
            'opening' => 'Bon, voilà comment je le vois.',
            'doubt' => 'Sur le papier, d’accord ; sur place, faut voir.',
            'revision' => 'Si les faits me contredisent, je ne vais pas faire comme si de rien n’était.',
        ],
        [
            'name' => 'prudent et interrogatif',
            'opening' => 'Et si une partie du problème nous échappait ?',
            'doubt' => 'Qu’est-ce qui me ferait changer d’avis ?',
            'revision' => 'Je demanderais une seconde observation avant de trancher.',
        ],
        [
            'name' => 'chaleureux et collectif',
            'opening' => 'Je pense d’abord à ceux qui demanderont de l’aide.',
            'doubt' => 'J’aimerais que chacun puisse raconter ce qui lui arrive, sans pression.',
            'revision' => 'Je reprendrais la discussion avec les personnes concernées.',
        ],
        [
            'name' => 'bref et réservé',
            'opening' => 'Mon point de départ est simple.',
            'doubt' => 'Pas assez de recul, à mon avis.',
            'revision' => 'Un essai. Un bilan. Puis une décision.',
        ],
        [
            'name' => 'imagé et narratif',
            'opening' => 'J’imagine le trajet d’une personne qui rentre chez elle.',
            'doubt' => 'Une belle carte ne dit pas tout du terrain.',
            'revision' => 'Je retournerais voir le chemin après les premières difficultés.',
        ],
        [
            'name' => 'méthodique et formel',
            'opening' => 'Je retiens trois exigences : un périmètre, une source, un réexamen.',
            'doubt' => 'Une promesse ne constitue pas encore un résultat.',
            'revision' => 'Je proposerais une nouvelle version, datée et justifiée.',
        ],
        [
            'name' => 'enthousiaste et expressif',
            'opening' => 'J’ai envie qu’on tente quelque chose d’utile !',
            'doubt' => 'Mon enthousiasme ne remplace pas une mesure sérieuse.',
            'revision' => 'Je garderais l’élan, en changeant ce qui a échoué.',
        ],
        [
            'name' => 'sceptique et incisif',
            'opening' => 'Avant de promettre, regardons ce qui est vérifiable.',
            'doubt' => 'Qui vérifie, et avec quelle méthode ?',
            'revision' => 'Je retirerais les promesses que les résultats ne soutiennent pas.',
        ],
        [
            'name' => 'nuancé et conditionnel',
            'opening' => 'Je pourrais soutenir cette idée, à certaines conditions.',
            'doubt' => 'Deux effets contraires peuvent coexister.',
            'revision' => 'Je limiterais la mesure au contexte où elle reste défendable.',
        ],
        [
            'name' => 'pratique et attentif au coût',
            'opening' => 'Je regarde ce qu’on peut faire, avec quels moyens.',
            'doubt' => 'Il faut aussi compter le temps et les moyens engagés.',
            'revision' => 'Je comparerais une version plus légère à l’arrêt du dispositif.',
        ],
    ];

    /** Tirage stable par clé ; aucun état aléatoire global, aucune donnée démographique. */
    private function sample(string $key): float
    {
        return hexdec(
            substr(hash('sha256', self::VERSION . ':' . $key), 0, 6),
        ) / 16777216;
    }

    public function create(string $citizenId): array
    {
        $priorities = [];
        foreach (self::PRIORITIES as $key => $label) {
            $priorities[$key] = round(
                0.15 + 0.85 * $this->sample($citizenId . ':' . $key),
                3,
            );
        }
        arsort($priorities);
        $voice = (int) floor(
            $this->sample($citizenId . ':voice') * count(self::VOICES),
        );

        return [
            'model' => self::VERSION,
            'revision' => 1,
            'round' => 0,
            'priorities' => $priorities,
            'learningRate' => round(
                0.03 + 0.92 * $this->sample($citizenId . ':learning'),
                3,
            ),
            'openness' => round(
                0.15 + 0.8 * $this->sample($citizenId . ':openness'),
                3,
            ),
            'attachment' => round(
                0.05 + 0.9 * $this->sample($citizenId . ':attachment'),
                3,
            ),
            'uncertaintyTolerance' => round(
                0.1 + 0.8 * $this->sample($citizenId . ':uncertainty'),
                3,
            ),
            'initialTrust' => round(
                0.1 + 0.8 * $this->sample($citizenId . ':trust'),
                3,
            ),
            'voice' => min(count(self::VOICES) - 1, $voice),
            'idiom' => (int) floor($this->sample($citizenId . ':idiom') * 4),
            'dailyScene' => [
                'le trajet du matin',
                'les démarches qui prennent une demi-journée',
                'le retour quand les commerces ferment',
                'la traversée d’une place encombrée',
                'la facture qui tombe au mauvais moment',
                'l’attente devant un accueil fermé',
                'une rue rendue aux piétons',
                'le passage d’un quartier à l’autre',
                'un arrêt de transport mal indiqué',
                'un rendez-vous qu’on ne doit pas rater',
                'l’éclairage d’un chemin de retour',
                'les travaux qui obligent à faire un détour',
            ][(int) floor($this->sample($citizenId . ':scene') * 12)],
            'beliefs' => [],
            'experiences' => 0,
        ];
    }

    public function voice(array $mind): array
    {
        return self::VOICES[$mind['voice']];
    }

    /** Réaction propre au personnage : les mêmes contre-indices n’entraînent pas la même révision. */
    public function evolve(array $mind, array $feedback, int $round): array
    {
        foreach ($feedback as $proposalId => $observation) {
            $before = $mind['beliefs'][$proposalId] ?? 0.0;
            $contradicts =
                $before !== 0.0 && $before * $observation['signal'] < 0;
            $filter = $contradicts ? 1 - 0.8 * $mind['attachment'] : 1.0;
            $response =
                $mind['learningRate'] *
                $mind['openness'] *
                $filter *
                $observation['reliability'];
            $mind['beliefs'][$proposalId] = round(
                max(
                    -1,
                    min(
                        1,
                        $before +
                            $response * ($observation['signal'] - $before),
                    ),
                ),
                5,
            );
        }
        ++$mind['revision'];
        $mind['round'] = $round;
        ++$mind['experiences'];

        return $mind;
    }

    public function feedback(array $proposal, int $round): array
    {
        $scenario = ((int) substr($proposal['id'], 1) + $round) % 4;
        $signals = [-0.95, -0.35, 0.4, 0.85];
        $descriptions = [
            'Dans le scénario inventé, l’indicateur se dégrade et un report du problème apparaît.',
            'Dans le scénario inventé, le résultat est faible, coûteux et difficile à distinguer du contexte.',
            'Dans le scénario inventé, l’indicateur s’améliore légèrement, avec une incertitude importante.',
            'Dans le scénario inventé, une amélioration se répète, mais certains usagers restent mal desservis.',
        ];

        return [
            'signal' => $signals[$scenario],
            'reliability' => [0.85, 0.45, 0.5, 0.8][$scenario],
            'description' => $descriptions[$scenario],
            'unexpected' => [
                'Report vers un autre lieu dans la fiction',
                'Temps d’organisation sous-estimé dans la fiction',
                'Effet différent selon les horaires fictifs',
                'Accès inégal révélé par le scénario',
            ][$scenario],
            'indicator' => $proposal['indicator'],
            'simulated' => true,
        ];
    }

    /** Les réponses complètes sont régénérables à partir du profil figé et vérifiées par empreinte. */
    public function deliberate(
        array $citizen,
        array $proposal,
        array $mind,
        int $round,
    ): array {
        $priorities = $mind['priorities'];
        $priority = array_key_first($priorities);
        $text = mb_strtolower($proposal['title'] . ' ' . $proposal['solution']);
        $alignment = 0.0;
        foreach (
            [
                'safety' => 'sécur|victim|médiation|pression|signalement',
                'liberty' => 'confiden|droit|libert|discrimin',
                'solidarity' => 'accueil|aide|repas|logement|enfant',
                'ecology' => 'énerg|chaleur|ombre|climat|littoral|piéton',
                'budget' => 'coût|dépense|énerg',
                'accessibility' => 'accessib|travers|chemin|orientation',
            ] as $key => $pattern
        ) {
            if (preg_match('/' . $pattern . '/u', $text)) {
                $alignment += 0.22 * $priorities[$key];
            }
        }
        $belief = $mind['beliefs'][$proposal['id']] ?? 0.0;
        $reservation = $this->sample(
            $citizen['id'] . ':' . $proposal['id'] . ':reservation',
        );
        $score =
            $alignment +
            0.35 * ($mind['initialTrust'] - 0.5) +
            0.9 * $belief -
            0.4 * $reservation -
            0.12 * $priorities['budget'];
        $threshold = 0.035 + 0.12 * (1 - $mind['uncertaintyTolerance']);
        $choice =
            $score > $threshold
                ? 'pour'
                : ($score < -$threshold
                    ? 'contre'
                    : 'abstention');
        $voice = $this->voice($mind);
        $observation = $round ? $this->feedback($proposal, $round) : null;
        $stance = match ($choice) {
            'pour' => 'Je soutiens un essai limité, avec un droit d’arrêt et un bilan public.',
            'contre' => 'Je ne soutiens pas cette version ; je préfère revoir son périmètre avant d’essayer.',
            default => 'Je m’abstiens pour cette version : mes réserves ne sont pas levées.',
        };
        $learning =
            $mind['learningRate'] < 0.25
                ? 'Je reconnais la difficulté, mais je reste attaché à mon idée de départ ; ces éléments me font encore peu bouger.'
                : ($mind['learningRate'] > 0.7
                    ? 'Ces éléments me font revoir franchement mon attente, même si mon premier avis était différent.'
                    : 'Je corrige une partie de mon attente ; je garde encore plusieurs réserves.');
        $condition = [
            'Pour moi,',
            'À mes yeux,',
            'Si je dois choisir,',
            'Dans ce cas précis,',
        ][$mind['idiom']];
        $answers = [
            $voice['opening'] .
            ' Le sujet que je retiens : ' .
            lcfirst($proposal['problem']) .
            ' Ma priorité est ' .
            self::PRIORITIES[$priority] .
            '. Je pense par exemple à ' .
            $mind['dailyScene'] .
            '.',
            $condition .
            ' l’explication avancée est : ' .
            lcfirst($proposal['cause']) .
            ' Je ne la prends pas pour une preuve causale.',
            'Je regarderais les horaires, l’organisation et l’accès aux services autour de « ' .
            $proposal['title'] .
            ' ». Mon attention va surtout à ' .
            self::PRIORITIES[$priority] .
            '.',
            $voice['doubt'] .
            ' L’effet attendu de cette action reste une hypothèse ; une autre organisation ou le contexte peuvent expliquer un changement.',
            'Je peux citer le document « ' .
            ($proposal['sources'][0]['label'] ?? 'source du projet') .
            ' ». Il documente un contexte, pas l’efficacité du pilote. ' .
            ($observation
                ? $observation['description']
                : 'À cette étape, je n’ai aucun résultat du pilote, même fictif.'),
            $stance .
            ' L’action examinée est : ' .
            lcfirst($proposal['solution']),
            'J’espère surtout protéger ' .
            self::PRIORITIES[$priority] .
            '. La baisse annoncée de ' .
            abs($proposal['forecast']) .
            ' % reste un objectif inventé à confronter aux résultats, pas un acquis.',
            'Mon risque principal est ' .
            ($priorities['liberty'] > $priorities['budget']
                ? 'une restriction injustifiée ou une collecte excessive de données'
                : 'un coût disproportionné ou un service qui ne rejoint pas les personnes concernées') .
            '. ' .
            $proposal['risk'],
            'Je suivrais « ' .
            $proposal['indicator'] .
            ' » sur les mêmes lieux et horaires, avec une référence, une incertitude et les coûts. ' .
            $voice['doubt'],
            $observation
                ? $observation['unexpected'] .
                    '. Je demanderais si cet imprévu touche certains usages davantage que d’autres.'
                : 'Je ne peux pas raconter un imprévu qui n’a pas encore été observé. Je prévois de recueillir les reports et les difficultés d’accès.',
            'Je comparerais le projet à une amélioration des services existants, à un pilote plus petit et à l’absence de changement. ' .
            ($proposal['nodes'][10]['content'] ??
                'L’alternative doit être expliquée elle aussi.'),
            ($round
                ? $learning . ' '
                : 'Avant toute expérience, je fixe une condition de réexamen. ') .
            $voice['revision'] .
            ' ' .
            $proposal['reversibility'],
        ];
        $reasoning = [];
        foreach (SimulationEngine::CATEGORIES as $index => $category) {
            $answer = $answers[$index];
            if ($mind['voice'] === 2 && in_array($index, [1, 6, 7, 10], true)) {
                $answer = 'Franchement, ' . lcfirst($answer);
            }
            if ($mind['voice'] === 5 && $index === 8) {
                $answer =
                    'Un indicateur précis : « ' .
                    $proposal['indicator'] .
                    ' ». Même lieu, mêmes horaires, et une marge d’erreur.';
            }
            if ($mind['voice'] === 8 && $index === 6) {
                $answer = 'Voilà ce que j’aimerais voir arriver ! ' . $answer;
            }
            if ($mind['voice'] === 6 && $index === 2) {
                $answer =
                    'Je prends comme point de départ ' .
                    $mind['dailyScene'] .
                    '. ' .
                    $answer;
            }
            $reasoning[] = [
                'category' => $category,
                'answer' => $answer,
                'nature' => $index === 4
                        ? 'documentary_context_and_fiction'
                        : ($index === 9 && $round
                            ? 'simulated_observation'
                            : 'personal_hypothesis'),
            ];
        }

        return [
            'model' => self::VERSION,
            'citizenId' => $citizen['id'],
            'proposalId' => $proposal['id'],
            'proposalVersion' => $proposal['version'],
            'profileRevision' => $mind['revision'],
            'round' => $round,
            'choice' => $choice,
            'voice' => $voice['name'],
            'priority' => self::PRIORITIES[$priority],
            'learningNarrative' => $round
                ? $learning
                : 'Aucun retour d’expérience à cette étape.',
            'reasoning' => $reasoning,
            'simulated' => true,
        ];
    }

    public function digest(array $deliberation): string
    {
        return hash(
            'sha256',
            json_encode(
                $deliberation,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR,
            ),
        );
    }
}
