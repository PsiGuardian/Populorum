<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Read-only, source-attributed parliamentary dossier attached to an official bill.
 * Network synchronisation is strictly an explicit operator CLI operation.
 */
final class SenateDossier
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function forMeasure(string $measureId): array
    {
        $empty = ['documents' => [], 'groups' => [], 'excerpts' => [], 'updatedAt' => null];
        if (preg_match('/\Apm_[a-f0-9]{24}\z/D', $measureId) !== 1) {
            return $empty;
        }
        $override = getenv('POPULORUM_POLICY_CATALOG_PATH');
        $path = is_string($override) && $override !== ''
            ? $override : $this->projectDir . '/var/policy-catalog.sqlite';
        if (!is_file($path)) {
            return $empty;
        }
        $db = new \PDO('sqlite:' . $path);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $exists = $db->prepare(
            "SELECT 1 FROM policy_measures
             WHERE id = ? AND source_type = 'proposition_de_loi'",
        );
        $exists->execute([$measureId]);
        if ($exists->fetchColumn() === false) {
            return $empty;
        }
        $tables = $db->query(
            "SELECT name FROM sqlite_master
             WHERE type = 'table'
               AND name IN ('policy_senate_documents', 'policy_senate_context')",
        )->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('policy_senate_documents', $tables, true)
            || !in_array('policy_senate_context', $tables, true)) {
            return $empty;
        }
        $query = $db->prepare(
            'SELECT kind, label, url, source_section
             FROM policy_senate_documents
             WHERE measure_id = ?
             ORDER BY sort_order, label LIMIT 120',
        );
        $query->execute([$measureId]);
        $documents = $query->fetchAll();
        $groups = [];
        foreach ($documents as $document) {
            $groups[$document['kind']][] = $document;
        }
        $query = $db->prepare(
            'SELECT excerpt_text, excerpt_url, synced_at
             FROM policy_senate_context WHERE measure_id = ?',
        );
        $query->execute([$measureId]);
        $context = $query->fetch();
        if ($context === false) {
            return [
                'documents' => $documents, 'groups' => $groups,
                'excerpts' => [], 'updatedAt' => null,
            ];
        }

        return [
            'documents' => $documents,
            'groups' => $groups,
            'excerpts' => $context['excerpt_text'] !== ''
                ? explode("\n\n", $context['excerpt_text']) : [],
            'excerptUrl' => $context['excerpt_url'],
            'updatedAt' => $context['synced_at'],
        ];
    }
}
