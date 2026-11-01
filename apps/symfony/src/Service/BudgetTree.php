<?php

declare(strict_types=1);

namespace App\Service;

/** Expandable documentary tree: containment ≠ a bank-to-bank transfer. */
final class BudgetTree
{
    private const OPERATING = [
        'Frais de personnel', 'Achats et charges externes', "Dépenses d'intervention",
        'Charges financières', 'Autres dépenses de fonctionnement',
    ];
    private const CAPITAL = [
        "Dépenses d'équipement", "Subventions d'équipement versées",
        "Remboursements d'emprunts hors GAD", "Autres dépenses d'investissement",
    ];

    public function __construct(private readonly BudgetCatalog $catalog, private readonly BudgetTrace $trace, private readonly BudgetEvidenceCatalog $evidence, private readonly PublicProcurementData $procurement)
    {
    }

    private static function node(string $id, string $label, ?int $amount, bool $children, string $kind, string $note = '', ?string $url = null): array
    {
        return [
            'id' => $id, 'label' => $label, 'amountCents' => $amount,
            'hasChildren' => $children, 'kind' => $kind, 'note' => $note,
            'sourceUrl' => $url,
        ];
    }

    public function children(string $id, int $offset = 0): array
    {
        $offset = max(0, min(100000, $offset));
        $ofgl = 'https://data.ofgl.fr/explore/dataset/ofgl-base-communes-consolidee/';
        $items = [];
        $total = 0;
        if ($id === 'root') {
            $coverage = $this->procurement->coverage();
            if ($coverage['available']) {
                $items[] = self::node('procurement:root', 'Marchés publics et concessions · ' . number_format($coverage['contracts'], 0, ',', ' ') . ' contrats distincts', null, true, 'contracts', 'Données DECP, montants contractuels et non paiements ; géolocalisation prudente.', '/marches-publics');
            }
            $items[] = self::node('evidence:root', 'Bibliothèque des documents publics · ' . count($this->evidence->all()) . ' références', null, true, 'documents', 'Références officielles classées par thème ; ne sont pas des transferts financiers attestés.');
            foreach ($this->catalog->regions() as $row) {
                $items[] = self::node('region:' . $row['code'], $row['name'] . ' · ' . $row['count'] . ' commune(s) du référentiel géographique', null, true, 'region');
            }
        } elseif (preg_match('/\Aregion:([0-9A-Z]{1,3})\z/D', $id, $match)) {
            foreach ($this->catalog->departments($match[1]) as $row) {
                $items[] = self::node('dept:' . $match[1] . ':' . $row['code'], $row['name'] . ' · ' . $row['count'] . ' commune(s)', null, true, 'department');
            }
        } elseif (preg_match('/\Adept:([0-9A-Z]{1,3}):([0-9A-Z]{1,3})\z/D', $id, $match)) {
            $page = $this->catalog->cityPage($match[1], $match[2], $offset);
            $total = $page['total'];
            foreach ($page['rows'] as $row) {
                $city = $this->catalog->city($row['code']);
                $items[] = self::node('city:' . $row['code'], $row['name'] . ' (' . $row['code'] . ')', $city['totalCents'] ?? null, true, 'city', ($city['totalCents'] ?? null) !== null ? 'Dépenses communales OFGL 2025. Ne pas additionner aux totaux parents.' : 'Commune référencée par INSEE ; comptes OFGL 2025 non importés ou indisponibles.', $ofgl);
            }
        } elseif (preg_match('/\Acity:((?:[0-9]{5}|2[AB][0-9]{3}))\z/D', $id, $match)) {
            $city = $this->catalog->city($match[1]);
            if ($city !== null && $city['totalCents'] === null) {
                $items = [
                    self::node('missing-budget:' . $match[1], 'Données financières non chargées pour cette commune', null, false, 'unknown', 'Commune officielle, sans montant OFGL documenté dans cet index. Une absence de donnée ne signifie pas une dépense nulle.'),
                    self::node('evidence:city:' . $match[1], 'Sources publiques et documents consultables', null, true, 'documents', 'Bibliothèque documentaire ; toutes les sources ne contiennent pas des opérations de cette commune.'),
                    self::node('docs:' . $match[1], 'Justificatifs individualisés documentés', null, true, 'documents', 'Les transferts ne seront affichés que si une pièce publique les établit.'),
                ];
            } elseif ($city !== null) {
                $items = [
                    self::node('resources:' . $match[1], 'Ressources fiscales et autres recettes (origine mutualisée)', $city['amounts']['Recettes de fonctionnement'] ?? null, true, 'resources', 'Les recettes ne sont pas affectables à une dépense précise.', $ofgl),
                    self::node('section:' . $match[1] . ':operating', 'Fonctionnement', $city['amounts']['Dépenses de fonctionnement'] ?? null, true, 'section', 'Partie du total des dépenses.', $ofgl),
                    self::node('section:' . $match[1] . ':capital', 'Investissement', $city['amounts']["Dépenses d'investissement"] ?? null, true, 'section', 'Partie du total des dépenses.', $ofgl),
                    self::node('evidence:city:' . $match[1], 'Sources publiques et documents consultables', null, true, 'documents', 'Dossiers documentaires indépendants des transferts effectivement établis.'),
                    self::node('docs:' . $match[1], 'Attributions, paiements et utilisations documentés', null, true, 'documents', 'Branche séparée : une pièce n’est reliée à une rubrique que si la source établit ce lien.'),
                ];
            }
            if ($city !== null && $this->procurement->coverage()['available']) {
                $count = $this->procurement->cityCount($match[1]);
                $items[] = self::node('procurement:city:' . $match[1],
                    'Contrats dont le lieu d’exécution est explicitement cette commune · ' . $count,
                    null, true, 'contracts',
                    'Seul le champ Code commune est admis. Codes postaux, départements et acheteurs non géolocalisés ne sont pas attribués arbitrairement.',
                    '/marches-publics?city=' . $match[1]);
            }
        } elseif ($id === 'evidence:root' || preg_match('/\Aevidence:city:((?:[0-9]{5}|2[AB][0-9]{3}))\z/D', $id, $match)) {
            $code = $id === 'evidence:root' ? 'FR' : $match[1];
            foreach ($this->evidence->grouped($code) as $group) {
                $key = $code === 'FR' ? 'evidence:group:' . $group['id'] : 'evidence:city-group:' . $code . ':' . $group['id'];
                $items[] = self::node($key, $group['label'] . ' · ' . $group['count'] . ' source(s)', null, true, 'documents', 'Sources bibliographiques, pas transactions ni dépenses à additionner.');
            }
        } elseif (preg_match('/\Aevidence:group:([a-z_]+)\z/D', $id, $match)
            || preg_match('/\Aevidence:city-group:((?:[0-9]{5}|2[AB][0-9]{3})):([a-z_]+)\z/D', $id, $local)) {
            $code = isset($local[1]) ? $local[1] : 'FR';
            $group = isset($local[2]) ? $local[2] : $match[1];
            foreach ($this->evidence->forScope($code, $group) as $source) {
                $items[] = self::node(
                    'evidence:ref:' . $source['id'],
                    $source['title'] . ' · ' . $source['year'], null, false, 'document',
                    'Niveau : ' . $source['level'] . '. ' . $source['description'] . ' Limite : ' . $source['limitations'],
                    $source['url'],
                );
            }
        } elseif ($id === 'procurement:root') {
            $stats = $this->procurement->coverage();
            if ($stats['available']) {
                $items[] = self::node('procurement:type:market', 'Marchés publics · ' . $stats['markets'], null, true, 'contracts', 'Montants des contrats : ne constituent pas des paiements.', '/marches-publics?kind=market');
                $items[] = self::node('procurement:type:concession', 'Concessions · ' . $stats['concessions'], null, true, 'contracts', 'Valeur globale et subvention publique déclarées : indicateurs distincts.', '/marches-publics?kind=concession');
            }
        } elseif (preg_match('/\Aprocurement:type:(market|concession)\z/D', $id, $group)) {
            foreach ($this->procurement->years($group[1]) as $year) {
                $items[] = self::node('procurement:year:' . $group[1] . ':' . $year['year'],
                    $year['year'] . ' · ' . $year['n'] . ' contrat(s) notifié(s)', null, true,
                    'contracts', 'Année de notification, distincte de la date de publication.',
                    '/marches-publics?kind=' . $group[1] . '&year=' . $year['year']);
            }
        } elseif (preg_match('/\Aprocurement:year:(market|concession):([12][0-9]{3})\z/D', $id, $group)) {
            $page = $this->procurement->search(['kind' => $group[1], 'year' => $group[2]], $offset, 30);
            $total = $page['total'];
            foreach ($page['items'] as $contract) {
                $items[] = self::node('procurement:contract:' . $contract['contract_key'],
                    $contract['object'] . ' · acheteur SIRET ' . $contract['buyer_id'],
                    $contract['amount_initial_cents'] !== null ? (int) $contract['amount_initial_cents'] : null,
                    true, 'contract', 'Montant contractuel déclaré, non paiement effectué. Contrat ' . $contract['original_id'],
                    '/marches-publics/' . $contract['contract_key']);
            }
        } elseif (preg_match('/\Aprocurement:city:((?:[0-9]{5}|2[AB][0-9]{3}))\z/D', $id, $group)) {
            $page = $this->procurement->search(['city' => $group[1]], $offset, 30);
            $total = $page['total'];
            foreach ($page['items'] as $contract) {
                $items[] = self::node('procurement:contract:' . $contract['contract_key'],
                    $contract['object'] . ' · SIRET acheteur ' . $contract['buyer_id'],
                    $contract['amount_initial_cents'] !== null ? (int) $contract['amount_initial_cents'] : null,
                    true, 'contract', 'Lieu d’exécution codé commune, pas nécessairement contrat signé par sa mairie.', '/marches-publics/' . $contract['contract_key']);
            }
            if (!$items) {
                $items[] = self::node('procurement:none:' . $group[1], 'Aucun contrat à lieu d’exécution communal exact dans l’index chargé', null, false, 'unknown', 'L’absence de ligne n’est pas une absence de marchés : une majorité des contrats sont localisés moins précisément.');
            }
        } elseif (preg_match('/\Aprocurement:contract:([a-f0-9]{40})\z/D', $id, $group)) {
            $detail = $this->procurement->detail($group[1]);
            if ($detail !== null) {
                $items[] = self::node('procurement:buyer:' . $group[1],
                    'Acheteur SIRET : ' . $detail['buyer_id'], null, false, 'document', 'Identifiant déclaré ; vérifier l’établissement dans Sirene.');
                foreach ($detail['participants'] as $p) {
                    $items[] = self::node('procurement:participant:' . $group[1] . ':' . count($items),
                        $p['role'] . ' · ' . $p['identifier_type'] . ' : ' . $p['identifier'], null, false, 'document',
                        'Participation déclarée ; aucun versement déduit.');
                }
                foreach ($detail['events'] as $ev) {
                    $items[] = self::node('procurement:event:' . $group[1] . ':' . count($items),
                        ($ev['event_type'] === 'subcontract' ? 'Sous-traitance' : 'Modification') . ' · ' . $ev['event_date'] . ($ev['identifier'] !== '' ? ' · ' . $ev['identifier'] : ''),
                        $ev['amount_cents'] !== null ? (int) $ev['amount_cents'] : null, false, 'document',
                        'Montant déclaré pour cet événement, ne pas additionner aux valeurs contractuelles.');
                }
                $history = $detail['history'] ?? null;
                if ($history !== null) {
                    $items[] = self::node('procurement:history:' . $group[1],
                        'Historique · ' . $history['references'] . ' fichier(s), ' . $history['observed'] . ' observation(s)',
                        null, true, 'document', 'Montants déclarés successifs, non dépenses ni paiements.',
                        '/marches-publics/' . $group[1]);
                }
                $items[] = self::node('procurement:source:' . $group[1], 'Source et versions originales', null, false, 'document',
                    'Historique des publications et limites disponibles dans la fiche du contrat.', '/marches-publics/' . $group[1]);
            }
        } elseif (preg_match('/\Aprocurement:history:([a-f0-9]{40})\z/D', $id, $group)) {
            $detail = $this->procurement->detail($group[1]);
            if ($detail !== null) {
                $history = $detail['history'];
                foreach ($history['items'] as $observation) {
                    $amount = $observation['base_amount_cents'];
                    $items[] = self::node('procurement:publication:' . $group[1] . ':' . count($items),
                        $observation['filename'] . ' · ' . ($amount !== null ? 'valeur déclarée' : 'montant inconnu'),
                        $amount !== null ? (int) $amount : null, false, 'document',
                        'Valeur contractuelle dans ce fichier, pas une dépense réglée. Ne pas additionner les versions.',
                        '/marches-publics/' . $group[1]);
                }
                if (!$history['complete']) {
                    $items[] = self::node('procurement:missinghistory:' . $group[1],
                        'Historique incomplet : importer les observations manquantes', null, false, 'unknown',
                        'Une source est référencée mais sa valeur n’a pas encore été extraite.');
                }
            }
        } elseif (preg_match('/\Aresources:((?:[0-9]{5}|2[AB][0-9]{3}))\z/D', $id, $match)) {
            $city = $this->catalog->city($match[1]);
            if ($city !== null) {
                foreach (['Impôts locaux', 'Dotation globale de fonctionnement', 'Recettes de fonctionnement'] as $label) {
                    if (isset($city['amounts'][$label])) {
                        $items[] = self::node('end:' . $match[1] . ':' . count($items), $label, $city['amounts'][$label], false, 'aggregate', 'Sous-total ou composante des recettes ; lignes non additives.', $ofgl);
                    }
                }
            }
        } elseif (preg_match('/\Asection:((?:[0-9]{5}|2[AB][0-9]{3})):(operating|capital)\z/D', $id, $match)) {
            $city = $this->catalog->city($match[1]);
            if ($city !== null) {
                $components = $match[2] === 'operating' ? self::OPERATING : self::CAPITAL;
                foreach ($components as $index => $label) {
                    $amount = $city['amounts'][$label] ?? null;
                    $items[] = self::node('nature:' . $match[1] . ':' . $match[2] . ':' . $index, $label, $amount, true, 'aggregate', $amount === null ? 'Montant non disponible.' : 'Compte de nature : bénéficiaire final non identifié par cet agrégat.', $ofgl);
                }
            }
        } elseif (preg_match('/\Anature:((?:[0-9]{5}|2[AB][0-9]{3})):(operating|capital):([0-9])\z/D', $id, $match)) {
            $components = $match[2] === 'operating' ? self::OPERATING : self::CAPITAL;
            if (isset($components[(int) $match[3]]) && $this->catalog->city($match[1]) !== null) {
                $items[] = self::node('stop:' . $match[1] . ':' . $match[2] . ':' . $match[3], 'Dernier niveau public disponible : destinataire non individualisé', null, false, 'unknown', 'La donnée OFGL ne précise ni le prestataire, ni le versement, ni la destination finale.');
            }
        } elseif (preg_match('/\Adocs:((?:[0-9]{5}|2[AB][0-9]{3}))\z/D', $id, $match)) {
            $records = $this->trace->recordsForCity($match[1]);
            foreach ($records as $record) {
                if ($record['parentId'] === '') {
                    $items[] = $this->recordNode($record, $records);
                }
            }
            if (!$items) {
                $items[] = self::node('no-document:' . $match[1], 'Aucun maillon documentaire individualisé dans le registre', null, false, 'unknown', 'L’absence de pièce importée ne signifie pas qu’aucune dépense n’existe.');
            }
        } elseif (preg_match('/\Aflow:([a-zA-Z0-9_-]{1,64})\z/D', $id, $match)) {
            $all = $this->trace->records();
            $parent = null;
            foreach ($all as $record) {
                if ($record['id'] === $match[1]) {
                    $parent = $record;
                    break;
                }
            }
            if ($parent !== null) {
                foreach ($all as $record) {
                    if ($record['parentId'] === $parent['id'] && $record['cityCode'] === $parent['cityCode']) {
                        $items[] = $this->recordNode($record, $all);
                    }
                }
                if (!$items) {
                    $items[] = self::node('trace-end:' . $parent['id'], 'Fin des transferts documentés', null, false, 'unknown', 'Ne pas présumer de l’utilisation ultérieure des fonds.');
                }
            }
        }

        // Lists of municipalities and contracts are paginated to bound responses.
        return ['node' => $id, 'children' => $items, 'offset' => $offset, 'nextOffset' => $total > $offset + count($items) ? $offset + count($items) : null, 'total' => $total ?: count($items)];
    }

    private function recordNode(array $record, array $all): array
    {
        return self::node(
            'flow:' . $record['id'],
            $record['from'] . ' → ' . $record['to'] . ' · ' . $record['stageLabel'],
            $record['amountCents'], true, 'document',
            $record['documentDate'] . ' · ' . $record['destinationLabel'] . ' · ' . $record['purpose'] . ' · Les étapes successives ne sont pas des montants à additionner.',
            $record['sourceUrl'],
        );
    }
}
