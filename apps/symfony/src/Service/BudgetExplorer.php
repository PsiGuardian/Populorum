<?php

declare(strict_types=1);

namespace App\Service;

/** Citizen-facing read model. Aggregates are never summed across accounting levels. */
final class BudgetExplorer
{
    public const CATEGORIES = [
        'overview' => ['label' => 'Vue globale', 'accounts' => ['Dépenses de fonctionnement', "Dépenses d'investissement"], 'note' => 'Deux composantes du total annuel. Elles ne doivent pas être additionnées aux sous-rubriques.'],
        'composition' => ['label' => 'Détail des dépenses sans double comptage', 'accounts' => ['Frais de personnel', 'Achats et charges externes', "Dépenses d'intervention", 'Charges financières', 'Autres dépenses de fonctionnement', "Dépenses d'équipement", "Subventions d'équipement versées", "Remboursements d'emprunts hors GAD", "Autres dépenses d'investissement"], 'note' => 'Neuf postes par nature appartenant au fonctionnement ou à l’investissement. Cette ventilation évite d’additionner les totaux parents.'],
        'functioning' => ['label' => 'Fonctionnement', 'accounts' => ['Dépenses de fonctionnement'], 'note' => 'Dépenses courantes ; ce total comprend d’autres rubriques affichables séparément.'],
        'investment' => ['label' => 'Investissement', 'accounts' => ["Dépenses d'investissement"], 'note' => 'Dépenses d’investissement, y compris remboursements, selon le périmètre OFGL.'],
        'personnel' => ['label' => 'Personnel et rémunérations', 'accounts' => ['Frais de personnel'], 'note' => 'Dépenses de personnel regroupées, sans identification des bénéficiaires individuels.'],
        'purchases' => ['label' => 'Achats et prestations', 'accounts' => ['Achats et charges externes'], 'note' => 'La nature des achats ne permet pas d’identifier ici les fournisseurs.'],
        'intervention' => ['label' => 'Interventions et aides', 'accounts' => ["Dépenses d'intervention"], 'note' => 'Total d’intervention : peut inclure des sous-rubriques de subventions.'],
        'grants' => ['label' => 'Subventions et associations', 'accounts' => ['Subventions aux personnes de droit privé', "Subventions d'équipement versées"], 'note' => 'Personnes de droit privé ≠ associations seulement ; les subventions d’équipement peuvent aussi avoir d’autres destinataires. Ne pas additionner ce filtre au total des interventions.'],
        'debt' => ['label' => 'Dette et intérêts', 'accounts' => ['Charges financières', "Remboursements d'emprunts hors GAD", 'Encours de dette'], 'note' => 'L’encours est un stock à une date ; les intérêts et remboursements sont des flux annuels. Ils ne doivent pas être additionnés.'],
        'revenue' => ['label' => 'Impôts et ressources', 'accounts' => ['Impôts locaux', 'Dotation globale de fonctionnement', 'Recettes de fonctionnement'], 'note' => 'Recettes : les impôts locaux et dotations font partie d’agrégats plus larges. Aucune affectation à une dépense précise ne peut en être déduite.'],
        'security' => ['label' => 'Sécurité et prévention', 'accounts' => [], 'note' => 'La balance par nature ne suffit pas à isoler l’ensemble des missions de sécurité. Il faut un budget par fonction, des décisions et justificatifs.'],
        'international' => ['label' => 'Actions et financements hors de France', 'accounts' => [], 'note' => 'Une subvention à une structure française ne démontre pas l’acheminement de fonds à l’étranger. Requiert bénéficiaire, destination et compte rendu.'],
        'foreign_residents' => ['label' => 'Actions en faveur de résidents étrangers en France', 'accounts' => [], 'note' => 'La nationalité du bénéficiaire et le pays où l’argent est dépensé sont deux informations distinctes. Les comptes disponibles ne chiffrent pas cette finalité.'],
        'politics' => ['label' => 'Élus, représentation et action politique', 'accounts' => [], 'note' => 'Indemnités, fonctionnement des assemblées, communication institutionnelle et financement politique doivent être séparés et documentés.'],
        'education' => ['label' => 'Éducation et jeunesse', 'accounts' => [], 'note' => 'Données fonctionnelles détaillées nécessaires ; les comptes par nature ne suffisent pas.'],
        'health' => ['label' => 'Santé et solidarité', 'accounts' => [], 'note' => 'Compétences communales, sociales et autres financeurs ne sont pas consolidés ici.'],
        'environment' => ['label' => 'Environnement et énergie', 'accounts' => [], 'note' => 'Fonctions et projets documentés requis pour ventiler ces dépenses.'],
        'culture' => ['label' => 'Culture, sport et vie associative', 'accounts' => [], 'note' => 'Les montants dédiés exigent des ventilations fonctionnelles ou des subventions nominatives.'],
    ];

    private const DESCRIPTIONS = [
        'Dépenses de fonctionnement' => 'Services et charges courantes, ensemble de l’exercice.',
        "Dépenses d'investissement" => 'Équipements, immobilisations, remboursements et autres postes d’investissement.',
        'Frais de personnel' => 'Rémunérations et charges liées aux agents.',
        'Autres dépenses de fonctionnement' => 'Autres charges de fonctionnement distinctes des postes détaillés.',
        "Dépenses d'équipement" => 'Acquisitions et équipements : flux annuel, hors remboursements de dette.',
        "Autres dépenses d'investissement" => 'Autres flux d’investissement du compte consolidé.',
        'Achats et charges externes' => 'Achats, fournitures, services et contrats regroupés.',
        "Dépenses d'intervention" => 'Interventions financières et contributions, sans détail des destinataires.',
        'Subventions aux personnes de droit privé' => 'Bénéficiaires de droit privé, qui ne sont pas tous des associations.',
        "Subventions d'équipement versées" => 'Subventions destinées à l’investissement.',
        'Charges financières' => 'Charges annuelles de financement.',
        "Remboursements d'emprunts hors GAD" => 'Remboursements du principal de la dette.',
        'Encours de dette' => 'Dette restante : stock et non dépense annuelle.',
        'Impôts locaux' => 'Produit de la fiscalité locale ; origine collective, pas portefeuille individuel.',
        'Dotation globale de fonctionnement' => 'Dotation reçue de l’État.',
        'Recettes de fonctionnement' => 'Ensemble des ressources de fonctionnement, sous-rubriques incluses.',
    ];

    public function build(array $cities, string $code, string $category, string $sort, string $term): array
    {
        $category = array_key_exists($category, self::CATEGORIES) ? $category : 'overview';
        $sort = in_array($sort, ['amount_desc', 'amount_asc', 'label'], true) ? $sort : 'amount_desc';
        $term = trim($term);
        $term = preg_match('/\A.{0,80}/us', $term, $match) ? $match[0] : '';
        $chosen = null;
        foreach ($cities as $city) {
            if (($city['city']['code'] ?? '') === $code) {
                $chosen = $city;
                break;
            }
        }
        if ($chosen === null) {
            return ['category' => $category, 'sort' => $sort, 'term' => $term, 'city' => null, 'rows' => [], 'categories' => self::CATEGORIES, 'note' => 'Ville inconnue.'];
        }
        $definition = self::CATEGORIES[$category];
        $rows = [];
        foreach ($chosen['rows'] as $row) {
            $key = (string) ($row['agregat'] ?? '');
            if (!in_array($key, $definition['accounts'], true)) {
                continue;
            }
            $description = self::DESCRIPTIONS[$key] ?? '';
            if ($term !== '' && !preg_match('/' . preg_quote($term, '/') . '/iu', $key . ' ' . $description)) {
                continue;
            }
            $amountCents = (int) round(((float) $row['montant']) * 100);
            $totalCents = $chosen['totalCents'] ?? null;
            $rows[] = [
                'label' => $key,
                'description' => $description,
                'isRevenue' => $category === 'revenue',
                'amountCents' => $amountCents,
                'mainCents' => (int) round(((float) ($row['montant_bp'] ?? 0)) * 100),
                'annexCents' => (int) round(((float) ($row['montant_ba'] ?? 0)) * 100),
                'crossCents' => (int) round(((float) ($row['montant_flux'] ?? 0)) * 100),
                'perResident' => ($chosen['population'] ?? 0) > 0 ? $amountCents / (100 * $chosen['population']) : null,
                'share' => ($totalCents ?? 0) > 0 && !in_array($category, ['debt', 'revenue'], true)
                    ? min(100, max(0, 100 * $amountCents / $totalCents)) : null,
                'isStock' => $key === 'Encours de dette',
            ];
        }
        // The nine detail headings must reconcile with the reported yearly total.
        // Missing lines are not treated as evidence for a zero expenditure.
        $composition = self::CATEGORIES['composition']['accounts'];
        $byLabel = array_column($chosen['rows'], null, 'agregat');
        $allPresent = array_reduce($composition, static fn (bool $ok, string $key): bool => $ok && isset($byLabel[$key]), true);
        $compositionCents = $allPresent ? array_sum(array_map(
            static fn (string $key): int => (int) round((float) $byLabel[$key]['montant'] * 100),
            $composition,
        )) : null;
        $differenceCents = $compositionCents !== null && $chosen['totalCents'] !== null
            ? $chosen['totalCents'] - $compositionCents : null;
        usort($rows, static fn (array $a, array $b): int => match ($sort) {
            'amount_asc' => $a['amountCents'] <=> $b['amountCents'],
            'label' => strcmp($a['label'], $b['label']),
            default => $b['amountCents'] <=> $a['amountCents'],
        });

        return [
            'categories' => self::CATEGORIES,
            'category' => $category,
            'categoryLabel' => $definition['label'],
            'note' => $definition['note'],
            'sort' => $sort,
            'term' => $term,
            'city' => $chosen,
            'rows' => $rows,
            'isDirectlyMeasured' => $definition['accounts'] !== [],
            'sourceYear' => $chosen['rows'][0]['exer'] ?? null,
            'compositionCents' => $compositionCents,
            'compositionDifferenceCents' => $differenceCents,
            'compositionComplete' => $allPresent && $differenceCents !== null && abs($differenceCents) <= 1,
        ];
    }
}
