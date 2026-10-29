<?php

declare(strict_types=1);

namespace App\Service;

/** Géométrie de présentation uniquement ; aucune déduction causale à partir de la position d’un élément. */
final class CausalLayout
{
    public function layout(array $proposal): array
    {
        $nodes = [];
        foreach ($proposal['nodes'] as $index => $node) {
            $nodes[$node['id']] = $node + [
                'x' => 20 + ($index % 3) * 300,
                'y' => 25 + intdiv($index, 3) * 125,
            ];
        }
        $edges = [];
        foreach ($proposal['edges'] as $edge) {
            if (isset($nodes[$edge['from']], $nodes[$edge['to']])) {
                $edges[] = $edge + [
                    'x1' => $nodes[$edge['from']]['x'] + 130,
                    'y1' => $nodes[$edge['from']]['y'] + 48,
                    'x2' => $nodes[$edge['to']]['x'] + 130,
                    'y2' => $nodes[$edge['to']]['y'] + 48,
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'height' => max(150, (int) ceil(count($nodes) / 3) * 125),
        ];
    }
}
