<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Arbre de délibération attaché exclusivement aux vraies propositions de loi.
 * La discussion libre est remplacée par un couple obligatoire cause / conséquence.
 * La délibération ne modifie jamais les bulletins, les dossiers ou les scrutins clos.
 */
final class PolicyDeliberation
{
    private const PAGE_SIZE = 40;

    private const MAX_DEPTH = 6;

    public function __construct(private readonly string $projectDir)
    {
    }

    private function database(): ?\PDO
    {
        $override = getenv('POPULORUM_POLICY_CATALOG_PATH');
        $path = is_string($override) && $override !== ''
            ? $override
            : $this->projectDir . '/var/policy-catalog.sqlite';
        if (!is_file($path)) {
            return null;
        }

        $db = new \PDO('sqlite:' . $path);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 5000');
        // Migration additive et idempotente des installations déjà en place.
        $db->exec(
            "CREATE TABLE IF NOT EXISTS policy_deliberations (
                id TEXT PRIMARY KEY,
                measure_id TEXT NOT NULL REFERENCES policy_measures(id),
                citizen_id TEXT NOT NULL,
                participant_label TEXT NOT NULL,
                parent_id TEXT REFERENCES policy_deliberations(id),
                depth INTEGER NOT NULL CHECK (depth BETWEEN 0 AND 6),
                cause_text TEXT NOT NULL,
                cause_degree TEXT NOT NULL
                    CHECK (cause_degree IN ('probable', 'observee', 'demontree')),
                cause_source TEXT NOT NULL DEFAULT '',
                consequence_text TEXT NOT NULL,
                consequence_degree TEXT NOT NULL
                    CHECK (consequence_degree IN ('probable', 'observee', 'demontree')),
                consequence_source TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL
            )",
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_policy_deliberations_measure
             ON policy_deliberations(measure_id, created_at DESC, id DESC)',
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_policy_deliberations_author
             ON policy_deliberations(measure_id, citizen_id)',
        );
        $fields = $db->query('PRAGMA table_info(policy_deliberations)')->fetchAll();
        if (!in_array('version', array_column($fields, 'name'), true)) {
            // Existing branches become version 1 without losing their original texts.
            $db->exec(
                'ALTER TABLE policy_deliberations ADD COLUMN version INTEGER NOT NULL DEFAULT 1',
            );
        }
        if (!in_array('updated_at', array_column($fields, 'name'), true)) {
            $db->exec("ALTER TABLE policy_deliberations ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
        }
        $db->exec(
            "CREATE TABLE IF NOT EXISTS policy_deliberation_revisions (
                entry_id TEXT NOT NULL REFERENCES policy_deliberations(id),
                version INTEGER NOT NULL CHECK (version > 0),
                cause_text TEXT NOT NULL,
                cause_degree TEXT NOT NULL,
                cause_source TEXT NOT NULL,
                consequence_text TEXT NOT NULL,
                consequence_degree TEXT NOT NULL,
                consequence_source TEXT NOT NULL,
                recorded_at TEXT NOT NULL,
                PRIMARY KEY (entry_id, version)
            )",
        );

        return $db;
    }

    /** Lecture publique uniquement après vote personnel ou clôture du scrutin. */
    public function page(
        string $measureId, bool $visible, int $number = 1, ?string $citizenId = null,
    ): array
    {
        $empty = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        if (!$visible) {
            return $empty;
        }
        $db = $this->database();
        if ($db === null) {
            return $empty;
        }
        $number = max(1, min(10000, $number));
        $count = $db->prepare(
            'SELECT COUNT(*) FROM policy_deliberations WHERE measure_id = ?',
        );
        $count->execute([$measureId]);
        $total = (int) $count->fetchColumn();
        $pages = (int) ceil($total / self::PAGE_SIZE);
        $number = $pages ? min($number, $pages) : 1;
        $query = $db->prepare(
            'SELECT d.id, d.parent_id, d.depth, d.participant_label, d.citizen_id,
                d.cause_text, d.cause_degree, d.cause_source,
                d.consequence_text, d.consequence_degree, d.consequence_source,
                d.created_at, d.updated_at, d.version,
                p.cause_text AS parent_cause
             FROM policy_deliberations d
             LEFT JOIN policy_deliberations p ON p.id = d.parent_id
             WHERE d.measure_id = ?
             ORDER BY d.created_at DESC, d.id DESC LIMIT ? OFFSET ?',
        );
        $query->bindValue(1, $measureId);
        $query->bindValue(2, self::PAGE_SIZE, \PDO::PARAM_INT);
        $query->bindValue(3, ($number - 1) * self::PAGE_SIZE, \PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();
        foreach ($rows as &$row) {
            $row['mine'] = $citizenId !== null
                && hash_equals($row['citizen_id'], $citizenId);
            unset($row['citizen_id']);
        }
        unset($row);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $number,
            'pages' => $pages,
        ];
    }

    public function entry(string $measureId, string $entryId): ?array
    {
        if (preg_match('/\Apc_[a-f0-9]{24}\z/D', $entryId) !== 1) {
            return null;
        }
        $db = $this->database();
        if ($db === null) {
            return null;
        }
        $query = $db->prepare(
            'SELECT id, cause_text, consequence_text, participant_label
             FROM policy_deliberations WHERE id = ? AND measure_id = ?',
        );
        $query->execute([$entryId, $measureId]);
        $entry = $query->fetch();

        return $entry === false ? null : $entry;
    }

    public function add(string $measureId, array $profile, array $payload): string
    {
        $citizenId = (string) ($profile['citizenId'] ?? '');
        if (preg_match('/\Abeta_[a-f0-9]{24}\z/D', $citizenId) !== 1
            || (int) ($profile['expiresAt'] ?? 0) <= time()) {
            throw new \DomainException('Un compte de test actif est nécessaire.', 403);
        }
        $causeText = self::statement($payload['causeText'] ?? null, 'Cause');
        $consequenceText = self::statement($payload['consequenceText'] ?? null, 'Conséquence');
        $causeDegree = self::degree($payload['causeDegree'] ?? null);
        $consequenceDegree = self::degree($payload['consequenceDegree'] ?? null);
        $causeSource = self::source($payload['causeSource'] ?? null);
        $consequenceSource = self::source($payload['consequenceSource'] ?? null);
        if ($causeDegree === 'demontree' && $causeSource === '') {
            throw new \DomainException('Une cause présentée comme démontrée doit comporter une source vérifiable.', 400);
        }
        if ($consequenceDegree === 'demontree' && $consequenceSource === '') {
            throw new \DomainException('Une conséquence présentée comme démontrée doit comporter une source vérifiable.', 400);
        }
        $parentId = trim((string) ($payload['parentId'] ?? ''));
        if ($parentId !== '' && preg_match('/\Apc_[a-f0-9]{24}\z/D', $parentId) !== 1) {
            throw new \DomainException('Référence de branche invalide.', 400);
        }

        $db = $this->database();
        if ($db === null) {
            throw new \DomainException('Catalogue des propositions de loi non installé.', 503);
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $query = $db->prepare(
                "SELECT territory_code FROM policy_measures
                 WHERE id = ? AND source_type = 'proposition_de_loi'",
            );
            $query->execute([$measureId]);
            $measure = $query->fetch();
            if ($measure === false) {
                throw new \DomainException('Proposition officielle inexistante.', 404);
            }
            if ($measure['territory_code'] !== 'FR'
                && $measure['territory_code'] !== ($profile['communeCode'] ?? '')) {
                throw new \DomainException('Participation non autorisée dans cette commune.', 403);
            }
            $vote = $db->prepare(
                'SELECT 1 FROM policy_votes WHERE measure_id = ? AND citizen_id = ?',
            );
            $vote->execute([$measureId, $citizenId]);
            if ($vote->fetchColumn() === false) {
                throw new \DomainException('Exprimez d’abord votre position avant de contribuer à l’arbre causal.', 403);
            }
            $count = $db->prepare(
                'SELECT COUNT(*) FROM policy_deliberations
                 WHERE measure_id = ? AND citizen_id = ?',
            );
            $count->execute([$measureId, $citizenId]);
            if ((int) $count->fetchColumn() >= 50) {
                throw new \DomainException('Limite de 50 branches par compte et proposition atteinte.', 429);
            }
            $depth = 0;
            if ($parentId !== '') {
                $parent = $db->prepare(
                    'SELECT depth FROM policy_deliberations
                     WHERE id = ? AND measure_id = ?',
                );
                $parent->execute([$parentId, $measureId]);
                $parentDepth = $parent->fetchColumn();
                if ($parentDepth === false) {
                    throw new \DomainException('La branche parent doit appartenir au même dossier.', 400);
                }
                $depth = (int) $parentDepth + 1;
                if ($depth > self::MAX_DEPTH) {
                    throw new \DomainException('Profondeur maximale atteinte : créez une nouvelle branche.', 400);
                }
            }
            $id = 'pc_' . bin2hex(random_bytes(12));
            $createdAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s.uP');
            $insert = $db->prepare(
                'INSERT INTO policy_deliberations (
                    id, measure_id, citizen_id, participant_label, parent_id, depth,
                    cause_text, cause_degree, cause_source,
                    consequence_text, consequence_degree, consequence_source, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $insert->execute([
                $id, $measureId, $citizenId,
                'Compte de test ' . substr($citizenId, -8),
                $parentId !== '' ? $parentId : null,
                $depth, $causeText, $causeDegree, $causeSource,
                $consequenceText, $consequenceDegree, $consequenceSource,
                $createdAt,
            ]);
            self::storeRevision($db, [
                'id' => $id,
                'cause_text' => $causeText,
                'cause_degree' => $causeDegree,
                'cause_source' => $causeSource,
                'consequence_text' => $consequenceText,
                'consequence_degree' => $consequenceDegree,
                'consequence_source' => $consequenceSource,
            ], 1, $createdAt);
            $db->commit();

            return $id;
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    /** A historical snapshot stores every published version, including its original. */
    private static function storeRevision(
        \PDO $db, array $entry, int $version, string $recordedAt, bool $initial = false,
    ): void {
        $sql = $initial ? 'INSERT OR IGNORE' : 'INSERT';
        $insert = $db->prepare(
            $sql . ' INTO policy_deliberation_revisions (
                entry_id, version, cause_text, cause_degree, cause_source,
                consequence_text, consequence_degree, consequence_source, recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([
            $entry['id'], $version,
            $entry['cause_text'], $entry['cause_degree'], $entry['cause_source'],
            $entry['consequence_text'], $entry['consequence_degree'],
            $entry['consequence_source'], $recordedAt,
        ]);
    }

    /** Only the active original author may revise, never a reader or another account. */
    public function editableEntry(string $measureId, string $entryId, ?array $profile): ?array
    {
        $citizenId = (string) ($profile['citizenId'] ?? '');
        if ($profile === null || (int) ($profile['expiresAt'] ?? 0) <= time()
            || preg_match('/\Abeta_[a-f0-9]{24}\z/D', $citizenId) !== 1
            || preg_match('/\Apc_[a-f0-9]{24}\z/D', $entryId) !== 1) {
            return null;
        }
        $db = $this->database();
        if ($db === null) {
            return null;
        }
        $query = $db->prepare(
            'SELECT d.id, d.version, d.cause_text, d.cause_degree, d.cause_source,
                d.consequence_text, d.consequence_degree, d.consequence_source
             FROM policy_deliberations d
             INNER JOIN policy_votes v
                ON v.measure_id = d.measure_id AND v.citizen_id = d.citizen_id
             WHERE d.id = ? AND d.measure_id = ? AND d.citizen_id = ?',
        );
        $query->execute([$entryId, $measureId, $citizenId]);
        $entry = $query->fetch();

        return $entry === false ? null : $entry;
    }

    /** Read-only historical snapshots, available under the same rules as the tree. */
    public function history(string $measureId, string $entryId, bool $visible): array
    {
        if (!$visible || preg_match('/\Apc_[a-f0-9]{24}\z/D', $entryId) !== 1) {
            return [];
        }
        $db = $this->database();
        if ($db === null) {
            return [];
        }
        $query = $db->prepare(
            'SELECT id, version, cause_text, cause_degree, cause_source,
                consequence_text, consequence_degree, consequence_source,
                created_at, updated_at
             FROM policy_deliberations WHERE id = ? AND measure_id = ?',
        );
        $query->execute([$entryId, $measureId]);
        $current = $query->fetch();
        if ($current === false) {
            return [];
        }
        $query = $db->prepare(
            'SELECT version, cause_text, cause_degree, cause_source,
                consequence_text, consequence_degree, consequence_source, recorded_at
             FROM policy_deliberation_revisions
             WHERE entry_id = ? ORDER BY version DESC',
        );
        $query->execute([$entryId]);
        $versions = $query->fetchAll();
        if ($versions === []) {
            // Backwards compatibility for pre-versioning branch records.
            $versions = [[
                'version' => 1,
                'cause_text' => $current['cause_text'],
                'cause_degree' => $current['cause_degree'],
                'cause_source' => $current['cause_source'],
                'consequence_text' => $current['consequence_text'],
                'consequence_degree' => $current['consequence_degree'],
                'consequence_source' => $current['consequence_source'],
                'recorded_at' => $current['created_at'],
            ]];
        }

        return $versions;
    }

    /** Atomic optimistic-concurrency update; previous text is immutable in revisions. */
    public function revise(
        string $measureId, string $entryId, array $profile, array $payload, int $expectedVersion,
    ): int {
        $citizenId = (string) ($profile['citizenId'] ?? '');
        if (preg_match('/\Abeta_[a-f0-9]{24}\z/D', $citizenId) !== 1
            || (int) ($profile['expiresAt'] ?? 0) <= time()) {
            throw new \DomainException('Compte de test actif requis.', 403);
        }
        if (preg_match('/\Apc_[a-f0-9]{24}\z/D', $entryId) !== 1) {
            throw new \DomainException('Identifiant de branche invalide.', 400);
        }
        if ($expectedVersion < 1) {
            throw new \DomainException('Version de départ invalide.', 400);
        }
        // Reuse the exact original evidence and statement requirements.
        $updated = [
            'cause_text' => self::statement($payload['causeText'] ?? null, 'Cause'),
            'cause_degree' => self::degree($payload['causeDegree'] ?? null),
            'cause_source' => self::source($payload['causeSource'] ?? null),
            'consequence_text' => self::statement($payload['consequenceText'] ?? null, 'Conséquence'),
            'consequence_degree' => self::degree($payload['consequenceDegree'] ?? null),
            'consequence_source' => self::source($payload['consequenceSource'] ?? null),
        ];
        if ($updated['cause_degree'] === 'demontree' && $updated['cause_source'] === '') {
            throw new \DomainException('Source obligatoire pour une cause présentée comme démontrée.', 400);
        }
        if ($updated['consequence_degree'] === 'demontree'
            && $updated['consequence_source'] === '') {
            throw new \DomainException('Source obligatoire pour une conséquence présentée comme démontrée.', 400);
        }
        $db = $this->database();
        if ($db === null) {
            throw new \DomainException('Catalogue non installé.', 503);
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $query = $db->prepare(
                "SELECT d.* FROM policy_deliberations d
                 INNER JOIN policy_measures m
                    ON m.id = d.measure_id AND m.source_type = 'proposition_de_loi'
                 WHERE d.id = ? AND d.measure_id = ?",
            );
            $query->execute([$entryId, $measureId]);
            $before = $query->fetch();
            if ($before === false) {
                throw new \DomainException('Branche causale introuvable.', 404);
            }
            if (!hash_equals($before['citizen_id'], $citizenId)) {
                throw new \DomainException('Seul l’auteur peut modifier sa branche.', 403);
            }
            $vote = $db->prepare(
                'SELECT 1 FROM policy_votes WHERE measure_id = ? AND citizen_id = ?',
            );
            $vote->execute([$measureId, $citizenId]);
            if ($vote->fetchColumn() === false) {
                throw new \DomainException('Votre vote préalable est requis.', 403);
            }
            $version = (int) $before['version'];
            if ($version !== $expectedVersion) {
                throw new \DomainException(
                    'Cette branche a déjà été modifiée : rechargez-la avant de corriger votre texte.', 409,
                );
            }
            $changed = false;
            foreach ($updated as $field => $value) {
                if ($before[$field] !== $value) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                throw new \DomainException('Aucune modification à enregistrer.', 400);
            }
            $before['id'] = $entryId;
            // Older branches have never had revision entries; archive v1 first.
            self::storeRevision($db, $before, $version, $before['updated_at'] ?: $before['created_at'], true);
            $newVersion = $version + 1;
            $updatedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s.uP');
            $change = $db->prepare(
                'UPDATE policy_deliberations SET
                    cause_text = ?, cause_degree = ?, cause_source = ?,
                    consequence_text = ?, consequence_degree = ?, consequence_source = ?,
                    version = ?, updated_at = ?
                 WHERE id = ? AND measure_id = ? AND citizen_id = ? AND version = ?',
            );
            $change->execute([
                $updated['cause_text'], $updated['cause_degree'], $updated['cause_source'],
                $updated['consequence_text'], $updated['consequence_degree'],
                $updated['consequence_source'], $newVersion, $updatedAt,
                $entryId, $measureId, $citizenId, $version,
            ]);
            if ($change->rowCount() !== 1) {
                throw new \DomainException('Modification concurrente : rechargez cette branche.', 409);
            }
            $updated['id'] = $entryId;
            self::storeRevision($db, $updated, $newVersion, $updatedAt);
            $db->commit();

            return $newVersion;
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function statement(mixed $raw, string $label): string
    {
        if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8')) {
            throw new \DomainException($label . ' : texte invalide.', 400);
        }
        $text = trim($raw);
        if (mb_strlen($text) < 25 || mb_strlen($text) > 1500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', $text)) {
            throw new \DomainException($label . ' : fournissez un raisonnement de 25 à 1 500 caractères.', 400);
        }

        return $text;
    }

    private static function degree(mixed $raw): string
    {
        if (!is_string($raw) || !in_array($raw, ['probable', 'observee', 'demontree'], true)) {
            throw new \DomainException('Degré de preuve obligatoire et invalide.', 400);
        }

        return $raw;
    }

    private static function source(mixed $raw): string
    {
        if (!is_string($raw)) {
            throw new \DomainException('La source doit être une URL HTTPS.', 400);
        }
        $url = trim($raw);
        if ($url === '') {
            return '';
        }
        $parsed = parse_url($url);
        if (strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false
            || $parsed === false || ($parsed['scheme'] ?? '') !== 'https'
            || empty($parsed['host']) || isset($parsed['user']) || isset($parsed['pass'])) {
            throw new \DomainException('Source invalide : indiquez une adresse HTTPS publique.', 400);
        }

        return $url;
    }
}
