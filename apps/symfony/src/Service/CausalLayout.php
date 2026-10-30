<?php

declare(strict_types=1);

namespace App\Service;

/** Presentation follows declared dependencies; no relationship is inferred from position. */
final class CausalLayout
{
    public function layout(array $proposal): array
    {
        $nodes = array_column($proposal['nodes'], null, 'id');
        $incoming = array_fill_keys(array_keys($nodes), 0);
        $children = [];
        foreach ($proposal['edges'] as $edge) {
            if (isset($nodes[$edge['from']], $nodes[$edge['to']])) {
                ++$incoming[$edge['to']];
                $children[$edge['from']][] = $edge['to'];
            }
        }
        $queue = array_keys(array_filter($incoming, static fn (int $count): bool => $count === 0));
        $levels = array_fill_keys($queue, 0);
        for ($index = 0; $index < count($queue); ++$index) {
            $id = $queue[$index];
            foreach ($children[$id] ?? [] as $child) {
                $levels[$child] = max($levels[$child] ?? 0, $levels[$id] + 1);
                if (--$incoming[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }
        $cycleIds = array_keys(array_filter($incoming, static fn (int $count): bool => $count > 0));
        $cycleLevel = $levels ? max($levels) + 1 : 0;
        foreach ($cycleIds as $id) {
            $levels[$id] = $cycleLevel;
        }
        $groups = [];
        foreach ($nodes as $id => $node) {
            $groups[$levels[$id] ?? 0][] = $id;
        }
        ksort($groups);
        $row = 0;
        foreach ($groups as $ids) {
            foreach ($ids as $index => $id) {
                $nodes[$id]['x'] = 20 + ($index % 3) * 300;
                $nodes[$id]['y'] = 25 + ($row + intdiv($index, 3)) * 125;
            }
            $row += (int) ceil(count($ids) / 3);
        }
        $edges = [];
        foreach ($proposal['edges'] as $edge) {
            if (isset($nodes[$edge['from']], $nodes[$edge['to']])) {
                $edges[] = $edge + [
                    // Route through the gutter, never through an unrelated node.
                    'path' => sprintf(
                        'M %d %d V %d H %d V %d H %d V %d',
                        $nodes[$edge['from']]['x'] + 130,
                        $nodes[$edge['from']]['y'] + 70,
                        $nodes[$edge['from']]['y'] + 82,
                        $nodes[$edge['from']]['x'] + 275,
                        $nodes[$edge['to']]['y'] - 12,
                        $nodes[$edge['to']]['x'] + 130,
                        $nodes[$edge['to']]['y'],
                    ),
                    'x1' => $nodes[$edge['from']]['x'] + 130,
                    'y1' => $nodes[$edge['from']]['y'] + 70,
                    'x2' => $nodes[$edge['to']]['x'] + 130,
                    'y2' => $nodes[$edge['to']]['y'],
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'cycleIds' => $cycleIds,
            'height' => max(150, $row * 125),
        ];
    }
}
