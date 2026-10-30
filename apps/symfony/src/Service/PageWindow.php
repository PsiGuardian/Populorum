<?php

declare(strict_types=1);

namespace App\Service;

/** Filter before slicing: bounded HTML without hiding the total or requiring JavaScript. */
final class PageWindow
{
    public static function slice(array $items, int $page, int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Page size out of bounds.');
        }
        $total = count($items);
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($pages, max(1, $page));

        return [
            'items' => array_slice($items, ($page - 1) * $limit, $limit),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'first' => $total ? ($page - 1) * $limit + 1 : 0,
            'last' => min($page * $limit, $total),
        ];
    }
}
