<?php

declare(strict_types=1);

namespace App\Service;

/** Moteur métier PHP natif. Aucun service JavaScript, appel réseau ou IA. */
final class SimulationEngine
{
    public const CATEGORIES = [
        'Problème',
        'Cause directe',
        'Cause indirecte',
        'Cause probable',
        'Cause observée',
        'Solution proposée',
        'Conséquence favorable probable',
        'Conséquence défavorable probable',
        'Conséquence observable',
        'Conséquence imprévue',
        'Solution alternative',
        'Révision',
    ];

    private const PROPOSAL_TEXT_FIELDS = [
        'title',
        'summary',
        'problem',
        'cause',
        'consequence',
        'solution',
        'justification',
        'indicator',
        'risk',
        'reversibility',
    ];

    public function __construct(
        private readonly CausalGraph $graph = new CausalGraph(),
        private readonly AuditPrivacy $privacy = new AuditPrivacy(),
        private readonly CivicWorkflow $workflow = new CivicWorkflow(),
        private readonly ConvergenceAnalyzer $analyzer = new ConvergenceAnalyzer(),
    ) {
    }

    private function fail(string $message, int $status = 400): never
    {
        throw new \DomainException($message, $status);
    }

    private function requireText(
        mixed $value,
        int $minimumLength = 4,
        int $maximumLength = 3000,
    ): string {
        if (
            !is_string($value)
            || mb_strlen(trim($value)) < $minimumLength
            || mb_strlen($value) > $maximumLength
        ) {
            $this->fail(
                "Texte attendu : $minimumLength à $maximumLength caractères.",
            );
        }

        return trim($value);
    }

    private function requireNumber(
        mixed $value,
        float $minimum = -1e12,
        float $maximum = 1e12,
    ): int|float {
        if (
            !is_numeric($value)
            || !is_finite((float) $value)
            || (float) $value < $minimum
            || (float) $value > $maximum
        ) {
            $this->fail('Valeur numérique hors limites.');
        }

        return $value + 0;
    }

    private function generateId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(12));
    }

    private function currentTimestamp(): string
    {
        return gmdate("Y-m-d\TH:i:s\Z");
    }

    private function findItemIndex(
        array $state,
        string $collection,
        string $id,
    ): int {
        foreach ($state[$collection] as $index => $item) {
            if ($item['id'] === $id) {
                return $index;
            }
        }
        $this->fail('Objet introuvable.', 404);
    }

    private function requireActiveCitizen(array $state, string $id): array
    {
        $citizen =
            $state['citizens'][$this->findItemIndex($state, 'citizens', $id)];
        if ($citizen['suspended']) {
            $this->fail('Participation restreinte ; recours disponible.', 403);
        }

        return $citizen;
    }

    private function assertProposalAuthor(
        array $proposal,
        string $actorId,
    ): void {
        if ($proposal['authorId'] !== $actorId) {
            $this->fail(
                'Seul le personnage auteur peut réaliser cette opération.',
                403,
            );
        }
    }

    /** Trie les clés des objets, mais conserve l’ordre des listes pour stabiliser les empreintes. */
    private function canonicalJson(mixed $value): string
    {
        if (!is_array($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR,
            );
        }
        if (array_is_list($value)) {
            return '[' .
                implode(',', array_map($this->canonicalJson(...), $value)) .
                ']';
        }
        ksort($value, SORT_STRING);
        $parts = [];
        foreach ($value as $key => $itemValue) {
            $parts[] =
                $this->canonicalJson((string) $key) .
                ':' .
                $this->canonicalJson($itemValue);
        }

        return '{' . implode(',', $parts) . '}';
    }

    public function digest(mixed $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    /** Exclut les clés privées, le journal et les résultats calculés de l’état à signer. */
    private function stateContentForDigest(array $state): array
    {
        unset(
            $state['events'],
            $state['operations'],
            $state['keyPair'],
            $state['publicKey'],
        );
        foreach ($state['consultations'] as &$consultation) {
            unset($consultation['tally']);
        }

        return $state;
    }

    /** Contrôle le chaînage, les signatures, puis le lien entre le dernier événement et l’état courant. */
    public function verify(array $state): array
    {
        if (
            ($state['simulation'] ?? false) !== true
            || !is_int($state['schemaVersion'] ?? null)
        ) {
            return [
                'valid' => false,
                'reason' => 'Format de simulation ou version manquante.',
            ];
        }
        foreach (
            [
                'events',
                'territories',
                'citizens',
                'proposals',
                'consultations',
                'votes',
                'experiments',
            ] as $collection
        ) {
            if (
                !is_array($state[$collection] ?? null)
                || !array_is_list($state[$collection])
            ) {
                return [
                    'valid' => false,
                    'reason' => 'Collection de preuve invalide.',
                ];
            }
            foreach ($state[$collection] as $item) {
                if (!is_array($item)) {
                    return [
                        'valid' => false,
                        'reason' => 'Objet de preuve invalide.',
                    ];
                }
            }
        }
        foreach ($state['events'] as $event) {
            if (
                !is_string($event['hash'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/D', $event['hash'])
                || !is_string($event['signature'] ?? null)
                || !is_int($event['index'] ?? null)
                || !is_string($event['previous'] ?? null)
                || !is_array($event['payload'] ?? null)
                || !is_string($event['payload']['stateDigest'] ?? null)
            ) {
                return [
                    'valid' => false,
                    'reason' => 'Structure d’événement signée invalide.',
                ];
            }
        }
        $previousHash = 'GENESIS';
        $publicKey =
            $state['keyPair']['publicKey'] ?? ($state['publicKey'] ?? null);
        if (
            $state['events']
            && (!is_string($publicKey) || !@openssl_pkey_get_public($publicKey))
        ) {
            return ['valid' => false, 'reason' => 'Clé publique invalide.'];
        }
        foreach ($state['events'] as $index => $event) {
            $hash = $event['hash'];
            $signature = $event['signature'];
            unset($event['hash'], $event['signature']);
            if (
                $event['index'] !== $index
                || $event['previous'] !== $previousHash
                || (!$this->originalEventMatches($event, $hash)
                    && (!$publicKey
                        || !$this->privacy->verifiesRedaction(
                            $event,
                            $hash,
                            $publicKey,
                            $this->digest(...),
                        )))
            ) {
                return [
                    'valid' => false,
                    'index' => $index,
                    'reason' => 'Chaînage ou empreinte divergent.',
                ];
            }
            if (
                !$publicKey
                || openssl_verify(
                    $hash,
                    base64_decode($signature, true) ?: '',
                    $publicKey,
                    OPENSSL_ALGO_SHA256,
                ) !== 1
            ) {
                return [
                    'valid' => false,
                    'index' => $index,
                    'reason' => 'Signature invalide.',
                ];
            }
            $previousHash = $hash;
        }
        if (
            $state['events']
            && !hash_equals(
                end($state['events'])['payload']['stateDigest'],
                $this->digest($this->stateContentForDigest($state)),
            )
        ) {
            return [
                'valid' => false,
                'reason' => 'L’état courant diffère du point de contrôle signé.',
            ];
        }

        return [
            'valid' => true,
            'count' => count($state['events']),
            'checkpoint' => $previousHash,
            'reason' => $state['events']
                ? 'Chaîne, signatures et état courant vérifiés.'
                : 'État pédagogique initial ; aucun événement signé.',
        ];
    }

    private function originalEventMatches(array $event, string $hash): bool
    {
        // Une attestation d’expurgation doit toujours être vérifiée, même si elle est superflue.
        return !isset($event['redaction'])
            && hash_equals($hash, $this->digest($event));
    }

    /** Signe une opération et son nouvel état ; la persistance atomique est assurée par DemoDatabase. */
    private function recordAuditEvent(
        array &$state,
        string $actionType,
        string $objectId,
        array $payload,
    ): void {
        if (empty($state['keyPair'])) {
            $keyPair = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
            ]);
            if (!$keyPair || !openssl_pkey_export($keyPair, $privateKey)) {
                throw new \RuntimeException('Création de clé impossible.');
            }
            $state['keyPair'] = [
                'privateKey' => $privateKey,
                'publicKey' => openssl_pkey_get_details($keyPair)['key'],
            ];
        }
        $event = [
            'id' => $this->generateId('evt'),
            'index' => count($state['events']),
            'type' => $actionType,
            'object' => $objectId,
            'at' => $this->currentTimestamp(),
            'previous' => $state['events']
                ? end($state['events'])['hash']
                : 'GENESIS',
            'payload' => [
                'simulation' => true,
                'actorId' => $payload['citizenId'] ?? null,
                'changes' => $this->privacy->changes($actionType, $payload),
                'stateDigest' => $this->digest(
                    $this->stateContentForDigest($state),
                ),
            ],
        ];
        $hash = $this->digest($event);
        if (
            !openssl_sign(
                $hash,
                $signature,
                $state['keyPair']['privateKey'],
                OPENSSL_ALGO_SHA256,
            )
        ) {
            throw new \RuntimeException('Signature impossible.');
        }
        $state['events'][] = $event + [
            'hash' => $hash,
            'signature' => base64_encode($signature),
        ];
    }

    /** Sceau initial propre à cette instance ; aucune clé privée dans les fixtures versionnées. */
    public function initializeFixture(array $state): array
    {
        $this->recordAuditEvent(
            $state,
            'fixture.created',
            $state['preferences']['dataset'] ?? 'municipal-fixture',
            [],
        );

        return $state;
    }

    public function publicState(array $state): array
    {
        $state['publicKey'] = $state['keyPair']['publicKey'] ?? null;
        unset($state['keyPair'], $state['operations']);
        foreach ($state['consultations'] as &$consultation) {
            $consultation['tally'] = $this->tally(
                $consultation,
                $state['votes'],
            );
        }

        return $state;
    }

    public function tally(array $consultation, array $votes): array
    {
        $unit = $consultation['rules']['votingUnit'] ?? 'individual';
        if (!in_array($unit, ['individual', 'family'], true)) {
            throw new \DomainException('Unité de suffrage inconnue.', 409);
        }
        $family =
            $unit === 'family'
                ? new FamilySuffrage()->count($consultation, $votes)
                : null;
        $counts = $family['counts'] ?? [
            'pour' => 0,
            'contre' => 0,
            'abstention' => 0,
        ];
        if (!$family) {
            foreach ($votes as $vote) {
                if ($vote['consultationId'] === $consultation['id']) {
                    ++$counts[$vote['choice']];
                }
            }
        }
        $total = array_sum($counts);
        $eligibleCount =
            $family['eligible'] ?? count($consultation['eligibleIds']);
        $participation = $eligibleCount ? ($total / $eligibleCount) * 100 : 0;
        $expressedCount = $counts['pour'] + $counts['contre'];

        return $counts + [
            'total' => $total,
            'eligible' => $eligibleCount,
            'unit' => $family ? 'family' : 'individual',
            'families' => $family['families'] ?? [],
            'individual' => $family['individual'] ?? $counts + ['total' => $total],
            'expressed' => $expressedCount,
            'participation' => $participation,
            'quorumReached' => $participation >= $consultation['rules']['quorum'],
            'adopted' => $consultation['status'] !== 'cancelled'
                && $participation >= $consultation['rules']['quorum']
                && $expressedCount > 0
                && $counts['pour'] > $counts['contre'],
            'provisional' => $this->consultationPhase($consultation) === 'open',
            'phase' => $this->consultationPhase($consultation),
            'awaitingProclamation' => $this->consultationPhase($consultation) === 'expired',
        ];
    }

    /** L’échéance arrête le vote sans proclamer silencieusement un résultat définitif. */
    public function consultationPhase(array $consultation): string
    {
        if (in_array($consultation['status'], ['closed', 'cancelled'], true)) {
            return $consultation['status'];
        }

        return strtotime($consultation['closesAt']) <= time()
            ? 'expired'
            : 'open';
    }

    public function convergence(array $state): array
    {
        return $this->analyzer->analyze($state);
    }

    private function validateProposal(array $proposal): void
    {
        foreach (
            [
                'title',
                'problem',
                'summary',
                'cause',
                'consequence',
                'solution',
                'justification',
                'indicator',
                'risk',
                'reversibility',
            ] as $field
        ) {
            $this->requireText($proposal[$field] ?? '');
        }
        if (count($proposal['nodes']) < 12) {
            $this->fail('Douze catégories causales sont requises.');
        }
        foreach (self::CATEGORIES as $nodeIndex => $category) {
            if (
                ($proposal['nodes'][$nodeIndex]['category'] ?? '') !==
                $category
            ) {
                $this->fail('Catégorie causale invalide.');
            }
            $this->requireText($proposal['nodes'][$nodeIndex]['content'] ?? '');
            if (
                str_starts_with(
                    $proposal['nodes'][$nodeIndex]['content'],
                    'À compléter',
                )
            ) {
                $this->fail(
                    'Complétez les éléments causaux avant publication.',
                );
            }
        }
        foreach ($proposal['nodes'] as $node) {
            $this->requireText($node['content']);
        }
        $this->graph->edges($proposal['edges'], $proposal['nodes']);
        if (!$proposal['sources']) {
            $this->fail('Une source pédagogique est nécessaire.');
        }
    }

    /** Valide l’intégrité et l’anti-rejeu avant de déléguer chaque action à une méthode métier. */
    public function apply(
        array $state,
        string $actionType,
        array $payload,
        string $operationId,
    ): array {
        if ($state['events'] && !$this->verify($state)['valid']) {
            $this->fail(
                'Intégrité divergente : opération suspendue pour vérification.',
                409,
            );
        }
        if (strlen($operationId) < 16 || strlen($operationId) > 100) {
            $this->fail('Identifiant d’opération invalide.');
        }
        if (in_array($operationId, $state['operations'], true)) {
            $this->fail('Rejeu refusé.', 409);
        }
        if (count($state['operations']) >= 10000) {
            $this->fail(
                'Limite pédagogique atteinte : exportez cette simulation.',
                429,
            );
        }
        $actorId = $payload['citizenId'] ?? '';
        $actor = $this->requireActiveCitizen($state, $actorId);
        if (
            str_starts_with($actionType, 'proposal.')
            || in_array(
                $actionType,
                ['observation.add', 'experiment.create'],
                true,
            )
        ) {
            foreach ($actor['publicationRestrictions'] ?? [] as $restriction) {
                if (
                    !$restriction['revoked']
                    && strtotime($restriction['until']) > time()
                ) {
                    $this->fail(
                        'Publication temporairement restreinte ; recours et droit de vote préservés.',
                        403,
                    );
                }
            }
        }
        $objectId = match ($actionType) {
            'citizen.create' => $this->createCitizen($state, $payload),
            'citizen.withdraw' => $this->withdrawConsent(
                $state,
                $payload,
                $actorId,
            ),
            'citizen.consent' => $this->changeConsent(
                $state,
                $payload,
                $actorId,
            ),
            'citizen.update' => $this->updateCitizen(
                $state,
                $payload,
                $actorId,
                $actor,
            ),
            'citizen.erase' => $this->eraseCitizenName(
                $state,
                $payload,
                $actorId,
            ),
            'proposal.create' => $this->createProposal(
                $state,
                $payload,
                $actorId,
                $actor,
            ),
            'proposal.publish' => $this->publishProposal(
                $state,
                $payload,
                $actorId,
            ),
            'proposal.revise' => $this->reviseProposal(
                $state,
                $payload,
                $actorId,
            ),
            'proposal.node' => $this->addCausalNode($state, $payload, $actorId),
            'proposal.edge' => $this->addCausalEdge($state, $payload, $actorId),
            'proposal.source' => $this->addProposalSource(
                $state,
                $payload,
                $actorId,
            ),
            'territory.adapt' => $this->adaptProposalToTerritory(
                $state,
                $payload,
                $actorId,
                $actor,
            ),
            'consultation.open' => $this->openConsultation(
                $state,
                $payload,
                $actorId,
            ),
            'vote.cast' => $this->castVote($state, $payload, $actorId, $actor),
            'consultation.close' => $this->closeConsultation(
                $state,
                $payload,
                $actorId,
            ),
            'experiment.create' => $this->createExperiment(
                $state,
                $payload,
                $actorId,
            ),
            'observation.add' => $this->addObservation(
                $state,
                $payload,
                $actor,
            ),
            'experiment.evaluate' => $this->evaluateExperiment(
                $state,
                $payload,
                $actorId,
            ),
            'finance.add' => $this->addFinancialEntry($state, $payload),
            'governance.propose' => $this->proposeGovernanceChange(
                $state,
                $payload,
                $actorId,
            ),
            'governance.approve' => $this->approveGovernanceChange(
                $state,
                $payload,
                $actorId,
            ),
            'moderation.report' => $this->reportModerationCase(
                $state,
                $payload,
                $actorId,
            ),
            'moderation.appeal' => $this->appealModerationCase(
                $state,
                $payload,
                $actorId,
            ),
            'integrity.alert' => $this->reportIntegrityAlert($state, $payload),
            'node.configure' => $this->configureVirtualNode($state, $payload),
            'node.sync' => $this->synchronizeVirtualNode($state, $payload),
            'resource.add' => $this->addResourceContribution($state, $payload),
            default => $this->workflow->apply(
                $state,
                $actionType,
                $payload,
                $actor,
            ),
        };
        $state['operations'][] = $operationId;
        $this->recordAuditEvent($state, $actionType, $objectId, $payload);

        return $state;
    }

    private function createCitizen(array &$state, array $payload): string
    {
        $name = $this->requireText($payload['name'] ?? '', 2, 80);
        $this->findItemIndex(
            $state,
            'territories',
            $payload['territoryId'] ?? '',
        );
        foreach ($state['citizens'] as $existingCitizen) {
            if (
                mb_strtolower($existingCitizen['name']) === mb_strtolower($name)
            ) {
                $this->fail('Identité fictive dupliquée.', 409);
            }
        }
        $objectId = $this->generateId('u');
        $state['citizens'][] = [
            'id' => $objectId,
            'name' => $name,
            'territoryId' => $payload['territoryId'],
            'consent' => false,
            'role' => 'citizen',
            'suspended' => false,
            'history' => [],
        ];

        return $objectId;
    }

    /** Le retrait est aussi inscrit dans la charge auditée, et pas uniquement dans le personnage. */
    private function withdrawConsent(
        array &$state,
        array &$payload,
        string $actorId,
    ): string {
        $payload['consent'] = false;

        return $this->changeConsent($state, $payload, $actorId);
    }

    private function changeConsent(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $citizenIndex = $this->findItemIndex($state, 'citizens', $actorId);
        $state['citizens'][$citizenIndex]['consent'] = filter_var(
            $payload['consent'] ?? false,
            FILTER_VALIDATE_BOOL,
        );
        $objectId = $actorId;

        return $objectId;
    }

    private function updateCitizen(
        array &$state,
        array $payload,
        string $actorId,
        array $actor,
    ): string {
        $citizenIndex = $this->findItemIndex($state, 'citizens', $actorId);
        if (!empty($payload['name'])) {
            $name = $this->requireText($payload['name'], 2, 80);
            foreach ($state['citizens'] as $existingCitizen) {
                if (
                    $existingCitizen['id'] !== $actorId
                    && mb_strtolower($existingCitizen['name']) ===
                        mb_strtolower($name)
                ) {
                    $this->fail('Nom fictif déjà utilisé.', 409);
                }
            }
            $state['citizens'][$citizenIndex]['name'] = $name;
        }
        if (
            !empty($payload['territoryId'])
            && $payload['territoryId'] !== $actor['territoryId']
        ) {
            foreach ($state['consultations'] as $consultation) {
                if (
                    $consultation['status'] === 'open'
                    && in_array($actorId, $consultation['eligibleIds'], true)
                ) {
                    $this->fail(
                        'Changement territorial différé : consultation admissible ouverte.',
                        409,
                    );
                }
            }
            $this->findItemIndex(
                $state,
                'territories',
                $payload['territoryId'],
            );
            $state['citizens'][$citizenIndex]['history'][] = [
                'territoryId' => $actor['territoryId'],
                'until' => $this->currentTimestamp(),
            ];
            $state['citizens'][$citizenIndex]['territoryId'] =
                $payload['territoryId'];
        }
        $objectId = $actorId;

        return $objectId;
    }

    private function eraseCitizenName(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $citizenIndex = $this->findItemIndex($state, 'citizens', $actorId);
        $this->privacy->eraseLegacyNames($state, $actorId, $this->digest(...));
        $state['citizens'][$citizenIndex]['name'] = 'Identité fictive effacée';
        $state['citizens'][$citizenIndex]['consent'] = false;
        $objectId = $actorId;

        return $objectId;
    }

    private function createProposal(
        array &$state,
        array $payload,
        string $actorId,
        array $actor,
    ): string {
        $this->findItemIndex(
            $state,
            'territories',
            $payload['territoryId'] ?? '',
        );
        if ($actor['territoryId'] !== $payload['territoryId']) {
            $this->fail('Un auteur local est requis.', 403);
        }
        $proposal = [
            'id' => $this->generateId('p'),
            'authorId' => $actorId,
            'territoryId' => $payload['territoryId'],
            'status' => 'draft',
            'version' => 1,
            'createdAt' => $this->currentTimestamp(),
            'revisions' => [],
            'edges' => [],
        ];
        foreach (self::PROPOSAL_TEXT_FIELDS as $field) {
            $proposal[$field] =
                $field === 'title'
                    ? $this->requireText($payload[$field] ?? '')
                    : $this->draftText($payload[$field] ?? '');
        }
        $proposal['theme'] = $this->draftText(
            $payload['theme'] ?? 'Vie locale',
        );
        $proposal['tags'] = $this->proposalTags($payload['tags'] ?? '');
        $proposal['forecast'] = $this->requireNumber(
            $payload['forecast'] ?? 0,
            -100,
            100,
        );
        $nodes = $payload['nodes'] ?? [];
        foreach (self::CATEGORIES as $index => $category) {
            $nodes[$index] ??= [
                'category' => $category,
                'content' => '',
                'kind' => 'hypothesis',
            ];
        }
        $proposal['nodes'] = $this->graph->nodes($nodes);
        $proposal['edges'] = $this->graph->edges(
            $payload['edges'] ?? [],
            $proposal['nodes'],
        );
        $proposal['sources'] = $this->proposalSources($payload);
        $state['proposals'][] = $proposal;
        $objectId = $proposal['id'];

        return $objectId;
    }

    private function publishProposal(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposalIndex = $this->findItemIndex(
            $state,
            'proposals',
            $payload['proposalId'] ?? '',
        );
        $proposal = $state['proposals'][$proposalIndex];
        $this->assertProposalAuthor($proposal, $actorId);
        if ($proposal['status'] !== 'draft') {
            $this->fail('Seul un brouillon peut être publié.');
        }
        $this->validateProposal($proposal);
        $state['proposals'][$proposalIndex]['status'] = 'published';
        $objectId = $proposal['id'];

        return $objectId;
    }

    private function reviseProposal(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposalIndex = $this->findItemIndex(
            $state,
            'proposals',
            $payload['proposalId'] ?? '',
        );
        $proposal = $state['proposals'][$proposalIndex];
        $this->assertProposalAuthor($proposal, $actorId);
        foreach ($state['consultations'] as $consultation) {
            if (
                $consultation['proposalId'] === $proposal['id']
                && $consultation['status'] === 'open'
            ) {
                $this->fail('Version gelée pendant la consultation.', 409);
            }
        }
        $snapshot = $proposal;
        unset($snapshot['revisions']);
        $state['proposals'][$proposalIndex]['revisions'][] = [
            'version' => $proposal['version'],
            'at' => $this->currentTimestamp(),
            'reason' => $this->requireText($payload['reason'] ?? ''),
            'snapshot' => $snapshot,
        ];
        $next = $state['proposals'][$proposalIndex];
        if (!empty($payload['observationId'])) {
            $found = false;
            foreach ($state['experiments'] as $experiment) {
                if (
                    $experiment['proposalId'] === $proposal['id']
                    && in_array(
                        $payload['observationId'],
                        array_column($experiment['observations'], 'id'),
                        true,
                    )
                ) {
                    $found = true;
                }
            }
            if (!$found) {
                $this->fail('Observation étrangère au chemin ou introuvable.');
            }
            $next['revisions'][array_key_last($next['revisions'])][
                'observationId'
            ] = $payload['observationId'];
        }

        foreach (self::PROPOSAL_TEXT_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $next[$field] =
                    $field === 'title'
                        ? $this->requireText($payload[$field])
                        : $this->draftText($payload[$field]);
            }
        }
        if (array_key_exists('theme', $payload)) {
            $next['theme'] = $this->draftText($payload['theme']);
        }
        if (array_key_exists('tags', $payload)) {
            $next['tags'] = $this->proposalTags($payload['tags']);
        }
        if (array_key_exists('forecast', $payload)) {
            $next['forecast'] = $this->requireNumber(
                $payload['forecast'],
                -100,
                100,
            );
        }
        if (isset($payload['nodes'])) {
            $next['nodes'] = $this->graph->nodes($payload['nodes']);
        }
        $next['edges'] = $this->graph->edges(
            $payload['edges'] ?? $next['edges'],
            $next['nodes'],
        );
        if (
            array_key_exists('source', $payload)
            || array_key_exists('sources', $payload)
        ) {
            $next['sources'] = $this->proposalSources($payload);
        }
        ++$next['version'];
        // Un brouillon complété reste un brouillon jusqu’à la publication explicite.
        $next['status'] =
            $proposal['status'] === 'draft' ? 'draft' : 'published';
        if ($next['status'] !== 'draft') {
            $this->validateProposal($next);
        }
        $state['proposals'][$proposalIndex] = $next;
        $objectId = $proposal['id'];

        return $objectId;
    }

    private function draftText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value) || mb_strlen($value) > 3000) {
            $this->fail('Texte limité à 3000 caractères.');
        }

        return trim($value);
    }

    private function proposalTags(mixed $value): array
    {
        return array_values(
            array_unique(
                array_filter(
                    array_map('trim', explode(',', $this->draftText($value))),
                ),
            ),
        );
    }

    private function proposalSources(array $payload): array
    {
        if (isset($payload['sources'])) {
            if (
                !is_array($payload['sources'])
                || count($payload['sources']) > 50
            ) {
                $this->fail('Liste de sources invalide.');
            }

            return array_map(function (array $source): array {
                $url = $source['url'] ?? '';
                if (
                    $url !== ''
                    && (!filter_var($url, FILTER_VALIDATE_URL)
                        || !in_array(
                            parse_url($url, PHP_URL_SCHEME),
                            ['https', 'http'],
                            true,
                        ))
                ) {
                    $this->fail('Lien de source invalide.');
                }
                $date = $source['date'] ?? gmdate('Y-m-d');
                if (
                    !is_string($date)
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                    || !strtotime($date)
                ) {
                    $this->fail('Date de source invalide.');
                }

                return [
                    'label' => $this->requireText($source['label'] ?? ''),
                    'date' => $date,
                    'url' => $url,
                ];
            }, $payload['sources']);
        }
        $label = $this->draftText($payload['source'] ?? '');

        return $label === ''
            ? []
            : [
                [
                    'label' => $this->requireText($label),
                    'date' => gmdate('Y-m-d'),
                    'url' => '',
                ],
            ];
    }

    private function addProposalSource(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposal =
            $state['proposals'][
                $this->findItemIndex(
                    $state,
                    'proposals',
                    $payload['proposalId'] ?? '',
                )
            ];
        $sources = $proposal['sources'];
        $sources[] = [
            'label' => $payload['label'] ?? '',
            'date' => $payload['date'] ?? '',
            'url' => $payload['url'] ?? '',
        ];

        return $this->reviseProposal(
            $state,
            $payload + [
                'sources' => $sources,
                'reason' => 'Ajout d’une source',
            ],
            $actorId,
        );
    }

    private function addCausalNode(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposal =
            $state['proposals'][
                $this->findItemIndex(
                    $state,
                    'proposals',
                    $payload['proposalId'] ?? '',
                )
            ];
        $nodes = $proposal['nodes'];
        $nodes[] = [
            'id' => $this->generateId('n'),
            'category' => $this->requireText(
                $payload['category'] ?? '',
                2,
                100,
            ),
            'content' => $this->requireText($payload['content'] ?? ''),
            'kind' => $payload['kind'] ?? 'hypothesis',
        ];

        return $this->reviseProposal(
            $state,
            $payload + [
                'nodes' => $nodes,
                'reason' => 'Ajout d’un élément causal',
            ],
            $actorId,
        );
    }

    private function addCausalEdge(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposal =
            $state['proposals'][
                $this->findItemIndex(
                    $state,
                    'proposals',
                    $payload['proposalId'] ?? '',
                )
            ];
        $edges = $proposal['edges'];
        $edges[] = [
            'from' => $payload['from'] ?? '',
            'to' => $payload['to'] ?? '',
            'relation' => $payload['relation'] ?? '',
        ];

        return $this->reviseProposal(
            $state,
            $payload + [
                'edges' => $edges,
                'reason' => 'Ajout d’une relation causale',
            ],
            $actorId,
        );
    }

    private function adaptProposalToTerritory(
        array &$state,
        array $payload,
        string $actorId,
        array $actor,
    ): string {
        $proposal =
            $state['proposals'][
                $this->findItemIndex(
                    $state,
                    'proposals',
                    $payload['proposalId'] ?? '',
                )
            ];
        $this->findItemIndex(
            $state,
            'territories',
            $payload['territoryId'] ?? '',
        );
        if (
            $proposal['territoryId'] === $payload['territoryId']
            || $actor['territoryId'] !== $payload['territoryId']
        ) {
            $this->fail(
                'Choisissez un autre territoire et son personnage local.',
                403,
            );
        }
        $adaptation = [
            'id' => $this->generateId('adapt'),
            'authorId' => $actorId,
            'version' => 1,
            'revisions' => [],
            'probableConsequences' => $this->draftText(
                $payload['probableConsequences'] ?? '',
            ),
            'observedConsequences' => $this->draftText(
                $payload['observedConsequences'] ?? '',
            ),
            'proposalId' => $proposal['id'],
            'territoryId' => $payload['territoryId'],
            'type' => ($payload['type'] ?? 'adaptation') === 'exclusion'
                    ? 'exclusion'
                    : 'adaptation',
            'at' => $this->currentTimestamp(),
        ];
        foreach (
            ['reason', 'characteristic', 'source', 'alternative', 'review'] as $field
        ) {
            $adaptation[$field] = $this->requireText($payload[$field] ?? '');
        }
        $state['adaptations'][] = $adaptation;
        if ($adaptation['type'] === 'adaptation') {
            $proposal['originId'] = $proposal['id'];
            $proposal['id'] = $this->generateId('p');
            $proposal['authorId'] = $actorId;
            $proposal['territoryId'] = $payload['territoryId'];
            $proposal['title'] .= ' — adaptation locale';
            $proposal['solution'] = $adaptation['alternative'];
            $proposal['version'] = 1;
            $proposal['status'] = 'draft';
            $proposal['revisions'] = [];
            $proposal['createdAt'] = $this->currentTimestamp();
            $state['proposals'][] = $proposal;
        }
        $objectId = $adaptation['id'];

        return $objectId;
    }

    private function openConsultation(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposalIndex = $this->findItemIndex(
            $state,
            'proposals',
            $payload['proposalId'] ?? '',
        );
        $proposal = $state['proposals'][$proposalIndex];
        $this->assertProposalAuthor($proposal, $actorId);
        $this->validateProposal($proposal);
        foreach ($state['consultations'] as $existingConsultation) {
            if (
                $existingConsultation['proposalId'] === $proposal['id']
                && $existingConsultation['status'] === 'open'
            ) {
                $this->fail('Consultation déjà ouverte.', 409);
            }
        }
        $days = $this->requireNumber($payload['days'] ?? 14, 1, 365);
        if (floor($days) !== $days && is_float($days)) {
            $this->fail('Nombre entier de jours requis.');
        }
        $activeRules =
            $state['ruleSets'] ?? [] ? end($state['ruleSets']) : null;
        $quorum = $this->requireNumber(
            $activeRules['quorum'] ?? ($payload['quorum'] ?? 50),
            0,
            100,
        );
        $objectId = $this->generateId('c');
        $state['consultations'][] = [
            'id' => $objectId,
            'proposalId' => $proposal['id'],
            'territoryId' => $proposal['territoryId'],
            'version' => $proposal['version'],
            'frozen' => $proposal,
            'rules' => [
                'quorum' => $quorum,
                'majority' => 'Majorité stricte des exprimés ; égalité = non adoptée',
                'abstention' => 'Dans la participation, hors exprimés',
                'changeAllowed' => $activeRules['changeAllowed'] ?? true,
                'ruleSetId' => $activeRules['id'] ?? 'initial',
            ],
            'eligibleIds' => array_column(
                array_filter(
                    $state['citizens'],
                    fn (array $participant): bool => $participant[
                        'territoryId'
                    ] === $proposal['territoryId']
                        && !$participant['suspended'],
                ),
                'id',
            ),
            'opensAt' => $this->currentTimestamp(),
            'closesAt' => gmdate(
                "Y-m-d\TH:i:s\Z",
                time() + (int) $days * 86400,
            ),
            'status' => 'open',
        ];
        if (($state['preferences']['votingUnit'] ?? '') === 'family') {
            $index = array_key_last($state['consultations']);
            $state['consultations'][$index]['rules']['votingUnit'] = 'family';
            $state['consultations'][$index]['rules']['familyAggregation'] =
                FamilySuffrage::VERSION;
            $state['consultations'][$index]['rules']['majority'] =
                'Majorité stricte des familles exprimant POUR ou CONTRE ; égalité = non adoptée';
            $state['consultations'][$index]['rules']['abstention'] =
                'Familles abstentionnistes dans le quorum, hors familles exprimées';
            $state['consultations'][$index][
                'eligibleFamilies'
            ] = new FamilySuffrage()->freeze(
                $state['citizens'],
                $state['consultations'][$index]['eligibleIds'],
                $proposal['territoryId'],
            );
        }
        $state['proposals'][$proposalIndex]['status'] = 'consultation';

        return $objectId;
    }

    private function castVote(
        array &$state,
        array $payload,
        string $actorId,
        array $actor,
    ): string {
        $consultationIndex = $this->findItemIndex(
            $state,
            'consultations',
            $payload['consultationId'] ?? '',
        );
        $consultation = $state['consultations'][$consultationIndex];
        if (
            $consultation['status'] !== 'open'
            || strtotime($consultation['closesAt']) <= time()
            || strtotime($consultation['opensAt']) > time()
        ) {
            $this->fail('Consultation fermée.', 409);
        }
        if (
            !in_array($actorId, $consultation['eligibleIds'], true)
            || $consultation['territoryId'] !== $actor['territoryId']
        ) {
            $this->fail('Personnage non admissible sur ce territoire.', 403);
        }
        if (!$actor['consent']) {
            $this->fail('Consentement nominatif fictif requis.', 403);
        }
        $choice = $payload['choice'] ?? '';
        if (!in_array($choice, ['pour', 'contre', 'abstention'], true)) {
            $this->fail('Choix invalide.');
        }
        $existingVoteChanged = false;
        foreach ($state['votes'] as &$vote) {
            if (
                $vote['consultationId'] === $consultation['id']
                && $vote['citizenId'] === $actorId
            ) {
                if (!$consultation['rules']['changeAllowed']) {
                    $this->fail('Changement interdit.', 409);
                }
                if ($vote['choice'] === $choice) {
                    $this->fail('Ce choix est déjà actif.', 409);
                }
                $vote['history'][] =
                    [
                        'choice' => $vote['choice'],
                        'at' => $vote['at'],
                    ] +
                    (isset($vote['deliberation'])
                        ? ['deliberation' => $vote['deliberation']]
                        : []);
                unset($vote['deliberation']);
                $vote['choice'] = $choice;
                $vote['at'] = $this->currentTimestamp();
                $existingVoteChanged = true;
                break;
            }
        }
        unset($vote);
        if (!$existingVoteChanged) {
            $state['votes'][] = [
                'id' => $this->generateId('v'),
                'consultationId' => $consultation['id'],
                'citizenId' => $actorId,
                'choice' => $choice,
                'at' => $this->currentTimestamp(),
                'history' => [],
            ];
        }
        $objectId = $consultation['id'];

        return $objectId;
    }

    private function closeConsultation(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $consultationIndex = $this->findItemIndex(
            $state,
            'consultations',
            $payload['consultationId'] ?? '',
        );
        $consultation = $state['consultations'][$consultationIndex];
        $proposalIndex = $this->findItemIndex(
            $state,
            'proposals',
            $consultation['proposalId'],
        );
        $this->assertProposalAuthor(
            $state['proposals'][$proposalIndex],
            $actorId,
        );
        if ($consultation['status'] !== 'open') {
            $this->fail('Consultation déjà close.', 409);
        }
        $state['consultations'][$consultationIndex]['status'] = 'closed';
        $state['consultations'][$consultationIndex][
            'closedAt'
        ] = $this->currentTimestamp();
        $state['consultations'][$consultationIndex][
            'closeReason'
        ] = $this->requireText(
            $payload['reason'] ?? 'Clôture pédagogique volontaire',
        );
        $state['consultations'][$consultationIndex]['result'] = $this->tally(
            $state['consultations'][$consultationIndex],
            $state['votes'],
        );
        $state['proposals'][$proposalIndex]['status'] = 'result';
        $objectId = $consultation['id'];

        return $objectId;
    }

    private function createExperiment(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $consultation =
            $state['consultations'][
                $this->findItemIndex(
                    $state,
                    'consultations',
                    $payload['consultationId'] ?? '',
                )
            ];
        if (
            $consultation['status'] !== 'closed'
            || !$this->tally($consultation, $state['votes'])['adopted']
        ) {
            $this->fail('Consultation close et adoptée requise.');
        }
        foreach ($state['experiments'] as $experiment) {
            if ($experiment['consultationId'] === $consultation['id']) {
                $this->fail('Protocole déjà créé.', 409);
            }
        }
        $proposalIndex = $this->findItemIndex(
            $state,
            'proposals',
            $consultation['proposalId'],
        );
        $this->assertProposalAuthor(
            $state['proposals'][$proposalIndex],
            $actorId,
        );
        $experiment = [
            'id' => $this->generateId('e'),
            'consultationId' => $consultation['id'],
            'proposalId' => $consultation['proposalId'],
            'proposalVersion' => $consultation['version'],
            'frozenProposal' => $consultation['frozen'],
            'territoryId' => $consultation['territoryId'],
            'indicator' => $consultation['frozen']['indicator'],
            'baseline' => $this->requireNumber($payload['baseline'] ?? null, 0),
            'status' => 'running',
            'observations' => [],
        ];
        foreach (
            [
                'objective',
                'hypothesis',
                'unit',
                'start',
                'end',
                'stop',
                'method',
                'limitations',
            ] as $field
        ) {
            $experiment[$field] = $this->requireText(
                $payload[$field] ?? '',
                $field === 'unit' ? 1 : 4,
            );
        }
        if (
            !strtotime($experiment['start'])
            || !strtotime($experiment['end'])
            || $experiment['end'] <= $experiment['start']
        ) {
            $this->fail('Période invalide.');
        }
        $state['experiments'][] = $experiment;
        $state['proposals'][$proposalIndex]['status'] = 'experiment';
        $objectId = $experiment['id'];

        return $objectId;
    }

    private function addObservation(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $experimentIndex = $this->findItemIndex(
            $state,
            'experiments',
            $payload['experimentId'] ?? '',
        );
        $experiment = $state['experiments'][$experimentIndex];
        if ($experiment['territoryId'] !== $actor['territoryId']) {
            $this->fail('Observateur local requis.', 403);
        }
        if ($experiment['status'] !== 'running') {
            $this->fail('Expérience close.', 409);
        }
        $date = $this->requireText($payload['date'] ?? '', 10, 10);
        if (
            !strtotime($date)
            || $date < $experiment['start']
            || $date > $experiment['end']
        ) {
            $this->fail('Observation hors période.');
        }
        $observation = [
            'id' => $this->generateId('o'),
            'date' => $date,
            'baseline' => $experiment['baseline'],
            'value' => $this->requireNumber($payload['value'] ?? null),
            'uncertainty' => $this->requireNumber(
                $payload['uncertainty'] ?? null,
                0,
            ),
            'source' => $this->requireText($payload['source'] ?? ''),
            'external' => $this->requireText($payload['external'] ?? ''),
            'unexpected' => $this->requireText(
                $payload['unexpected'] ?? 'Aucun imprévu déclaré',
            ),
            'simulated' => true,
        ];
        $state['experiments'][$experimentIndex][
            'observations'
        ][] = $observation;
        $objectId = $experiment['id'];

        return $objectId;
    }

    private function evaluateExperiment(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $experimentIndex = $this->findItemIndex(
            $state,
            'experiments',
            $payload['experimentId'] ?? '',
        );
        $experiment = $state['experiments'][$experimentIndex];
        $this->assertProposalAuthor(
            $state['proposals'][
                $this->findItemIndex(
                    $state,
                    'proposals',
                    $experiment['proposalId'],
                )
            ],
            $actorId,
        );
        if (!$experiment['observations']) {
            $this->fail('Observation requise.');
        }
        $decision = $payload['decision'] ?? 'revise';
        if (!in_array($decision, ['continue', 'revise', 'stop'], true)) {
            $this->fail('Suite invalide.');
        }
        if (isset($experiment['evaluation'])) {
            $this->fail(
                'Ce bilan est déjà publié ; son remplacement est interdit.',
                409,
            );
        }
        $comparison = $this->analyzer->evaluation(
            $experiment,
            $state['proposals'][
                $this->findItemIndex(
                    $state,
                    'proposals',
                    $experiment['proposalId'],
                )
            ],
        );
        $state['experiments'][$experimentIndex]['status'] = 'evaluated';
        $state['experiments'][$experimentIndex]['comparison'] = $comparison;
        $state['experiments'][$experimentIndex]['evaluation'] = [
            'summary' => $this->requireText($payload['summary'] ?? ''),
            'decision' => $decision,
            'at' => $this->currentTimestamp(),
        ];
        $objectId = $experiment['id'];

        return $objectId;
    }

    private function addFinancialEntry(array &$state, array $payload): string
    {
        $amount = $this->requireNumber($payload['amount'] ?? null, 0.01, 1e7);
        if (abs($amount * 100 - round($amount * 100)) > 0.00001) {
            $this->fail('Deux décimales maximum.');
        }
        if (
            !in_array($payload['type'] ?? '', ['expense', 'contribution'], true)
        ) {
            $this->fail('Nature comptable invalide.');
        }
        $objectId = $this->generateId('f');
        $state['finance'][] = [
            'id' => $objectId,
            'type' => $payload['type'],
            'label' => $this->requireText($payload['label'] ?? ''),
            'cents' => (int) round($amount * 100),
            'territoryId' => $this->requireActiveCitizen(
                $state,
                $payload['citizenId'],
            )['territoryId'],
            'category' => $this->draftText(
                $payload['category'] ?? 'Autre ressource',
            ),
            'receipt' => $this->requireText($payload['receipt'] ?? ''),
            'date' => gmdate('Y-m-d'),
            'simulated' => true,
        ];

        return $objectId;
    }

    private function proposeGovernanceChange(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposal = [
            'id' => $this->generateId('g'),
            'authorId' => $actorId,
            'status' => 'proposed',
            'approvals' => [],
            'at' => $this->currentTimestamp(),
        ];
        foreach (['title', 'reason', 'risk', 'rollback'] as $field) {
            $proposal[$field] = $this->requireText($payload[$field] ?? '');
        }
        $proposal['proposedRules'] = [
            'quorum' => $this->requireNumber($payload['quorum'] ?? 50, 0, 100),
            'changeAllowed' => filter_var(
                $payload['changeAllowed'] ?? true,
                FILTER_VALIDATE_BOOL,
            ),
        ];
        $state['governance'][] = $proposal;
        $objectId = $proposal['id'];

        return $objectId;
    }

    private function approveGovernanceChange(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $proposalIndex = $this->findItemIndex(
            $state,
            'governance',
            $payload['governanceId'] ?? '',
        );
        $proposal = $state['governance'][$proposalIndex];
        if (
            $proposal['authorId'] === $actorId
            || in_array($actorId, $proposal['approvals'], true)
        ) {
            $this->fail('Un avis indépendant est requis.', 409);
        }
        $state['governance'][$proposalIndex]['approvals'][] = $actorId;
        if (count($state['governance'][$proposalIndex]['approvals']) >= 2) {
            $state['governance'][$proposalIndex]['status'] = 'reviewed';
        }
        $objectId = $proposal['id'];

        return $objectId;
    }

    private function reportModerationCase(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $objectId = $this->generateId('case');
        $state['cases'][] = [
            'id' => $objectId,
            'reporterId' => $actorId,
            'objectId' => $this->requireText(
                $payload['objectId'] ?? '',
                1,
                100,
            ),
            'reason' => $this->requireText($payload['reason'] ?? ''),
            'status' => 'under_review',
            'at' => $this->currentTimestamp(),
            'decisions' => [],
        ];

        return $objectId;
    }

    private function appealModerationCase(
        array &$state,
        array $payload,
        string $actorId,
    ): string {
        $this->findItemIndex($state, 'cases', $payload['caseId'] ?? '');
        $objectId = $this->generateId('appeal');
        $state['appeals'][] = [
            'id' => $objectId,
            'caseId' => $payload['caseId'],
            'citizenId' => $actorId,
            'reason' => $this->requireText($payload['reason'] ?? ''),
            'status' => 'pending',
            'at' => $this->currentTimestamp(),
        ];

        return $objectId;
    }

    private function reportIntegrityAlert(array &$state, array $payload): string
    {
        $objectId = $this->generateId('alert');
        $state['alerts'][] = [
            'id' => $objectId,
            'reason' => $this->requireText($payload['reason'] ?? ''),
            'status' => 'to_verify',
            'at' => $this->currentTimestamp(),
        ];

        return $objectId;
    }

    private function configureVirtualNode(array &$state, array $payload): string
    {
        $nodeIndex = $this->findItemIndex(
            $state,
            'nodes',
            $payload['nodeId'] ?? '',
        );
        $bandwidth = $this->requireNumber($payload['bandwidth'] ?? 0, 1, 1024);
        if ((int) $bandwidth != $bandwidth) {
            $this->fail('Débit entier requis.');
        }
        $state['nodes'][$nodeIndex]['bandwidth'] = (int) $bandwidth;
        $state['nodes'][$nodeIndex]['status'] = filter_var(
            $payload['enabled'] ?? false,
            FILTER_VALIDATE_BOOL,
        )
            ? 'ready'
            : 'stopped';
        $objectId = $state['nodes'][$nodeIndex]['id'];

        return $objectId;
    }

    private function synchronizeVirtualNode(
        array &$state,
        array $payload,
    ): string {
        $nodeIndex = $this->findItemIndex(
            $state,
            'nodes',
            $payload['nodeId'] ?? '',
        );
        if ($state['nodes'][$nodeIndex]['status'] === 'stopped') {
            $this->fail('Relais virtuel arrêté.');
        }
        $state['nodes'][$nodeIndex]['checkpoint'] = [
            'hash' => $state['events']
                ? end($state['events'])['hash']
                : 'GENESIS',
            'count' => count($state['events']),
            'at' => $this->currentTimestamp(),
            'publicOnly' => true,
        ];
        $state['nodes'][$nodeIndex]['status'] = 'synced';
        $objectId = $state['nodes'][$nodeIndex]['id'];

        return $objectId;
    }

    private function addResourceContribution(
        array &$state,
        array $payload,
    ): string {
        $objectId = $this->generateId('r');
        $state['resources'][] = [
            'id' => $objectId,
            'kind' => $this->requireText($payload['kind'] ?? '', 2, 80),
            'description' => $this->requireText($payload['description'] ?? ''),
            'at' => $this->currentTimestamp(),
            'simulated' => true,
        ];

        return $objectId;
    }
}
