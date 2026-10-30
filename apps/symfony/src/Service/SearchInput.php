<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** A search term is data, never a SQL/DQL fragment, regex, sort expression or template. */
final class SearchInput
{
    public function term(mixed $value): string
    {
        if (
            !is_string($value)
            || !mb_check_encoding($value, 'UTF-8')
            || mb_strlen($value) > 160
            || preg_match('/[\x00-\x1f\x7f]/u', $value)
        ) {
            throw new BadRequestHttpException('Recherche invalide : texte de 160 caractères maximum, sans caractère de contrôle.');
        }

        return trim($value);
    }

    public function territory(mixed $value, array $territories): string
    {
        if (
            !is_string($value)
            || !in_array(
                $value,
                array_merge(['all', ''], array_column($territories, 'id')),
                true,
            )
        ) {
            throw new BadRequestHttpException('Territoire de recherche inconnu.');
        }

        return $value;
    }
}
