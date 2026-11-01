<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Documented public-money links. There is no attribution of a person's tax payment
 * to a downstream beneficiary: tax receipts are pooled and funds are fungible.
 */
final class BudgetTrace
{
    private ?array $cachedRecords = null;
    private const STAGES = [
        'voted' => 'Voté / autorisé (pas un paiement)',
        'awarded' => 'Attribué (paiement non prouvé)',
        'paid' => 'Versement documenté',
        'spent' => 'Utilisation justifiée dans un compte rendu',
    ];

    private const DESTINATIONS = [
        'france' => 'Utilisation en France documentée',
        'outside_france' => 'Utilisation hors de France documentée',
        'unknown' => 'Destination non documentée',
    ];

    public function __construct(private readonly string $projectDir)
    {
    }

    public function build(array $cities, string $cityCode, string $category, string $destination, string $selectedId): array
    {
        $chosen = null;
        foreach ($cities as $city) {
            if (($city['city']['code'] ?? null) === $cityCode) {
                $chosen = $city;
                break;
            }
        }
        $destination = array_key_exists($destination, self::DESTINATIONS) ? $destination : 'all';
        $records = $this->readRecords();
        $filtered = [];
        foreach ($records as $record) {
            if ($cityCode !== 'FR' && $record['cityCode'] !== $cityCode) {
                continue;
            }
            // A funding record may concern several citizen themes.
            // An evidenced use outside France is also visible in the international lens.
            if ($category !== 'overview'
                && $record['category'] !== $category
                && !in_array($category, $record['themes'], true)
                && !($category === 'international' && $record['destination'] === 'outside_france')) {
                continue;
            }
            if ($destination !== 'all' && $record['destination'] !== $destination) {
                continue;
            }
            $filtered[] = $record;
        }
        $byId = array_column($records, null, 'id');
        $trail = [];
        // Only traverse explicit parent links, never guess a relationship by amount or name.
        if (isset($byId[$selectedId]) && ($cityCode === 'FR' || $byId[$selectedId]['cityCode'] === $cityCode)) {
            $seen = [];
            $cursor = $selectedId;
            for ($i = 0; $i < 25 && isset($byId[$cursor]) && !isset($seen[$cursor]); ++$i) {
                $record = $byId[$cursor];
                if ($record['cityCode'] !== $byId[$selectedId]['cityCode']) {
                    break;
                }
                $seen[$cursor] = true;
                $trail[] = $record;
                $cursor = $record['parentId'];
                if ($cursor === '') {
                    break;
                }
            }
            $trail = array_reverse($trail);
        }
        $amounts = $chosen['amounts'] ?? [];
        $localTaxCents = $amounts['Impôts locaux'] ?? null;
        $totalCents = $chosen['totalCents'] ?? null;
        $financialYear = (string) ($chosen['rows'][0]['exer'] ?? 'non disponible');

        return [
            'city' => $chosen,
            'cityCode' => $cityCode,
            'destination' => $destination,
            'destinations' => self::DESTINATIONS,
            'records' => $filtered,
            'totalRecords' => count($records),
            'trail' => $trail,
            'stages' => self::STAGES,
            'publicStages' => [
                [
                    'title' => '1. Ressources collectives',
                    'amountCents' => $localTaxCents,
                    'detail' => 'Produit annuel des impôts locaux dans les comptes communaux. La part versée par une personne n’est ni publiée ni affectable à une dépense particulière.',
                    'status' => 'Donnée publique officielle · origine individuelle non traçable',
                ],
                [
                    'title' => '2. Budget de la collectivité',
                    'amountCents' => $totalCents,
                    'detail' => 'Dépenses totales constatées pour l’exercice ' . $financialYear . '. Les financements comprennent aussi dotations, emprunts et autres recettes ; ce nombre n’est pas la somme des impôts locaux.',
                    'status' => 'Donnée publique officielle · pas de lien de paiement individualisé',
                ],
                [
                    'title' => '3. Affectation budgétaire',
                    'amountCents' => null,
                    'detail' => 'Les postes par nature sont visibles dans les filtres. Ils ne précisent pas systématiquement l’organisme ou la personne qui reçoit le paiement.',
                    'status' => 'Rubrique comptable disponible ; destinataire à documenter',
                ],
                [
                    'title' => '4. Dernier emploi publiquement vérifiable',
                    'amountCents' => null,
                    'detail' => 'Un nom de bénéficiaire, un versement, puis un compte rendu d’utilisation exigent des pièces supplémentaires. Aucune destination finale n’est présumée.',
                    'status' => 'Chaîne interrompue dans les sources locales actuelles',
                ],
            ],
        ];
    }

    /** Full validated register, for verified parent-child documentation links. */
    public function records(): array
    {
        return $this->readRecords();
    }

    public function recordsForCity(string $code): array
    {
        return array_values(array_filter($this->records(), static fn (array $r): bool => $r['cityCode'] === $code));
    }

    private static function limit(string $value, int $length): string
    {
        $value = trim($value);

        return preg_match('/\A.{0,' . $length . '}/us', $value, $match) ? $match[0] : '';
    }

    /** Accept only records with identifiers and dated HTTPS sources. Bad rows are omitted. */
    private function readRecords(): array
    {
        if ($this->cachedRecords !== null) {
            return $this->cachedRecords;
        }
        $file = $this->projectDir . '/data/research/public-funding-links.json';
        if (!is_file($file) || filesize($file) > 20971520) {
            return [];
        }
        try {
            $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($decoded) || ($decoded['schema'] ?? '') !== 'populorum-funding-links-v1' || !is_array($decoded['records'] ?? null)) {
            return [];
        }
        $out = [];
        foreach (array_slice($decoded['records'], 0, 20000) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['id'] ?? null;
            $cityCode = $row['cityCode'] ?? null;
            $category = $row['category'] ?? null;
            $stage = $row['stage'] ?? null;
            $url = $row['sourceUrl'] ?? null;
            $date = $row['documentDate'] ?? null;
            $from = $row['from'] ?? null;
            $to = $row['to'] ?? null;
            $amount = $row['amountCents'] ?? null;
            $dest = $row['destination'] ?? null;
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $id) || isset($out[$id])
                || !is_string($cityCode) || !preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $cityCode)
                || !is_string($category) || !isset(BudgetExplorer::CATEGORIES[$category])
                || !isset(self::STAGES[$stage]) || !isset(self::DESTINATIONS[$dest])
                || !is_int($amount) || $amount < 0 || $amount > 100000000000000000
                || !is_string($url) || strlen($url) > 2048 || !str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false
                || !is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
                || !is_string($from) || trim($from) === '' || !preg_match('/\A.{1,160}\z/us', $from)
                || !is_string($to) || trim($to) === '' || !preg_match('/\A.{1,160}\z/us', $to)
            ) {
                continue;
            }
            $parentId = $row['parentId'] ?? '';
            if (!is_string($parentId) || ($parentId !== '' && !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $parentId))) {
                continue;
            }
            $rawThemes = $row['themes'] ?? [];
            $themes = is_array($rawThemes) && array_is_list($rawThemes)
                ? array_values(array_unique(array_filter(array_slice($rawThemes, 0, 12),
                    static fn (mixed $key): bool => is_string($key) && isset(BudgetExplorer::CATEGORIES[$key]))))
                : [];
            $out[$id] = [
                'id' => $id, 'cityCode' => $cityCode, 'category' => $category, 'themes' => $themes,
                'stage' => $stage, 'stageLabel' => self::STAGES[$stage],
                'destination' => $dest, 'destinationLabel' => self::DESTINATIONS[$dest],
                'amountCents' => $amount, 'sourceUrl' => $url,
                'documentDate' => $date, 'from' => trim($from), 'to' => trim($to),
                'parentId' => $parentId, 'purpose' => self::limit((string) ($row['purpose'] ?? ''), 300),
                'reference' => self::limit((string) ($row['reference'] ?? ''), 140),
                'beneficiaryIdentifier' => self::limit((string) ($row['beneficiaryIdentifier'] ?? ''), 40),
                'destinationPlace' => self::limit((string) ($row['destinationPlace'] ?? ''), 160),
            ];
        }
        // Do not expose a relationship unless the referenced row is present and local.
        // Further use of the funds is never inferred from the grant amount alone.
        foreach ($out as $id => $row) {
            $parent = $row['parentId'];
            if ($parent !== '' && (!isset($out[$parent]) || $out[$parent]['cityCode'] !== $row['cityCode'] || $parent === $id)) {
                $out[$id]['parentId'] = '';
            }
        }
        // Reject cycles in parent chains: documentation must be a directed acyclic graph.
        foreach ($out as $id => $record) {
            $seen = [];
            $cursor = $id;
            for ($depth = 0; $depth <= count($out); ++$depth) {
                if ($cursor === '' || !isset($out[$cursor])) {
                    break;
                }
                if (isset($seen[$cursor])) {
                    $out[$cursor]['parentId'] = '';
                    break;
                }
                $seen[$cursor] = true;
                $cursor = $out[$cursor]['parentId'];
            }
        }

        return $this->cachedRecords = array_values($out);
    }
}
