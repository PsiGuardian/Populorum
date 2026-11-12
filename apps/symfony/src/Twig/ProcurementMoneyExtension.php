<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Format DECP / documentary amounts without converting integer cents to float.
 * Very large source amounts can exceed the exact integer range of a PHP double.
 */
final class ProcurementMoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('decp_euros', [self::class, 'formatCents'])];
    }

    public static function formatCents(int|string|null $cents): string
    {
        if ($cents === null) {
            return 'Non communiqué';
        }
        $raw = (string) $cents;
        if (preg_match('/\A-?[0-9]+\z/D', $raw) !== 1) {
            return 'Montant invalide';
        }
        $negative = str_starts_with($raw, '-');
        $digits = str_pad(ltrim($raw, '-'), 3, '0', STR_PAD_LEFT);
        $euros = ltrim(substr($digits, 0, -2), '0');
        if ($euros === '') {
            $euros = '0';
        }
        $grouped = preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', ' ', $euros);

        return ($negative ? '-' : '') . $grouped . ',' . substr($digits, -2) . ' €';
    }
}
