<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Catalogue civique séparé de la base nationale et du laboratoire historique.
 * Un compte de test = une famille expérimentale d'un membre ; aucun vote réel.
 * Les sources et acteurs ne sont jamais transmis pour une consultation ouverte.
 */
final class PolicyCatalog
{
    private const PAGE_SIZE = 20;

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

        return $db;
    }

    /**
     * Catalogue national paginé ; peut servir à l'exploration ou au tableau de vote.
     * Les dossiers de programme, textes fictifs et auteurs restent hors des bulletins.
     *
     * @return array{rows: array, total: int, page: int, pages: int, available: bool}
     */
    public function page(
        int $number = 1,
        string $search = '',
        string $territory = '',
        string $category = '',
        string $status = 'all',
        ?string $citizenId = null,
        string $participation = 'all',
    ): array {
        $db = $this->database();
        if ($db === null) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'available' => false];
        }
        $number = max(1, min(10000, $number));
        $now = gmdate('Y-m-d\TH:i:s+00:00');
        $where = ["m.source_type = 'proposition_de_loi'"];
        $params = [];
        if ($search !== '') {
            $where[] = "m.label LIKE :search ESCAPE '!'";
            $params[':search'] = '%' . str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                mb_substr($search, 0, 100),
            ) . '%';
        }
        if (in_array($territory, ['FR', '75056'], true)) {
            $where[] = 'm.territory_code = :territory';
            $params[':territory'] = $territory;
        }
        if ($category !== '') {
            $where[] = 'm.category = :category';
            $params[':category'] = mb_substr($category, 0, 80);
        }
        if ($status === 'open') {
            $where[] = "m.status = 'open' AND m.closes_at > :now";
            $params[':now'] = $now;
        } elseif ($status === 'closed') {
            $where[] = "(m.status <> 'open' OR m.closes_at <= :now)";
            $params[':now'] = $now;
        }

        // La jointure ne révèle que la position du compte actif ; jamais celle d'un tiers.
        $identified = is_string($citizenId)
            && preg_match('/\Abeta_[a-f0-9]{24}\z/D', $citizenId) === 1;
        $join = '';
        $voteColumn = 'NULL AS my_choice';
        if ($identified) {
            $join = ' LEFT JOIN policy_votes v ON v.measure_id = m.id AND v.citizen_id = :citizen_id';
            $voteColumn = 'v.choice AS my_choice';
            $params[':citizen_id'] = $citizenId;
            if ($participation === 'voted') {
                $where[] = 'v.choice IS NOT NULL';
            } elseif ($participation === 'pending') {
                $where[] = 'v.choice IS NULL';
            }
        }
        $from = ' FROM policy_measures m' . $join . ' WHERE ' . implode(' AND ', $where);
        $count = $db->prepare('SELECT COUNT(*)' . $from);
        foreach ($params as $key => $value) {
            $count->bindValue($key, $value);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $pages = (int) ceil($total / self::PAGE_SIZE);
        $number = $pages ? min($number, $pages) : 1;
        $query = $db->prepare(
            'SELECT m.id, m.label, m.category, m.territory_code, m.closes_at,
                    m.status, m.source_type, m.source_url, m.source_date,
                    m.source_reference, m.origin_actor, m.legal_status,
                    m.review_status, ' . $voteColumn . $from .
            ' ORDER BY m.category, m.label, m.id LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) {
            $query->bindValue($key, $value);
        }
        $query->bindValue(':limit', self::PAGE_SIZE, \PDO::PARAM_INT);
        $query->bindValue(':offset', ($number - 1) * self::PAGE_SIZE, \PDO::PARAM_INT);
        $query->execute();
        $rows = [];
        foreach ($query->fetchAll() as $row) {
            $public = $this->publicRecord($db, $row);
            $public['myChoice'] = $row['my_choice'];
            $rows[] = $public;
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $number, 'pages' => $pages, 'available' => true];
    }

    /** Catégories effectivement présentes dans les propositions officielles. */
    public function categories(): array
    {
        $db = $this->database();
        if ($db === null) {
            return [];
        }

        return $db->query(
            "SELECT DISTINCT category FROM policy_measures
             WHERE source_type = 'proposition_de_loi' ORDER BY category",
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Indicateurs agrégés, sans consultation des identités d'autres participants. */
    public function summary(?array $profile): array
    {
        $db = $this->database();
        if ($db === null) {
            return ['total' => 0, 'open' => 0, 'closed' => 0, 'voted' => 0, 'pending' => 0];
        }
        $now = gmdate('Y-m-d\TH:i:s+00:00');
        $query = $db->prepare(
            "SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'open' AND closes_at > ?
                    THEN 1 ELSE 0 END), 0) AS open
             FROM policy_measures WHERE source_type = 'proposition_de_loi'",
        );
        $query->execute([$now]);
        $totals = $query->fetch();
        $total = (int) $totals['total'];
        $open = (int) $totals['open'];
        $voted = 0;
        $votedOpen = 0;
        $citizenId = $profile['citizenId'] ?? null;
        if (is_string($citizenId)
            && preg_match('/\Abeta_[a-f0-9]{24}\z/D', $citizenId) === 1) {
            $query = $db->prepare(
                "SELECT COUNT(*) AS voted,
                    COALESCE(SUM(CASE WHEN m.status = 'open' AND m.closes_at > ?
                        THEN 1 ELSE 0 END), 0) AS voted_open
                 FROM policy_votes v
                 INNER JOIN policy_measures m ON m.id = v.measure_id
                 WHERE m.source_type = 'proposition_de_loi' AND v.citizen_id = ?",
            );
            $query->execute([$now, $citizenId]);
            $mine = $query->fetch();
            $voted = (int) $mine['voted'];
            $votedOpen = (int) $mine['voted_open'];
        }

        return [
            'total' => $total,
            'open' => $open,
            'closed' => $total - $open,
            'voted' => $voted,
            'pending' => max(0, $open - $votedOpen),
        ];
    }

    public function measure(string $id): ?array
    {
        $db = $this->database();
        if ($db === null) {
            return null;
        }
        $query = $db->prepare("SELECT * FROM policy_measures WHERE id = ? AND source_type = 'proposition_de_loi'");
        $query->execute([$id]);
        $row = $query->fetch();

        return $row === false ? null : $this->publicRecord($db, $row);
    }

    private function publicRecord(\PDO $db, array $row): array
    {
        $now = gmdate('Y-m-d\TH:i:s+00:00');
        $closed = $row['status'] !== 'open' || $row['closes_at'] <= $now;
        $public = [
            'id' => $row['id'],
            'label' => $row['label'],
            'category' => $row['category'],
            'territoryCode' => $row['territory_code'],
            'closesAt' => $row['closes_at'],
            'closed' => $closed,
            'reviewStatus' => $row['review_status'],
        ];
        if ($closed) {
            // La traçabilité devient publique seulement après clôture.
            $public['sourceType'] = $row['source_type'];
            $public['sourceUrl'] = $row['source_url'];
            $public['sourceDate'] = $row['source_date'];
            $public['sourceReference'] = $row['source_reference'];
            $public['originActor'] = $row['origin_actor'];
            $public['legalStatus'] = $row['legal_status'];
            $query = $db->prepare(
                'SELECT choice, COUNT(*) AS count FROM policy_votes
                 WHERE measure_id = ? GROUP BY choice',
            );
            $query->execute([$row['id']]);
            $public['tally'] = ['pour' => 0, 'contre' => 0, 'abstention' => 0];
            foreach ($query->fetchAll() as $result) {
                $public['tally'][$result['choice']] = (int) $result['count'];
            }
        }

        return $public;
    }

    /** Révision possible jusqu'à clôture ; une seule position active par famille de test. */
    public function vote(string $id, array $profile, string $choice, string $argument = ''): void
    {
        $db = $this->database();
        if ($db === null) {
            throw new \DomainException('Catalogue non importé.', 503);
        }
        $citizenId = (string) ($profile['citizenId'] ?? '');
        if (!preg_match('/\Abeta_[a-f0-9]{24}\z/D', $citizenId)
            || (int) ($profile['expiresAt'] ?? 0) <= time()) {
            throw new \DomainException('Compte de test actif requis.', 403);
        }
        if (!in_array($choice, ['pour', 'contre', 'abstention'], true)) {
            throw new \DomainException('Choix invalide.', 400);
        }
        $argument = trim($argument);
        if (mb_strlen($argument) > 2000) {
            throw new \DomainException('Argument limité à 2 000 caractères.', 400);
        }
        $now = gmdate('Y-m-d\TH:i:s+00:00');
        $db->exec('BEGIN IMMEDIATE');
        try {
            $query = $db->prepare(
                "SELECT territory_code FROM policy_measures
                 WHERE id = ? AND source_type = 'proposition_de_loi'
                   AND status = ? AND closes_at > ?",
            );
            $query->execute([$id, 'open', $now]);
            $row = $query->fetch();
            if ($row === false) {
                throw new \DomainException('Mesure inexistante ou consultation close.', 409);
            }
            if ($row['territory_code'] !== 'FR'
                && $row['territory_code'] !== ($profile['communeCode'] ?? '')) {
                throw new \DomainException('Consultation réservée aux participants de la commune.', 403);
            }
            $find = $db->prepare(
                'SELECT choice, argument FROM policy_votes WHERE measure_id = ? AND citizen_id = ?',
            );
            $find->execute([$id, $citizenId]);
            $previous = $find->fetch();
            if ($previous !== false
                && $previous['choice'] === $choice
                && $previous['argument'] === $argument) {
                $db->commit();

                return;
            }
            $upsert = $db->prepare(
                'INSERT INTO policy_votes(measure_id, citizen_id, choice, argument, updated_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON CONFLICT(measure_id, citizen_id)
                 DO UPDATE SET choice = excluded.choice,
                     argument = excluded.argument, updated_at = excluded.updated_at',
            );
            $upsert->execute([$id, $citizenId, $choice, $argument, $now]);
            $audit = $db->prepare(
                'INSERT INTO policy_vote_history
                 (measure_id, citizen_id, previous_choice, choice, updated_at)
                 VALUES (?, ?, ?, ?, ?)',
            );
            $audit->execute([
                $id, $citizenId, $previous['choice'] ?? null, $choice, $now,
            ]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public function existingVote(string $id, ?array $profile): ?string
    {
        if ($profile === null) {
            return null;
        }
        $db = $this->database();
        if ($db === null) {
            return null;
        }
        $query = $db->prepare(
            'SELECT choice FROM policy_votes WHERE measure_id = ? AND citizen_id = ?',
        );
        $query->execute([$id, $profile['citizenId']]);

        return ($vote = $query->fetchColumn()) === false ? null : (string) $vote;
    }
}
