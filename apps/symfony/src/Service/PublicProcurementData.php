<?php

declare(strict_types=1);

namespace App\Service;

/** Read-only DECP contract register. Amounts are contractual, NEVER payments. */
final class PublicProcurementData
{
    private ?\PDO $db = null;
    private bool $attempted = false;

    public function __construct(
        private readonly string $projectDir,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'cache.app')]
        private readonly ?\Symfony\Contracts\Cache\CacheInterface $publicCache = null,
    ) {
    }

    private function connection(): ?\PDO
    {
        if ($this->attempted) {
            return $this->db;
        }
        $this->attempted = true;
        $file = $this->projectDir . '/data/research/public-procurement.sqlite';
        if (!is_file($file)) {
            return null;
        }
        try {
            $pdo = new \PDO('sqlite:file:' . $file . '?mode=ro', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA query_only=ON');
            if ($pdo->query("SELECT value FROM metadata WHERE key='schema'")->fetchColumn() !== 'populorum-decp-2') {
                return null;
            }

            return $this->db = $pdo;
        } catch (\PDOException) {
            return null;
        }
    }

    public function coverage(): array
    {
        $db = $this->connection();
        if ($db === null) {
            return ['available' => false, 'contracts' => 0, 'markets' => 0, 'concessions' => 0,
                'events' => 0, 'files' => 0, 'lastImport' => null];
        }
        $load = static function () use ($db): array {
            $kinds = $db->query('SELECT kind, COUNT(*) AS n FROM contracts GROUP BY kind')->fetchAll();
            $counts = array_column($kinds, 'n', 'kind');

            return [
                'available' => true, 'contracts' => (int) array_sum($counts),
                'markets' => (int) ($counts['market'] ?? 0), 'concessions' => (int) ($counts['concession'] ?? 0),
                'events' => (int) $db->query('SELECT COUNT(*) FROM changes')->fetchColumn(),
                'files' => (int) $db->query('SELECT COUNT(*) FROM imports')->fetchColumn(),
                'lastImport' => $db->query('SELECT MAX(imported_at) FROM imports')->fetchColumn() ?: null,
            ];
        };
        if ($this->publicCache === null) {
            return $load();
        }
        // Cache only aggregate public reference data, never contracts, identities or sessions.
        // File metadata changes when a new national dataset replaces the old one.
        $file = $this->projectDir . '/data/research/public-procurement.sqlite';
        $key = 'decp_coverage_' . hash('sha256', $file . ':' . filemtime($file) . ':' . filesize($file));

        return $this->publicCache->get($key, static function ($item) use ($load): array {
            $item->expiresAfter(300);

            return $load();
        });
    }

    public function years(string $kind): array
    {
        if (!in_array($kind, ['market', 'concession'], true) || !($db = $this->connection())) {
            return [];
        }
        $query = $db->prepare("SELECT SUBSTR(notice_date,1,4) AS year, COUNT(*) AS n
            FROM contracts WHERE kind=? AND notice_date GLOB '[12][0-9][0-9][0-9]-*'
            GROUP BY year ORDER BY year DESC LIMIT 100");
        $query->execute([$kind]);

        return $query->fetchAll();
    }

    /** Exact commune attribution ONLY where the source explicitly uses Code commune.
     * Postal codes/departments never become an inferred municipal contract.
     */
    public function cityCount(string $insee): int
    {
        if (!preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $insee) || !($db = $this->connection())) {
            return 0;
        }
        $q = $db->prepare("SELECT COUNT(*) FROM contracts WHERE location_type='Code commune' AND location_code=?");
        $q->execute([$insee]);

        return (int) $q->fetchColumn();
    }

    /**
     * Classify a source-declared notification date without rewriting it.
     * The reason is a diagnostic inferred by Populorum, not a justification from the buyer.
     *
     * @return array{valid: bool, raw: string, reason: string}
     */
    public static function dateQuality(string $value): array
    {
        $raw = trim($value);
        if ($raw === '') {
            return ['valid' => false, 'raw' => $raw, 'reason' => 'Date non renseignée'];
        }
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $raw) !== 1) {
            return ['valid' => false, 'raw' => $raw, 'reason' => 'Format de date non reconnu'];
        }
        [$year, $month, $day] = array_map('intval', explode('-', $raw));
        if (!checkdate($month, $day, $year)) {
            return ['valid' => false, 'raw' => $raw, 'reason' => 'Date calendaire impossible'];
        }
        if ($year >= 2100) {
            return ['valid' => false, 'raw' => $raw, 'reason' => 'Année manifestement incohérente'];
        }
        if ($year < 1900) {
            return ['valid' => false, 'raw' => $raw, 'reason' => 'Année très ancienne pour une notification'];
        }
        if ($raw > gmdate('Y-m-d')) {
            return ['valid' => false, 'raw' => $raw, 'reason' => 'Date de notification future, à vérifier'];
        }

        return ['valid' => true, 'raw' => $raw, 'reason' => ''];
    }

    /**
     * A warning is a research lead, not a correction or an exclusion.
     * Exact source amounts, even anomalous ones, must remain visible.
     */
    public static function amountWarnings(int|string|null $cents, string $locationType = '', string $locationCode = ''): array
    {
        $warnings = [];
        if ($cents !== null) {
            $euros = intdiv((int) $cents, 100);
            if ($euros < 0) {
                $warnings[] = 'Montant négatif déclaré : vérifier la nature de la correction ou de la saisie.';
            }
            if ($euros >= 1000000000 && preg_match('/9{7,}/', (string) $euros) === 1) {
                $warnings[] = 'Suite de chiffres 9 dans un montant très élevé : vérifier un éventuel montant conventionnel ou une saisie atypique.';
            } elseif ($euros >= 10000000000) {
                $warnings[] = 'Montant supérieur à dix milliards d’euros : vérifier son périmètre et la pièce contractuelle.';
            }
        }
        if ($locationType === 'Code postal' && preg_match('/\A[0-9]{5}\z/D', $locationCode) !== 1) {
            $warnings[] = 'Le lieu est déclaré comme code postal, mais son format n’est pas celui d’un code postal français à cinq chiffres.';
        }

        return $warnings;
    }

    /** Server-side pagination; query fields are never interpolated into SQL. */
    public function search(array $filters, int $offset = 0, int $limit = 30): array
    {
        $db = $this->connection();
        $limit = max(1, min(60, $limit));
        $offset = max(0, min(200000, $offset));
        if (!$db) {
            return ['items' => [], 'total' => 0, 'offset' => $offset, 'nextOffset' => null];
        }
        $where = [];
        $args = [];
        $kind = (string) ($filters['kind'] ?? '');
        if (in_array($kind, ['market', 'concession'], true)) {
            $where[] = 'c.kind=?';
            $args[] = $kind;
        }
        $year = (string) ($filters['year'] ?? '');
        if (preg_match('/\A[0-9]{4}\z/D', $year)) {
            $where[] = 'c.notice_date>=? AND c.notice_date<?';
            $args[] = $year . '-01-01';
            $args[] = ((int) $year + 1) . '-01-01';
        }
        $buyer = (string) ($filters['buyer'] ?? '');
        if (preg_match('/\A[0-9]{9}(?:[0-9]{5})?\z/', $buyer)) {
            $where[] = '(c.buyer_id=? OR SUBSTR(c.buyer_id,1,9)=?)';
            $args[] = $buyer;
            $args[] = substr($buyer, 0, 9);
        }
        $supplier = (string) ($filters['supplier'] ?? '');
        if (preg_match('/\A[0-9]{9}(?:[0-9]{5})?\z/', $supplier)) {
            $where[] = 'EXISTS (SELECT 1 FROM participants p WHERE p.contract_key=c.contract_key AND (p.identifier=? OR SUBSTR(p.identifier,1,9)=?))';
            $args[] = $supplier;
            $args[] = substr($supplier, 0, 9);
        }
        $city = strtoupper((string) ($filters['city'] ?? ''));
        if (preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $city)) {
            $where[] = "c.location_type='Code commune' AND c.location_code=?";
            $args[] = $city;
        }
        $department = strtoupper((string) ($filters['department'] ?? ''));
        if (preg_match('/\A(?:[0-9]{2,3}|2[AB])\z/D', $department)) {
            $where[] = "c.location_type='Code département' AND c.location_code=?";
            $args[] = $department;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if (strlen($q) >= 2 && strlen($q) <= 120) {
            // FTS5: accent-insensitive search, indexed for nationwide volume.
            // Values are quoted as FTS literals; never interpolate into SQL.
            preg_match_all('/[\p{L}\p{N}]{2,}/u', $q, $tokens);
            $words = array_slice($tokens[0], 0, 8);
            if ($words !== []) {
                $where[] = 'c.rowid IN (SELECT rowid FROM contract_fts WHERE contract_fts MATCH ?)';
                $args[] = implode(' AND ', array_map(
                    static fn (string $term): string => '"' . str_replace('"', '', $term) . '"*',
                    $words,
                ));
            }
        }
        $condition = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $total = PerformanceTrace::measure('decp_count', function () use ($db, $where, $kind, $condition, $args): int {
            // The controller already loads public coverage on the markets page.
            // Unfiltered or kind-only totals come from that cached aggregate;
            // do not run an additional national COUNT(*) on every page visit.
            if ($where === [] || $where === ['c.kind=?']) {
                $coverage = $this->coverage();

                return (int) ($where === []
                    ? $coverage['contracts']
                    : ($kind === 'market' ? $coverage['markets'] : $coverage['concessions']));
            }
            $count = $db->prepare('SELECT COUNT(*) FROM contracts c' . $condition);
            $count->execute($args);

            return (int) $count->fetchColumn();
        });
        // Only known SQL fragments are allowed; dates and amounts remain unaltered.
        $sort = (string) ($filters['sort'] ?? 'date_desc');
        $orderBy = match ($sort) {
            'date_asc' => 'c.notice_date ASC,c.contract_key ASC',
            'amount_desc' => 'CASE WHEN c.amount_initial_cents IS NULL THEN 1 ELSE 0 END ASC,c.amount_initial_cents DESC,c.contract_key',
            'amount_asc' => 'CASE WHEN c.amount_initial_cents IS NULL THEN 1 ELSE 0 END ASC,c.amount_initial_cents ASC,c.contract_key',
            'date_anomalies' => "CASE WHEN c.notice_date < '1900-01-01' OR c.notice_date > DATE('now')
                OR c.notice_date NOT GLOB '[0-9][0-9][0-9][0-9]-[01][0-9]-[0-3][0-9]'
                THEN 0 ELSE 1 END ASC,c.notice_date DESC,c.contract_key",
            default => 'c.notice_date DESC,c.contract_key',
        };
        $sql = 'SELECT c.contract_key,c.kind,c.buyer_id,c.original_id,c.object,c.cpv,c.nature,c.notice_date,
            c.amount_initial_cents,c.amount_amended_cents,c.location_type,c.location_code,c.source_name,c.source_sha,
            (SELECT i.source_url FROM imports i WHERE i.sha256=c.source_sha LIMIT 1) AS source_url,
            (SELECT i.filename FROM imports i WHERE i.sha256=c.source_sha LIMIT 1) AS source_filename,
            (SELECT COUNT(*) FROM contract_sources cs WHERE cs.contract_key=c.contract_key) AS source_count
            FROM contracts c' . $condition . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?';
        $rows = PerformanceTrace::measure('decp_rows', static function () use ($db, $sql, $args, $limit, $offset): array {
            $stmt = $db->prepare($sql);
            foreach ($args as $i => $v) {
                $stmt->bindValue($i + 1, $v, \PDO::PARAM_STR);
            }
            $stmt->bindValue(count($args) + 1, $limit, \PDO::PARAM_INT);
            $stmt->bindValue(count($args) + 2, $offset, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        });
        foreach ($rows as &$row) {
            $row['date_quality'] = self::dateQuality((string) $row['notice_date']);
            $row['amount_warnings'] = self::amountWarnings(
                $row['amount_initial_cents'], (string) $row['location_type'], (string) $row['location_code'],
            );
        }
        unset($row);

        return ['items' => $rows, 'total' => $total, 'offset' => $offset,
            'nextOffset' => $offset + count($rows) < $total ? $offset + count($rows) : null];
    }

    public function detail(string $key): ?array
    {
        if (!preg_match('/\A[a-f0-9]{40}\z/D', $key) || !($db = $this->connection())) {
            return null;
        }
        $stmt = $db->prepare('SELECT * FROM contracts WHERE contract_key=?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $stmt = $db->prepare('SELECT role,identifier_type,identifier FROM participants WHERE contract_key=? ORDER BY role,identifier LIMIT 200');
        $stmt->execute([$key]);
        $row['participants'] = $stmt->fetchAll();
        $row['date_quality'] = self::dateQuality((string) $row['notice_date']);
        $row['amount_warnings'] = self::amountWarnings(
            $row['amount_initial_cents'], (string) $row['location_type'], (string) $row['location_code'],
        );
        $stmt = $db->prepare('SELECT event_type,event_id,event_date,published_date,amount_cents,identifier,extra FROM changes WHERE contract_key=? ORDER BY event_date,event_id LIMIT 300');
        $stmt->execute([$key]);
        $row['events'] = $stmt->fetchAll();
        $stmt = $db->prepare('SELECT i.filename,i.source_url,i.sha256,i.imported_at FROM contract_sources cs JOIN imports i ON cs.source_sha=i.sha256 WHERE cs.contract_key=? ORDER BY i.filename DESC LIMIT 80');
        $stmt->execute([$key]);
        $row['sources'] = $stmt->fetchAll();
        $row['history'] = $this->publicationHistory($db, $key);
        // Similar buyer-issued references are linked for investigation, never
        // silently merged: different lots can reuse an identical original ID.
        $row['reference_relations'] = PerformanceTrace::measure('decp_related_references', fn () => $this->relatedReferences($db, $row));
        $row['financial_evidence'] = $this->financialEvidence($key);
        $row['automatic_concordance'] = $this->automaticConcordance($key);
        // Statements are curated and source-linked, never inferred from the DECP
        // contract amount. Candidate BOAMP matches cannot assert any factual claim.
        $stmt = $db->prepare('SELECT category,source_type,source_name,source_url,document_title,
            statement,excerpt,published_at,recorded_at,status
            FROM procurement_evidence WHERE contract_key=? ORDER BY status ASC,category,document_title LIMIT 100');
        $stmt->execute([$key]);
        $row['evidence'] = $stmt->fetchAll();
        $stmt = $db->prepare('SELECT source_sha,kind,ordinal,record_sha
            FROM contract_records WHERE contract_key=? ORDER BY source_sha,kind,ordinal LIMIT 100');
        $stmt->execute([$key]);
        $row['original_records'] = $stmt->fetchAll();
        // One authentic original declaration is available for full inspection.
        // The index stores all occurrences; rendering remains bounded.
        $stmt = $db->prepare('SELECT cr.record_zlib,cr.record_sha,i.filename,i.source_url,i.sha256
            FROM contract_records cr JOIN imports i ON i.sha256=cr.source_sha
            WHERE cr.contract_key=? ORDER BY i.filename DESC,cr.kind,cr.ordinal LIMIT 1');
        $stmt->execute([$key]);
        $original = $stmt->fetch();
        $row['original_declaration_source'] = $original ? [
            'filename' => $original['filename'],
            'source_url' => $original['source_url'],
            'sha256' => $original['sha256'],
            'record_sha' => $original['record_sha'],
        ] : null;
        $compressed = $original['record_zlib'] ?? null;
        $row['source_declaration'] = null;
        if (is_string($compressed)) {
            $json = @gzuncompress($compressed, 2_000_000);
            if (is_string($json)) {
                try {
                    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($payload)) {
                        $row['source_declaration'] = $payload;
                    }
                } catch (\JsonException) {
                    // An unreadable source declaration must not affect the public page.
                }
            }
        }
        if ($row['kind'] === 'concession') {
            $stmt = $db->prepare('SELECT * FROM concession_finance WHERE contract_key=?');
            $stmt->execute([$key]);
            $row['concession_finance'] = $stmt->fetch() ?: null;
        }

        return $row;
    }

    /**
     * Documentary references with the same buyer, kind and published identifier.
     * They are NOT asserted to be the same legal contract or the same lot.
     */
    private function relatedReferences(\PDO $db, array $row): array
    {
        if (trim((string) $row['original_id']) === '' || trim((string) $row['buyer_id']) === '') {
            return ['items' => [], 'more' => false, 'indexed' => true];
        }

        // This optional enrichment must never turn a detail request into a
        // multi-second scan of the national DECP index (hundreds of thousands
        // of contracts). Pre-v0.12.5 databases lack the composite index.
        $indexed = (bool) $db->query("SELECT 1 FROM sqlite_master
            WHERE type='index' AND tbl_name='contracts'
              AND name='contracts_buyer_reference' LIMIT 1")->fetchColumn();
        if (!$indexed) {
            return ['items' => [], 'more' => false, 'indexed' => false];
        }
        $query = $db->prepare('SELECT contract_key,object,original_id,notice_date,cpv,
                    amount_initial_cents,location_type,location_code
                FROM contracts
                WHERE buyer_id=? AND kind=? AND original_id=? AND contract_key<>?
                ORDER BY notice_date DESC,contract_key LIMIT 31');
        $query->execute([$row['buyer_id'], $row['kind'], $row['original_id'], $row['contract_key']]);
        $items = $query->fetchAll();
        $more = count($items) > 30;

        return ['items' => array_slice($items, 0, 30), 'more' => $more, 'indexed' => true];
    }

    /**
     * Optional, independent ledger of separately sourced financial documents.
     * Never infer a payment from DECP amounts, calculate a sum of snapshots,
     * or write to the original national DECP database.
     */
    private function financialEvidence(string $key): array
    {
        $file = $this->projectDir . '/data/research/procurement-financial-evidence.sqlite';
        if (!is_file($file)) {
            return [];
        }
        try {
            $db = new \PDO('sqlite:file:' . $file . '?mode=ro', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $db->exec('PRAGMA query_only=ON');
            if ($db->query("SELECT value FROM metadata WHERE key='schema'")->fetchColumn() !== 'populorum-procurement-financial-1') {
                return [];
            }
            $stmt = $db->prepare('SELECT stage,amount_cents,accounting_scope,as_of_date,
                    period_start,period_end,document_date,source_issuer,
                    document_title,source_url,source_excerpt,recorded_at
                    FROM financial_evidence WHERE contract_key=?
                    ORDER BY as_of_date DESC,recorded_at DESC LIMIT 100');
            $stmt->execute([$key]);

            return $stmt->fetchAll();
        } catch (\PDOException) {
            // A missing or incompatible optional sidecar must never hide DECP.
            return [];
        }
    }

    /** Read-only match results from an offline official BOAMP enrichment job.
     * A matching notice establishes only a documentary link to a procedure,
     * never the executed cost of a lot, an invoice, or a payment.
     */
    private function automaticConcordance(string $key): array
    {
        $empty = ['status' => 'not_run', 'checked_at' => null, 'confirmed' => [], 'candidates' => [], 'conflicts' => [], 'phase_alerts' => [], 'history' => [], 'scan_history' => []];
        $file = $this->projectDir . '/data/research/procurement-auto-concordance.sqlite';
        if (!is_file($file)) {
            return $empty;
        }
        try {
            $db = new \PDO('sqlite:file:' . $file . '?mode=ro', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $db->exec('PRAGMA query_only=ON');
            if ($db->query("SELECT value FROM metadata WHERE key='schema'")->fetchColumn() !== 'populorum-boamp-concordance-1') {
                return $empty;
            }
            // Prefer the locally indexed national BOAMP corpus when this
            // contract was processed. Never download notices during HTTP.
            $hasLocal = $db->query("SELECT 1 FROM sqlite_master
                WHERE type='table' AND name='local_checks'")->fetchColumn();
            if ($hasLocal) {
                $localCheck = $db->prepare('SELECT checked_at,status,scanned_notices,
                    matched_notices,truncated,source_revision
                    FROM local_checks WHERE contract_key=?');
                $localCheck->execute([$key]);
                $local = $localCheck->fetch();
                if ($local) {
                    $localQuery = $db->prepare("SELECT idweb,status,basis,title,url,
                        publication_date,notice_kind,notice_sha AS notice_sha256,
                        '' AS notice_total_eur,first_seen_at,last_seen_at
                        FROM local_matches WHERE contract_key=? AND active=1
                        ORDER BY status,last_seen_at DESC,idweb LIMIT 80");
                    $localQuery->execute([$key]);
                    $confirmed = [];
                    $candidates = [];
                    $conflicts = [];
                    $phaseAlerts = [];
                    $ids = [];
                    foreach ($localQuery->fetchAll() as $notice) {
                        $ids[$notice['idweb']] = true;
                        if ($notice['status'] === 'procedure_ids') {
                            $confirmed[] = $notice;
                        } elseif ($notice['status'] === 'object_conflict') {
                            $conflicts[] = $notice;
                        } elseif ($notice['status'] === 'phase_conflict') {
                            $phaseAlerts[] = $notice;
                        } else {
                            $candidates[] = $notice;
                        }
                    }
                    // Old API-derived evidence must not disappear merely
                    // because a national import was subsequently performed.
                    $legacyQuery = $db->prepare('SELECT idweb,status,basis,title,url,
                        publication_date,notice_kind,notice_sha256,notice_total_eur,
                        first_seen_at,last_seen_at FROM reference_matches
                        WHERE contract_key=? ORDER BY status,last_seen_at DESC LIMIT 80');
                    $legacyQuery->execute([$key]);
                    foreach ($legacyQuery->fetchAll() as $notice) {
                        if (isset($ids[$notice['idweb']])) {
                            continue;
                        }
                        $notice['basis'] = 'Ancien rapprochement individuel : ' . $notice['basis'];
                        if ($notice['status'] === 'procedure_ids') {
                            $confirmed[] = $notice;
                        } else {
                            $candidates[] = $notice;
                        }
                    }

                    // Display the verified algorithm-version timeline, if installed.
                    // Older sidecars have no such table and remain readable.
                    $history = [];
                    $hasHistory = $db->query("SELECT 1 FROM sqlite_master
                        WHERE type='table' AND name='local_decision_history'")->fetchColumn();
                    if ($hasHistory) {
                        $timeline = $db->prepare('SELECT idweb,event_type,classification,
                            algorithm_version,algorithm_sha256,notice_sha,source_revision,
                            corpus_status,basis,factors_json,recorded_at
                            FROM local_decision_history WHERE contract_key=?
                            ORDER BY event_id DESC LIMIT 25');
                        $timeline->execute([$key]);
                        $history = $timeline->fetchAll();
                    }

                    // The scan log also covers zero-candidate contracts.
                    // Historical databases lacking this table remain readable.
                    $scanHistory = [];
                    $hasScanHistory = $db->query("SELECT 1 FROM sqlite_master
                        WHERE type='table' AND name='local_scan_history'")->fetchColumn();
                    if ($hasScanHistory) {
                        $scanTimeline = $db->prepare('SELECT scan_id,result,
                            algorithm_version,algorithm_sha256,source_revision,
                            corpus_status,scanned_notices,matched_notices,truncated,
                            recorded_at FROM local_scan_history WHERE contract_key=?
                            ORDER BY scan_id DESC LIMIT 10');
                        $scanTimeline->execute([$key]);
                        $scanHistory = $scanTimeline->fetchAll();
                    }

                    return ['status' => $local['status'],
                        'checked_at' => $local['checked_at'],
                        'confirmed' => $confirmed,
                        'candidates' => $candidates,
                        'conflicts' => $conflicts,
                        'phase_alerts' => $phaseAlerts,
                        'source' => 'index_national_local',
                        'scanned_notices' => (int) $local['scanned_notices'],
                        'truncated' => (bool) $local['truncated'],
                        'source_revision' => $local['source_revision'],
                        'history' => $history,
                        'scan_history' => $scanHistory];
                }
            }

            $query = $db->prepare('SELECT checked_at,status FROM checks WHERE contract_key=?');
            $query->execute([$key]);
            $check = $query->fetch();
            if (!$check) {
                return $empty;
            }
            $query = $db->prepare('SELECT idweb,status,basis,title,url,publication_date,notice_kind,
                       notice_sha256,notice_total_eur,first_seen_at,last_seen_at
                       FROM reference_matches WHERE contract_key=?
                       ORDER BY status, last_seen_at DESC,idweb LIMIT 40');
            $query->execute([$key]);
            $confirmed = [];
            $candidates = [];
            foreach ($query->fetchAll() as $notice) {
                if ($notice['status'] === 'procedure_ids') {
                    $confirmed[] = $notice;
                } else {
                    $candidates[] = $notice;
                }
            }

            return ['status' => $check['status'], 'checked_at' => $check['checked_at'],
                'confirmed' => $confirmed, 'candidates' => $candidates];
        } catch (\PDOException) {
            // Auxiliary enrichment failure must never break the national register.
            return $empty;
        }
    }

    /** Comparison of contract *snapshots*; not a financial commitment/payments reconciliation.
     * Earlier files may omit amounts. Sorting is by consolidated file's period,
     * not by the sometimes inconsistent datePublicationDonnees inside old records.
     */
    private function publicationHistory(\PDO $db, string $contractKey): array
    {
        $count = $db->prepare('SELECT COUNT(*) FROM contract_sources WHERE contract_key=?');
        $count->execute([$contractKey]);
        $references = (int) $count->fetchColumn();
        $exists = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='contract_publications'")->fetchColumn();
        if (!$exists) {
            return ['references' => $references, 'observed' => 0, 'complete' => false,
                'first' => null, 'last' => null, 'delta' => null, 'items' => []];
        }
        $stmt = $db->prepare('SELECT p.declared_publication_date, p.notice_date, p.base_amount_cents,
                p.modification_amount_cents,p.occurrences,i.filename,i.source_url,i.sha256
            FROM contract_publications p
            JOIN imports i ON i.sha256=p.source_sha
            WHERE p.contract_key=?
            ORDER BY i.filename ASC,i.sha256 ASC LIMIT 200');
        $stmt->execute([$contractKey]);
        $items = $stmt->fetchAll();
        $first = null;
        $last = null;
        $known = 0;
        foreach ($items as $item) {
            if ($item['base_amount_cents'] !== null) {
                $current = (int) $item['base_amount_cents'];
                $first ??= $current;
                $last = $current;
                ++$known;
            }
        }
        $complete = count($items) === $references;

        return [
            'references' => $references, 'observed' => count($items), 'complete' => $complete,
            'first' => $first, 'last' => $last,
            'delta' => $complete && $known >= 2 ? $last - $first : null,
            'items' => $items,
        ];
    }

    /** Side-by-side coverage indicators only. Amounts in OFGL and DECP
     * represent incompatible concepts and MUST NOT be summed or subtracted.
     */
    public function budgetConcordance(string $scope, string $accountingYear = '2025'): array
    {
        $db = $this->connection();
        $result = ['available' => $db !== null, 'scope' => $scope,
            'accountingYear' => $accountingYear, 'contractsInYear' => null,
            'contractsAllYears' => null, 'scopeType' => $scope === 'FR' ? 'national' : 'exact_place'];
        if ($db === null) {
            return $result;
        }
        $args = [];
        $where = '';
        if ($scope !== 'FR') {
            if (!preg_match('/\A(?:[0-9]{5}|2[AB][0-9]{3})\z/D', $scope)) {
                return $result;
            }
            // Execution geography, NOT the buyer's headquarters nor the local authority.
            $where = "WHERE location_type='Code commune' AND location_code=?";
            $args[] = $scope;
        }
        $all = $db->prepare('SELECT COUNT(*) FROM contracts ' . $where);
        $all->execute($args);
        $result['contractsAllYears'] = (int) $all->fetchColumn();
        $yearClause = 'notice_date>=? AND notice_date<?';
        $yearWhere = $where === '' ? ' WHERE ' . $yearClause : $where . ' AND ' . $yearClause;
        $year = $db->prepare('SELECT COUNT(*) FROM contracts ' . $yearWhere);
        $year->execute(array_merge($args, [$accountingYear . '-01-01', ((int) $accountingYear + 1) . '-01-01']));
        $result['contractsInYear'] = (int) $year->fetchColumn();

        return $result;
    }
}
