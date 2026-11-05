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
        $count = $db->prepare('SELECT COUNT(*) FROM contracts c' . $condition);
        $count->execute($args);
        $total = (int) $count->fetchColumn();
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
            c.amount_initial_cents,c.amount_amended_cents,c.location_type,c.location_code,c.source_name,
            (SELECT COUNT(*) FROM contract_sources cs WHERE cs.contract_key=c.contract_key) AS source_count
            FROM contracts c' . $condition . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?';
        $stmt = $db->prepare($sql);
        foreach ($args as $i => $v) {
            $stmt->bindValue($i + 1, $v, \PDO::PARAM_STR);
        }
        $stmt->bindValue(count($args) + 1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(count($args) + 2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['date_quality'] = self::dateQuality((string) $row['notice_date']);
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
        $stmt = $db->prepare('SELECT event_type,event_id,event_date,published_date,amount_cents,identifier,extra FROM changes WHERE contract_key=? ORDER BY event_date,event_id LIMIT 300');
        $stmt->execute([$key]);
        $row['events'] = $stmt->fetchAll();
        $stmt = $db->prepare('SELECT i.filename,i.source_url,i.sha256,i.imported_at FROM contract_sources cs JOIN imports i ON cs.source_sha=i.sha256 WHERE cs.contract_key=? ORDER BY i.filename DESC LIMIT 80');
        $stmt->execute([$key]);
        $row['sources'] = $stmt->fetchAll();
        $row['history'] = $this->publicationHistory($db, $key);
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
        if ($row['kind'] === 'concession') {
            $stmt = $db->prepare('SELECT * FROM concession_finance WHERE contract_key=?');
            $stmt->execute([$key]);
            $row['concession_finance'] = $stmt->fetch() ?: null;
        }

        return $row;
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
