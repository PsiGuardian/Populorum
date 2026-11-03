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

    /** Pagination/filtrage effectués par SQLite, sans décoder tous les dossiers. */
    public function page(int $number = 1, string $search = '', string $territory = ''): array
    {
        $db = $this->database();
        if ($db === null) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'available' => false];
        }
        $number = max(1, min(10000, $number));
        // Uniquement les propositions de loi parlementaires déposées officiellement.
        // Les anciennes fiches de programme restent archivées en SQLite, mais invisibles.
        $where = ["source_type = 'proposition_de_loi'"];
        $params = [];
        if ($search !== '') {
            $where[] = "label LIKE :search ESCAPE '!'";
            $params[':search'] = '%' . str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                mb_substr($search, 0, 100),
            ) . '%';
        }
        if (in_array($territory, ['FR', '75056'], true)) {
            $where[] = 'territory_code = :territory';
            $params[':territory'] = $territory;
        }
        $condition = ' WHERE ' . implode(' AND ', $where);
        $query = $db->prepare('SELECT COUNT(*) FROM policy_measures' . $condition);
        foreach ($params as $key => $value) {
            $query->bindValue($key, $value);
        }
        $query->execute();
        $count = (int) $query->fetchColumn();
        $pages = (int) ceil($count / self::PAGE_SIZE);
        $number = $pages ? min($number, $pages) : 1;
        $query = $db->prepare(
            'SELECT id, label, category, territory_code, closes_at, status,
                    source_type, source_url, source_date, source_reference,
                    origin_actor, legal_status, review_status
             FROM policy_measures' . $condition .
            ' ORDER BY territory_code DESC, category, label, id LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) {
            $query->bindValue($key, $value);
        }
        $query->bindValue(':limit', self::PAGE_SIZE, \PDO::PARAM_INT);
        $query->bindValue(':offset', ($number - 1) * self::PAGE_SIZE, \PDO::PARAM_INT);
        $query->execute();
        $rows = [];
        foreach ($query->fetchAll() as $row) {
            $rows[] = $this->publicRecord($db, $row);
        }

        return ['rows' => $rows, 'total' => $count, 'page' => $number, 'pages' => $pages, 'available' => true];
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
