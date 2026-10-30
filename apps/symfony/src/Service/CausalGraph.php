<?php

declare(strict_types=1);

namespace App\Service;

/** Validation du graphe ; les catégories complémentaires ne remplacent jamais les douze rubriques fondatrices. */
final class CausalGraph
{
    public const RELATIONS = [
        'cause de',
        'contribue à',
        'dépend de',
        'rend possible',
        'produit potentiellement',
        'observé après',
        'documenté par',
        'remplacé par',
        'alternative à',
    ];

    public function nodes(array $input): array
    {
        if (count($input) > 100) {
            throw new \DomainException('Un chemin contient au maximum 100 éléments.');
        }
        $nodes = [];
        foreach ($input as $index => $node) {
            $category =
                $node['category'] ??
                (SimulationEngine::CATEGORIES[$index] ?? '');
            $id = $node['id'] ?? 'n' . $index;
            if (
                !is_string($id)
                || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $id)
                || !is_string($category)
                || mb_strlen(trim($category)) < 2
                || mb_strlen($category) > 100
                || !is_string($node['content'] ?? '')
                || mb_strlen($node['content'] ?? '') > 3000
                || !in_array(
                    $node['kind'] ?? 'hypothesis',
                    ['hypothesis', 'observation'],
                    true,
                )
                || in_array($id, array_column($nodes, 'id'), true)
            ) {
                throw new \DomainException('Élément causal invalide ou identifiant dupliqué.');
            }
            $nodes[] = [
                'id' => $id,
                'category' => trim($category),
                'content' => trim($node['content'] ?? ''),
                'kind' => $node['kind'] ?? 'hypothesis',
                'evidenceType' => in_array(
                    $node['evidenceType'] ?? '',
                    [
                        'public_document',
                        'media_context',
                        'simulated',
                        'hypothesis',
                    ],
                    true,
                )
                    ? $node['evidenceType']
                    : (($node['kind'] ?? '') === 'observation'
                        ? 'simulated'
                        : 'hypothesis'),
            ];
        }

        return $nodes;
    }

    public function edges(array $input, array $nodes): array
    {
        if (count($input) > 300) {
            throw new \DomainException('Un chemin contient au maximum 300 relations.');
        }
        $ids = array_column($nodes, 'id');
        $edges = [];
        foreach ($input as $edge) {
            $relation = $edge['relation'] ?? ($edge['type'] ?? '');
            if (
                !in_array($edge['from'] ?? '', $ids, true)
                || !in_array($edge['to'] ?? '', $ids, true)
                || $edge['from'] === $edge['to']
                || !in_array($relation, self::RELATIONS, true)
            ) {
                throw new \DomainException('Relation invalide : choisissez deux éléments distincts et une relation proposée.');
            }
            // « type » reste accepté pour les exports antérieurs ; relation est la représentation canonique.
            $normalized = [
                'from' => $edge['from'],
                'to' => $edge['to'],
                'relation' => $relation,
            ];
            if (in_array($normalized, $edges, true)) {
                throw new \DomainException('Cette relation existe déjà.');
            }
            $edges[] = $normalized;
        }
        $this->assertAcyclicCauses($edges);

        return $edges;
    }

    private function assertAcyclicCauses(array $edges): void
    {
        $successors = [];
        foreach ($edges as $edge) {
            if (
                in_array($edge['relation'], ['cause de', 'contribue à'], true)
            ) {
                $successors[$edge['from']][] = $edge['to'];
            }
        }
        $visiting = [];
        $visited = [];
        $visit = function (string $id) use (
            &$visit,
            &$visiting,
            &$visited,
            $successors,
        ): void {
            if (isset($visiting[$id])) {
                throw new \DomainException('Les liens « cause de » et « contribue à » doivent former un graphe sans cycle.');
            }
            if (isset($visited[$id])) {
                return;
            }
            $visiting[$id] = true;
            foreach ($successors[$id] ?? [] as $next) {
                $visit($next);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach (array_keys($successors) as $id) {
            $visit($id);
        }
    }
}
