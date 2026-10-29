<?php

declare(strict_types=1);

namespace App\Service;

/** Procédures collégiales de la simulation. Les rôles ne modifient jamais le poids d’un vote. */
final class CivicWorkflow
{
    public const MODERATION_RULES = [
        'threat' => 'Menace documentée',
        'harassment' => 'Harcèlement documenté',
        'impersonation' => 'Usurpation démontrée',
        'private_data' => 'Divulgation de données privées',
        'spam' => 'Spam ou automatisation perturbatrice',
        'falsification' => 'Falsification volontaire démontrée',
    ];

    private function index(array $items, string $id): int
    {
        foreach ($items as $index => $item) {
            if ($item['id'] === $id) {
                return $index;
            }
        }
        throw new \DomainException('Objet introuvable.', 404);
    }

    private function text(mixed $value): string
    {
        if (
            !is_string($value)
            || mb_strlen(trim($value)) < 4
            || mb_strlen($value) > 3000
        ) {
            throw new \DomainException('Un motif documenté de 4 à 3000 caractères est nécessaire.');
        }

        return trim($value);
    }

    public function hasRole(array $citizen, array $allowed): bool
    {
        return (bool) array_intersect(
            $citizen['roles'] ?? [$citizen['role'] ?? 'citizen'],
            $allowed,
        );
    }

    private function role(array $citizen, array $allowed): void
    {
        if (!$this->hasRole($citizen, $allowed)) {
            throw new \DomainException('Ce rôle ne peut pas exécuter cette procédure.', 403);
        }
    }

    public function apply(
        array &$state,
        string $type,
        array $payload,
        array $actor,
    ): string {
        return match ($type) {
            'simulation.advance' => $this->advanceSimulation($state, $actor),
            'territory.revise' => $this->reviseAdaptation(
                $state,
                $payload,
                $actor,
            ),
            'proposal.transition' => $this->transition(
                $state,
                $payload,
                $actor,
            ),
            'governance.enact' => $this->enact($state, $payload, $actor),
            'moderation.decide' => $this->decide($state, $payload, $actor),
            'moderation.confirm' => $this->confirm($state, $payload, $actor),
            'moderation.resolve' => $this->resolve($state, $payload, $actor),
            'integrity.review' => $this->reviewIntegrity(
                $state,
                $payload,
                $actor,
            ),
            'consultation.review' => $this->reviewConsultation(
                $state,
                $payload,
                $actor,
            ),
            'citizen.preferences' => $this->preferences(
                $state,
                $payload,
                $actor,
            ),
            'proposal.reformulate' => $this->reformulate(
                $state,
                $payload,
                $actor,
            ),
            default => throw new \DomainException('Opération inconnue.', 404),
        };
    }

    private function advanceSimulation(array &$state, array $actor): string
    {
        $this->role($actor, ['governor']);
        if (empty($actor['fictional']) || empty($state['simulation'])) {
            throw new \DomainException('Opération réservée au laboratoire fictif.', 403);
        }

        return new MentalSimulation()->advance($state);
    }

    private function reviseAdaptation(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $index = $this->index(
            $state['adaptations'],
            $payload['adaptationId'] ?? '',
        );
        $adaptation = $state['adaptations'][$index];
        if (
            ($adaptation['authorId'] ?? '') !== $actor['id']
            || $adaptation['territoryId'] !== $actor['territoryId']
        ) {
            throw new \DomainException('Auteur local de la fiche requis.', 403);
        }
        $snapshot = $adaptation;
        unset($snapshot['revisions']);
        $adaptation['revisions'][] = [
            'version' => $adaptation['version'],
            'snapshot' => $snapshot,
            'at' => gmdate('c'),
            'actorId' => $actor['id'],
            'reason' => $this->text($payload['reason'] ?? ''),
        ];
        foreach (
            ['probableConsequences', 'observedConsequences', 'source', 'review'] as $field
        ) {
            $adaptation[$field] = $this->text($payload[$field] ?? '');
        }
        ++$adaptation['version'];
        $state['adaptations'][$index] = $adaptation;

        return $adaptation['id'];
    }

    private function transition(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $index = $this->index(
            $state['proposals'],
            $payload['proposalId'] ?? '',
        );
        $proposal = $state['proposals'][$index];
        if ($proposal['authorId'] !== $actor['id']) {
            throw new \DomainException('Auteur du chemin requis.', 403);
        }
        foreach ($state['consultations'] as $consultation) {
            if (
                $consultation['proposalId'] === $proposal['id']
                && $consultation['status'] === 'open'
            ) {
                throw new \DomainException('Consultation non clôturée : texte et cycle protégés.', 409);
            }
        }
        $allowed = [
            'draft' => ['withdrawn'],
            'published' => [
                'completed',
                'deferred',
                'withdrawn',
                'inapplicable',
            ],
            'completed' => ['ready', 'deferred', 'withdrawn'],
            'ready' => ['deferred', 'withdrawn'],
            'result' => ['archived', 'deferred'],
            'experiment' => ['observing', 'archived'],
            'observing' => ['evaluated'],
            'evaluated' => ['archived', 'published'],
            'deferred' => ['published', 'withdrawn'],
            'withdrawn' => ['draft'],
            'inapplicable' => ['published', 'archived'],
            'archived' => ['published'],
        ];
        $target = $payload['status'] ?? '';
        if (!in_array($target, $allowed[$proposal['status']] ?? [], true)) {
            throw new \DomainException('Transition non autorisée depuis cet état.', 409);
        }
        $state['proposals'][$index]['transitions'][] = [
            'from' => $proposal['status'],
            'to' => $target,
            'reason' => $this->text($payload['reason'] ?? ''),
            'at' => gmdate('c'),
            'actorId' => $actor['id'],
        ];
        $state['proposals'][$index]['status'] = $target;

        return $proposal['id'];
    }

    private function enact(array &$state, array $payload, array $actor): string
    {
        $this->role($actor, ['governor']);
        $index = $this->index(
            $state['governance'],
            $payload['governanceId'] ?? '',
        );
        $proposal = $state['governance'][$index];
        if (
            $proposal['status'] !== 'reviewed'
            || count(array_unique($proposal['approvals'])) < 2
        ) {
            throw new \DomainException('Deux avis indépendants et une proposition examinée sont requis.', 409);
        }
        $quorum = $proposal['proposedRules']['quorum'] ?? 50;
        if (!is_numeric($quorum) || $quorum < 0 || $quorum > 100) {
            throw new \DomainException('Quorum invalide.');
        }
        $rules = [
            'id' => 'rules_' . bin2hex(random_bytes(8)),
            'governanceId' => $proposal['id'],
            'quorum' => (float) $quorum,
            'changeAllowed' => $proposal['proposedRules']['changeAllowed'] ?? true,
            'at' => gmdate('c'),
            'reason' => $this->text($payload['reason'] ?? ''),
        ];
        $state['ruleSets'][] = $rules;
        $state['governance'][$index]['status'] = 'enacted';
        $state['governance'][$index]['ruleSetId'] = $rules['id'];

        return $proposal['id'];
    }

    private function decide(array &$state, array $payload, array $actor): string
    {
        $this->role($actor, ['conciliator']);
        $index = $this->index($state['cases'], $payload['caseId'] ?? '');
        $case = $state['cases'][$index];
        $target = $case['objectId'];
        foreach ($state['proposals'] as $proposal) {
            if ($proposal['id'] === $case['objectId']) {
                $target = $proposal['authorId'];
            }
        }
        if (isset($payload['targetId']) && $payload['targetId'] !== $target) {
            throw new \DomainException('Le personnage doit être lié à l’objet du dossier.', 403);
        }
        $this->index($state['citizens'], $target);
        if (
            in_array($actor['id'], [$target, $case['reporterId']], true)
            || $case['status'] !== 'under_review'
        ) {
            throw new \DomainException('Conciliateur indépendant et dossier ouvert requis.', 409);
        }
        $rule = $payload['rule'] ?? '';
        $measure = $payload['measure'] ?? '';
        $days = filter_var($payload['days'] ?? 1, FILTER_VALIDATE_INT);
        if (
            !isset(self::MODERATION_RULES[$rule])
            || !in_array(
                $measure,
                ['warning', 'publication_pause', 'dismiss'],
                true,
            )
            || !$days
            || $days < 1
            || $days > 30
        ) {
            throw new \DomainException('Règle, mesure ou durée invalide. Une opinion seule ne peut être sanctionnée.');
        }
        $decision = [
            'id' => 'decision_' . bin2hex(random_bytes(8)),
            'targetId' => $target,
            'officerId' => $actor['id'],
            'rule' => $rule,
            'measure' => $measure,
            'days' => $days,
            'facts' => $this->text($payload['facts'] ?? ''),
            'evidence' => $this->text($payload['evidence'] ?? ''),
            'reason' => $this->text($payload['reason'] ?? ''),
            'at' => gmdate('c'),
            'status' => 'proposed',
        ];
        $state['cases'][$index]['decisions'][] = $decision;
        $state['cases'][$index]['status'] = 'awaiting_confirmation';

        return $case['id'];
    }

    private function confirm(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $this->role($actor, ['conciliator']);
        $index = $this->index($state['cases'], $payload['caseId'] ?? '');
        $case = $state['cases'][$index];
        $decision = end($case['decisions']);
        if (
            $case['status'] !== 'awaiting_confirmation'
            || in_array(
                $actor['id'],
                [
                    $decision['targetId'],
                    $decision['officerId'],
                    $case['reporterId'],
                ],
                true,
            )
        ) {
            throw new \DomainException('Une seconde confirmation indépendante est nécessaire.', 403);
        }
        $decision['confirmedBy'] = $actor['id'];
        $decision['status'] = 'confirmed';
        $decision['confirmedAt'] = gmdate('c');
        $decision['confirmationReason'] = $this->text($payload['reason'] ?? '');
        $decision['until'] = gmdate('c', time() + $decision['days'] * 86400);
        $state['cases'][$index]['decisions'][
            array_key_last($case['decisions'])
        ] = $decision;
        $state['cases'][$index]['status'] = 'decided';
        if ($decision['measure'] === 'publication_pause') {
            $citizen = $this->index($state['citizens'], $decision['targetId']);
            $state['citizens'][$citizen]['publicationRestrictions'][] = [
                'decisionId' => $decision['id'],
                'until' => $decision['until'],
                'revoked' => false,
            ];
        }

        return $case['id'];
    }

    private function resolve(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $this->role($actor, ['conciliator', 'auditor']);
        $index = $this->index($state['appeals'], $payload['appealId'] ?? '');
        $appeal = $state['appeals'][$index];
        $caseIndex = $this->index($state['cases'], $appeal['caseId']);
        $case = $state['cases'][$caseIndex];
        $decision = $case['decisions'] ? end($case['decisions']) : null;
        $excluded = [
            $appeal['citizenId'],
            $case['reporterId'],
            $decision['officerId'] ?? '',
            $decision['confirmedBy'] ?? '',
        ];
        if (
            $appeal['status'] !== 'pending'
            || in_array($actor['id'], $excluded, true)
        ) {
            throw new \DomainException('Réexaminateur indépendant et recours en attente requis.', 403);
        }
        $outcome = $payload['outcome'] ?? '';
        if (!in_array($outcome, ['upheld', 'revoked'], true)) {
            throw new \DomainException('Conclusion de recours invalide.');
        }
        $state['appeals'][$index]['status'] = $outcome;
        $state['appeals'][$index]['resolution'] = [
            'by' => $actor['id'],
            'reason' => $this->text($payload['reason'] ?? ''),
            'at' => gmdate('c'),
        ];
        if ($outcome === 'revoked' && $decision) {
            $target = $this->index($state['citizens'], $decision['targetId']);
            foreach (
                $state['citizens'][$target]['publicationRestrictions'] ?? [] as $key => $restriction
            ) {
                if ($restriction['decisionId'] === $decision['id']) {
                    $state['citizens'][$target]['publicationRestrictions'][
                        $key
                    ]['revoked'] = true;
                }
            }
            $state['cases'][$caseIndex]['status'] = 'revoked_on_appeal';
        }

        return $appeal['id'];
    }

    private function reviewIntegrity(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $this->role($actor, ['auditor']);
        $index = $this->index($state['alerts'], $payload['alertId'] ?? '');
        $allowed = [
            'to_verify' => ['reproducible', 'dismissed'],
            'reproducible' => ['security_incident', 'dismissed'],
            'security_incident' => ['confirmed_fraud', 'corrected'],
        ];
        $status = $payload['status'] ?? '';
        if (
            !in_array(
                $status,
                $allowed[$state['alerts'][$index]['status']] ?? [],
                true,
            )
        ) {
            throw new \DomainException('Transition d’alerte invalide.');
        }
        if (
            $status === 'confirmed_fraud'
            && in_array(
                $actor['id'],
                array_column($state['alerts'][$index]['reviews'] ?? [], 'by'),
                true,
            )
        ) {
            throw new \DomainException('La qualification de fraude requiert un nouvel auditeur indépendant.', 403);
        }
        $state['alerts'][$index]['reviews'][] = [
            'by' => $actor['id'],
            'from' => $state['alerts'][$index]['status'],
            'to' => $status,
            'proof' => $this->text($payload['proof'] ?? ''),
            'at' => gmdate('c'),
        ];
        $state['alerts'][$index]['status'] = $status;

        return $state['alerts'][$index]['id'];
    }

    private function reviewConsultation(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $this->role($actor, ['auditor', 'governor']);
        $index = $this->index(
            $state['consultations'],
            $payload['consultationId'] ?? '',
        );
        $consultation = $state['consultations'][$index];
        if ($consultation['status'] !== 'closed') {
            throw new \DomainException('Seul un résultat proclamé peut faire l’objet de cette procédure.', 409);
        }
        $reason = $this->text($payload['reason'] ?? '');
        $reviews = $consultation['cancellationReviews'] ?? [];
        if (in_array($actor['id'], array_column($reviews, 'by'), true)) {
            throw new \DomainException('Avis déjà déposé.', 409);
        }
        $reviews[] = [
            'by' => $actor['id'],
            'reason' => $reason,
            'proof' => $this->text($payload['proof'] ?? ''),
            'at' => gmdate('c'),
        ];
        $state['consultations'][$index]['cancellationReviews'] = $reviews;
        if (count($reviews) >= 2) {
            $state['consultations'][$index]['previousResult'] =
                $consultation['result'] ?? null;
            $state['consultations'][$index]['status'] = 'cancelled';
            $state['consultations'][$index]['cancelledAt'] = gmdate('c');
        }

        return $consultation['id'];
    }

    private function preferences(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $index = $this->index($state['citizens'], $actor['id']);
        $locale = $payload['locale'] ?? 'fr_FR';
        if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $locale)) {
            throw new \DomainException('Locale invalide.');
        }
        $state['citizens'][$index]['preferences'] = [
            'locale' => $locale,
            'recommendations' => filter_var(
                $payload['recommendations'] ?? false,
                FILTER_VALIDATE_BOOL,
            ),
            'largeText' => filter_var(
                $payload['largeText'] ?? false,
                FILTER_VALIDATE_BOOL,
            ),
        ];

        return $actor['id'];
    }

    private function reformulate(
        array &$state,
        array $payload,
        array $actor,
    ): string {
        $index = $this->index(
            $state['proposals'],
            $payload['proposalId'] ?? '',
        );
        $proposal = $state['proposals'][$index];
        if ($proposal['authorId'] !== $actor['id']) {
            throw new \DomainException('Seul l’auteur valide une reformulation.', 403);
        }
        $locale = $payload['locale'] ?? 'fr_FR';
        if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $locale)) {
            throw new \DomainException('Locale invalide.');
        }
        $state['proposals'][$index]['reformulations'][] = [
            'id' => 'text_' . bin2hex(random_bytes(8)),
            'referenceVersion' => $proposal['version'],
            'locale' => $locale,
            'level' => $this->text($payload['level'] ?? ''),
            'text' => $this->text($payload['text'] ?? ''),
            'glossary' => $this->text($payload['glossary'] ?? ''),
            'validatedBy' => $actor['id'],
            'at' => gmdate('c'),
            'warning' => 'Reformulation documentaire : seul le texte figé de la consultation fait référence.',
        ];

        return $proposal['id'];
    }
}
