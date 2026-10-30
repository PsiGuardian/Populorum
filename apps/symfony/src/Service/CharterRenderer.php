<?php

declare(strict_types=1);

namespace App\Service;

use League\CommonMark\CommonMarkConverter;

/** Rendu Markdown des documents versionnés ; aucun HTML embarqué n’est accepté. */
final class CharterRenderer
{
    public function render(string $markdown): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 30,
        ]);
        $html = (string) $converter->convert($markdown);

        // La page possède déjà son h1 : la hiérarchie du document commence en h2.
        return preg_replace_callback(
            "/<(\/?)(h)([1-6])>/",
            static fn (array $match): string => '<' .
                $match[1] .
                'h' .
                min(6, (int) $match[3] + 1) .
                '>',
            $html,
        );
    }
}
