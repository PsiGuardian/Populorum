<?php

declare(strict_types=1);

namespace App\Service;

/** Fictive procurement laboratory. No contract, notification, dispatch or payment is sent. */
final class PublicProcurement
{
    public const WEIGHTS = [
        'price' => 50,
        'quality' => 30,
        'delay' => 10,
        'warranty' => 10,
    ];
    private const MAX_CENTS = 10000000000;

    public function __construct(private ?int $now = null)
    {
    }

    private function now(): int
    {
        return $this->now ?? time();
    }

    /** Conservative reservation: the laboratory has no verified timetable yet. */
    public static function busy(
        array $state,
        string $citizenId,
        string $exceptTender = '',
    ): bool {
        foreach ($state['missions'] ?? [] as $mission) {
            if (
                ($mission['workerId'] ?? null) === $citizenId
                && in_array(
                    $mission['status'],
                    ['offered', 'accepted', 'in_progress'],
                    true,
                )
            ) {
                return true;
            }
        }
        foreach ($state['tenders'] ?? [] as $tender) {
            if (
                $tender['id'] !== $exceptTender
                && $tender['selection']
                && $tender['selection']['kind'] === 'participation'
                && in_array(
                    $tender['status'],
                    ['awarded', 'in_progress', 'paused'],
                    true,
                )
                && isset($tender['participation']['volunteers'][$citizenId])
            ) {
                return true;
            }
        }

        return false;
    }

    private function text(
        array $data,
        string $key,
        bool $optional = false,
    ): string {
        $value = $data[$key] ?? '';
        if (
            !is_string($value)
            || mb_strlen($value) > 3000
            || (!$optional && mb_strlen(trim($value)) < 2)
        ) {
            throw new \DomainException('Texte invalide : ' . $key, 400);
        }

        return trim($value);
    }

    private function integer(
        array $data,
        string $key,
        int $min = 0,
        int $max = self::MAX_CENTS,
    ): int {
        $raw = $data[$key] ?? null;
        if (!is_int($raw) && !is_string($raw) && !is_float($raw)) {
            throw new \DomainException('Entier requis : ' . $key, 400);
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new \DomainException('Entier hors limites : ' . $key, 400);
        }

        return $value;
    }

    /** All quoted amounts use the same TTC basis, including non-recoverable charges. */
    public function lineItems(string $input): array
    {
        $lines = preg_split("/\R/u", trim($input));
        if (!$lines || count($lines) > 20) {
            throw new \DomainException('De 1 à 20 lignes de devis sont requises.', 400);
        }
        $items = [];
        $total = 0;
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 3) {
                throw new \DomainException('Format : libellé | quantité entière | prix unitaire TTC en centimes.', 400);
            }
            $quantity = $this->integer(
                ['quantity' => $parts[1]],
                'quantity',
                1,
                100000,
            );
            $unitCents = $this->integer(
                ['unit' => $parts[2]],
                'unit',
                0,
                100000000,
            );
            $subtotal = $quantity * $unitCents;
            $total += $subtotal;
            if ($total > self::MAX_CENTS) {
                throw new \DomainException('Plafond pédagogique de 100 millions d’euros dépassé.', 400);
            }
            $items[] = [
                'label' => $this->text(['label' => $parts[0]], 'label'),
                'quantity' => $quantity,
                'unitCents' => $unitCents,
                'totalCents' => $subtotal,
            ];
        }
        if ($total < 1) {
            throw new \DomainException('Le devis doit avoir un montant positif.', 400);
        }

        return ['items' => $items, 'totalCents' => $total];
    }

    /** Authenticated encryption; per-lab server private key is never in public exports. */
    private function seal(array $offer, array $state, string $aad): string
    {
        $private = $state['keyPair']['privateKey'] ?? '';
        if (!$private) {
            throw new \DomainException('Initialisez le laboratoire signé avant de déposer une offre.', 409);
        }
        $nonce = random_bytes(12);
        $json = json_encode(
            $offer,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );
        // Fixed-size envelopes avoid disclosing quote length. Contents only become public on opening.
        if (strlen($json) > 16384) {
            throw new \DomainException('Offre trop volumineuse.', 400);
        }
        $cipher = openssl_encrypt(
            str_pad($json, 16384, ' '),
            'aes-256-gcm',
            hash('sha256', 'populorum-offers-v1:' . $private, true),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
        );
        if ($cipher === false) {
            throw new \RuntimeException('Chiffrement impossible.');
        }

        return base64_encode($nonce . $tag . $cipher);
    }

    private function unseal(string $envelope, array $state, string $aad): array
    {
        $binary = base64_decode($envelope, true);
        if ($binary === false || strlen($binary) !== 16412) {
            throw new \DomainException('Enveloppe invalide.', 409);
        }
        $plain = openssl_decrypt(
            substr($binary, 28),
            'aes-256-gcm',
            hash(
                'sha256',
                'populorum-offers-v1:' .
                    ($state['keyPair']['privateKey'] ?? ''),
                true,
            ),
            OPENSSL_RAW_DATA,
            substr($binary, 0, 12),
            substr($binary, 12, 16),
            $aad,
        );
        if ($plain === false) {
            throw new \DomainException('Enveloppe altérée ou clé indisponible.', 409);
        }

        return json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
    }

    private function owner(array $tender, array $actor): void
    {
        if ($tender['authorId'] !== $actor['id']) {
            throw new \DomainException('Seul le pilote fictif de ce besoin peut effectuer cette action.', 403);
        }
    }

    private function independent(array $tender, array $actor): void
    {
        if (
            $actor['territoryId'] !== $tender['territoryId']
            || in_array(
                $actor['id'],
                array_merge(
                    [$tender['authorId']],
                    array_column($tender['sealedOffers'], 'bidderId'),
                    array_keys($tender['participation']['volunteers'] ?? []),
                ),
                true,
            )
        ) {
            throw new \DomainException('Un tiers du territoire, distinct du pilote, des soumissionnaires et des participants, est requis.', 403);
        }
    }

    private function phase(array $tender, array $allowed): void
    {
        if (!in_array($tender['status'], $allowed, true)) {
            throw new \DomainException('Cette action ne correspond pas à la phase du dossier.', 409);
        }
    }

    private function intake(array $tender): void
    {
        $this->phase($tender, ['open']);
        if ($this->now() >= $tender['closesAt']) {
            throw new \DomainException('La date limite de dépôt est dépassée.', 409);
        }
    }

    /** Ranking is advisory: quality assessments and the final decision stay explicit. */
    public function ranking(array $tender): array
    {
        $offers = array_values(
            array_filter(
                $tender['offers'],
                static fn (array $offer): bool => ($tender['evaluations'][
                    $offer['id']
                ]['compliant'] ??
                    false) ===
                    true,
            ),
        );
        if (!$offers) {
            return [];
        }
        $costs = array_map(
            static fn (array $offer): int => $offer['totalCents'] +
                $offer['annualMaintenanceCents'] * $tender['horizonYears'],
            $offers,
        );
        $minCost = min($costs);
        $minDays = min(array_column($offers, 'days'));
        $maxWarranty = max(1, ...array_column($offers, 'warrantyMonths'));
        foreach ($offers as $i => &$offer) {
            $offer['lifecycleCents'] = $costs[$i];
            $offer['score'] = round(
                (self::WEIGHTS['price'] * $minCost) / $costs[$i] +
                    (self::WEIGHTS['quality'] *
                        $tender['evaluations'][$offer['id']]['qualityScore']) /
                        100 +
                    (self::WEIGHTS['delay'] * $minDays) / $offer['days'] +
                    (self::WEIGHTS['warranty'] * $offer['warrantyMonths']) /
                        $maxWarranty,
                2,
            );
            $offer['priceAlert'] =
                $tender['referenceCents'] > 0
                && $offer['totalCents'] > $tender['referenceCents'] * 2;
        }
        unset($offer);
        usort(
            $offers,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?:
            strcmp($a['id'], $b['id']),
        );

        return $offers;
    }

    public function apply(
        array &$state,
        string $action,
        array $payload,
        array $actor,
    ): string {
        $state['tenders'] ??= [];
        if ($action === 'tender.create') {
            if (count($state['tenders']) >= 100) {
                throw new \DomainException('Limite de 100 dossiers pédagogiques atteinte.', 429);
            }
            $service = $this->text($payload, 'service');
            if (!isset(EssentialServices::CATALOG[$service])) {
                throw new \DomainException('Service inconnu.', 400);
            }
            $date = $this->text($payload, 'deadline');
            $deadline = \DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $date,
                new \DateTimeZone('Europe/Paris'),
            );
            if (
                !$deadline
                || $deadline->format('Y-m-d H:i') !== $date
                || $deadline->getTimestamp() < $this->now() + 60
                || $deadline->getTimestamp() > $this->now() + 365 * 86400
            ) {
                throw new \DomainException('Date limite : AAAA-MM-JJ HH:MM, heure de Paris, entre une minute et un an dans le futur.', 400);
            }
            $proposalId = $this->text($payload, 'proposalId', true);
            $missionId = $this->text($payload, 'missionId', true);
            foreach (
                ['proposalId' => 'proposals', 'missionId' => 'missions'] as $key => $collection
            ) {
                $id = $key === 'proposalId' ? $proposalId : $missionId;
                if ($id !== '') {
                    $item =
                        array_column($state[$collection] ?? [], null, 'id')[
                            $id
                        ] ?? null;
                    if (
                        !$item
                        || $item['territoryId'] !== $actor['territoryId']
                    ) {
                        throw new \DomainException('Lien causal ou mission absent du territoire.', 400);
                    }
                }
            }
            $id = 'tender_' . bin2hex(random_bytes(10));
            $state['tenders'][] = [
                'id' => $id,
                'authorId' => $actor['id'],
                'territoryId' => $actor['territoryId'],
                'title' => $this->text($payload, 'title'),
                'scope' => $this->text($payload, 'scope'),
                'location' => $this->text($payload, 'location'),
                'risks' => $this->text($payload, 'risks'),
                'cause' => $this->text($payload, 'cause'),
                'expectedEffect' => $this->text($payload, 'expectedEffect'),
                'indicator' => $this->text($payload, 'indicator'),
                'uncertainty' => $this->text($payload, 'uncertainty'),
                'service' => $service,
                'proposalId' => $proposalId,
                'missionId' => $missionId,
                'referenceCents' => $this->integer($payload, 'referenceCents'),
                'referenceBasis' => $this->text($payload, 'referenceBasis'),
                'budgetCents' => $this->integer($payload, 'budgetCents', 1),
                'horizonYears' => $this->integer(
                    $payload,
                    'horizonYears',
                    1,
                    30,
                ),
                'weights' => self::WEIGHTS,
                'closesAt' => $deadline->getTimestamp(),
                'status' => 'open',
                'sealedOffers' => [],
                'offers' => [],
                'questions' => [],
                'evaluations' => [],
                'alerts' => [],
                'amendments' => [],
                'invoices' => [],
                'inspections' => [],
                'history' => [],
                'participation' => null,
                'selection' => null,
                'authorizedCents' => null,
                'actualCents' => null,
                'payment' => null,
                'fictional' => true,
            ];

            return $id;
        }
        foreach ($state['tenders'] as &$tender) {
            if ($tender['id'] !== ($payload['tenderId'] ?? '')) {
                continue;
            }
            $before = $tender['status'];
            // Validate history text before any change; engine persistence remains atomic.
            $reason = $this->text($payload, 'reason');
            switch ($action) {
                case 'tender.question':
                    $this->intake($tender);
                    if (count($tender['questions']) >= 100) {
                        throw new \DomainException('Limite de questions atteinte.', 429);
                    }
                    $tender['questions'][] = [
                        'id' => 'q_' . bin2hex(random_bytes(6)),
                        'authorId' => $actor['id'],
                        'text' => $this->text($payload, 'question'),
                        'answer' => null,
                        'at' => gmdate('c', $this->now()),
                    ];
                    break;
                case 'tender.answer':
                    $this->owner($tender, $actor);
                    $this->intake($tender);
                    $found = false;
                    foreach ($tender['questions'] as &$question) {
                        if (
                            $question['id'] ===
                                ($payload['questionId'] ?? '')
                            && $question['answer'] === null
                        ) {
                            $question['answer'] = $this->text(
                                $payload,
                                'answer',
                            );
                            $question['answeredAt'] = gmdate('c', $this->now());
                            $found = true;
                        }
                    }
                    unset($question);
                    if (!$found) {
                        throw new \DomainException('Question absente ou déjà répondue.', 409);
                    }
                    break;
                case 'tender.bid':
                    $this->intake($tender);
                    if (
                        $actor['id'] === $tender['authorId']
                        || ($payload['fictionalConsent'] ?? false) !== true
                    ) {
                        throw new \DomainException('Soumissionnaire distinct du pilote et données entièrement fictives requis.', 403);
                    }
                    if (
                        count($tender['sealedOffers']) >= 100
                        || count(
                            array_filter(
                                $tender['sealedOffers'],
                                static fn (array $offer): bool => $offer[
                                    'bidderId'
                                ] === $actor['id'],
                            ),
                        ) >= 5
                    ) {
                        throw new \DomainException('Limite de dépôts atteinte (100 au total, 5 versions par personnage).', 429);
                    }
                    $id = 'offer_' . bin2hex(random_bytes(10));
                    $offer = $this->lineItems(
                        $this->text($payload, 'lines'),
                    ) + [
                        'id' => $id,
                        'bidderId' => $actor['id'],
                        'company' => $this->text($payload, 'company'),
                        'submissionReason' => $reason,
                        'qualifications' => $this->text(
                            $payload,
                            'qualifications',
                        ),
                        'exclusions' => $this->text($payload, 'exclusions'),
                        'days' => $this->integer($payload, 'days', 1, 3650),
                        'warrantyMonths' => $this->integer(
                            $payload,
                            'warrantyMonths',
                            0,
                            600,
                        ),
                        'annualMaintenanceCents' => $this->integer(
                            $payload,
                            'annualMaintenanceCents',
                            0,
                            100000000,
                        ),
                    ];
                    $tender['sealedOffers'][] = [
                        'id' => $id,
                        'bidderId' => $actor['id'],
                        'at' => gmdate('c', $this->now()),
                        'withdrawn' => false,
                        'envelope' => $this->seal(
                            $offer,
                            $state,
                            $tender['id'] . ':' . $id,
                        ),
                    ];
                    break;
                case 'tender.withdraw':
                    $this->intake($tender);
                    $found = false;
                    foreach ($tender['sealedOffers'] as &$sealed) {
                        if (
                            $sealed['bidderId'] === $actor['id']
                            && !$sealed['withdrawn']
                        ) {
                            $sealed['withdrawn'] = true;
                            $found = true;
                        }
                    }
                    unset($sealed);
                    if (!$found) {
                        throw new \DomainException('Aucune offre active à retirer.', 409);
                    }
                    break;
                case 'tender.open':
                    $this->owner($tender, $actor);
                    $this->phase($tender, ['open']);
                    if ($this->now() < $tender['closesAt']) {
                        throw new \DomainException('Ouverture anticipée refusée.', 409);
                    }
                    $latest = [];
                    foreach ($tender['sealedOffers'] as $sealed) {
                        if (!$sealed['withdrawn']) {
                            $latest[$sealed['bidderId']] = $sealed;
                        }
                    }
                    $offers = [];
                    foreach ($latest as $sealed) {
                        $offers[] = $this->unseal(
                            $sealed['envelope'],
                            $state,
                            $tender['id'] . ':' . $sealed['id'],
                        );
                    }
                    $tender['offers'] = $offers;
                    $tender['status'] = 'review';
                    break;
                case 'tender.evaluate':
                    $this->phase($tender, ['review']);
                    $this->independent($tender, $actor);
                    $offerId = $this->text($payload, 'offerId');
                    if (
                        !in_array(
                            $offerId,
                            array_column($tender['offers'], 'id'),
                            true,
                        )
                    ) {
                        throw new \DomainException('Offre inconnue.', 404);
                    }
                    $tender['evaluations'][$offerId] = [
                        'actorId' => $actor['id'],
                        'qualityScore' => $this->integer(
                            $payload,
                            'qualityScore',
                            0,
                            100,
                        ),
                        'compliant' => ($payload['compliant'] ?? false) === true,
                        'reason' => $reason,
                    ];
                    break;
                case 'tender.alert':
                    if (count($tender['alerts']) >= 100) {
                        throw new \DomainException('Limite de signalements atteinte.', 429);
                    }
                    $tender['alerts'][] = [
                        'id' => 'alert_' . bin2hex(random_bytes(6)),
                        'actorId' => $actor['id'],
                        'evidence' => $this->text($payload, 'evidence'),
                        'reason' => $reason,
                        'response' => null,
                    ];
                    break;
                case 'tender.explain':
                    $this->independent($tender, $actor);
                    $found = false;
                    foreach ($tender['alerts'] as &$alert) {
                        if (
                            $alert['id'] === ($payload['alertId'] ?? '')
                            && $alert['actorId'] !== $actor['id']
                            && $alert['response'] === null
                        ) {
                            $alert['response'] = [
                                'actorId' => $actor['id'],
                                'text' => $this->text($payload, 'response'),
                                'at' => gmdate('c', $this->now()),
                            ];
                            $found = true;
                        }
                    }
                    unset($alert);
                    if (!$found) {
                        throw new \DomainException('Signalement absent, déjà examiné ou auto-examen refusé.', 409);
                    }
                    break;
                case 'tender.participation':
                    $this->owner($tender, $actor);
                    $this->intake($tender);
                    if (
                        EssentialServices::CATALOG[$tender['service']][
                            'regulated'
                        ]
                        || $tender['participation'] !== null
                        || ($payload['lowRisk'] ?? false) !== true
                    ) {
                        throw new \DomainException('Plan unique, tâche simple à faible risque et service non réglementé requis.', 409);
                    }
                    $costs = [];
                    foreach (
                        [
                            'materialsCents',
                            'insuranceCents',
                            'supervisionCents',
                            'controlCents',
                        ] as $key
                    ) {
                        $costs[$key] = $this->integer(
                            $payload,
                            $key,
                            1,
                            100000000,
                        );
                    }
                    $tender['participation'] = $costs + [
                        'scope' => $this->text($payload, 'scope'),
                        'safety' => $this->text($payload, 'safety'),
                        'fallback' => $this->text($payload, 'fallback'),
                        'hoursNeeded' => $this->integer(
                            $payload,
                            'hoursNeeded',
                            1,
                            1000,
                        ),
                        'hourValueCents' => $this->integer(
                            $payload,
                            'hourValueCents',
                            1,
                            100000,
                        ),
                        'costCents' => array_sum($costs),
                        'volunteers' => [],
                        'validation' => null,
                    ];
                    break;
                case 'tender.volunteer':
                    if (($payload['consent'] ?? false) === true) {
                        $this->intake($tender);
                    } else {
                        $this->phase($tender, [
                            'open',
                            'review',
                            'awarded',
                            'in_progress',
                        ]);
                    }
                    $plan = $tender['participation'];
                    if (!$plan) {
                        throw new \DomainException('Aucun plan participatif.', 409);
                    }
                    if (($payload['consent'] ?? false) !== true) {
                        if (!isset($plan['volunteers'][$actor['id']])) {
                            throw new \DomainException('Aucun engagement personnel à retirer.', 409);
                        }
                        unset(
                            $tender['participation']['volunteers'][
                                $actor['id']
                            ],
                        );
                        if (
                            in_array(
                                $before,
                                ['awarded', 'in_progress'],
                                true,
                            )
                            && $tender['selection']['kind'] === 'participation'
                        ) {
                            $tender['status'] = 'paused';
                        }
                    } else {
                        $worker =
                            array_column($state['workers'] ?? [], null, 'id')[
                                $actor['id']
                            ] ?? null;
                        $hours = $this->integer($payload, 'hours', 1, 40);
                        if (
                            !$worker
                            || !$worker['available']
                            || !$worker['optIn']
                            || $worker['territoryId'] !== $tender['territoryId']
                            || !in_array(
                                $tender['service'],
                                $worker['skills'],
                                true,
                            )
                            || $hours > $worker['capacityHours']
                            || self::busy($state, $actor['id'], $tender['id'])
                        ) {
                            throw new \DomainException('Profil volontaire, disponible, compétent et du territoire requis ; aucune habilitation réelle attestée.', 409);
                        }
                        $tender['participation']['volunteers'][
                            $actor['id']
                        ] = $hours;
                    }
                    // Changed availability invalidates any preceding review.
                    $tender['participation']['validation'] = null;
                    break;
                case 'tender.validate':
                    $this->phase($tender, ['review']);
                    $this->independent($tender, $actor);
                    if (
                        !$tender['participation']
                        || ($payload['checked'] ?? false) !== true
                    ) {
                        throw new \DomainException('Plan et vérification explicite des risques, assurances, encadrement, périmètre et repli requis.', 409);
                    }
                    $tender['participation']['validation'] = [
                        'actorId' => $actor['id'],
                        'reason' => $reason,
                    ];
                    break;
                case 'tender.award':
                    $this->owner($tender, $actor);
                    $this->phase($tender, ['review']);
                    foreach ($tender['alerts'] as $alert) {
                        if ($alert['response'] === null) {
                            throw new \DomainException('Chaque signalement doit être examiné avant le choix.', 409);
                        }
                    }
                    foreach ($tender['offers'] as $offer) {
                        if (!isset($tender['evaluations'][$offer['id']])) {
                            throw new \DomainException('Toutes les offres doivent être examinées avant le choix.', 409);
                        }
                    }
                    $choice = $payload['selection'] ?? '';
                    if ($choice === 'participation') {
                        $plan = $tender['participation'];
                        if (
                            !$plan
                            || !$plan['validation']
                            || array_sum($plan['volunteers']) <
                                $plan['hoursNeeded']
                        ) {
                            throw new \DomainException('Validation indépendante et assez d’heures volontaires sont requises.', 409);
                        }
                        $this->checkVolunteers($state, $tender);
                        $selected = [
                            'kind' => 'participation',
                            'id' => 'participation',
                            'operatorId' => $actor['id'],
                            'amountCents' => $plan['costCents'],
                        ];
                    } else {
                        $ranked = array_column(
                            $this->ranking($tender),
                            null,
                            'id',
                        );
                        if (!is_string($choice) || !isset($ranked[$choice])) {
                            throw new \DomainException('Offre conforme examinée ou plan participatif validé requis.', 409);
                        }
                        $offer = $ranked[$choice];
                        if (
                            $offer['priceAlert']
                            && mb_strlen(
                                $this->text(
                                    $payload,
                                    'priceJustification',
                                    true,
                                ),
                            ) < 2
                        ) {
                            throw new \DomainException('Écart supérieur à deux fois la référence : justification spécifique requise.', 409);
                        }
                        $selected = [
                            'kind' => 'company',
                            'id' => $choice,
                            'operatorId' => $offer['bidderId'],
                            'amountCents' => $offer['totalCents'],
                            'score' => $offer['score'],
                        ];
                    }
                    if ($selected['amountCents'] > $tender['budgetCents']) {
                        throw new \DomainException('Le choix dépasse le budget publié : nouveau besoin à préparer.', 409);
                    }
                    $tender['selection'] = $selected + [
                        'reason' => $reason,
                        'priceJustification' => $this->text(
                            $payload,
                            'priceJustification',
                            true,
                        ),
                    ];
                    $tender['authorizedCents'] = $selected['amountCents'];
                    $tender['status'] = 'awarded';
                    break;
                case 'tender.amend':
                    $this->owner($tender, $actor);
                    $this->phase($tender, ['awarded', 'in_progress']);
                    if (
                        count($tender['amendments']) >= 30
                        || array_filter(
                            $tender['amendments'],
                            static fn (array $item): bool => $item[
                                'approval'
                            ] === null,
                        )
                    ) {
                        throw new \DomainException('Un seul avenant en attente ; 30 avenants au maximum.', 409);
                    }
                    $delta = $this->integer(
                        $payload,
                        'deltaCents',
                        -self::MAX_CENTS,
                    );
                    if (
                        $delta === 0
                        || $tender['authorizedCents'] + $delta < 1
                        || $tender['authorizedCents'] + $delta >
                            $tender['budgetCents']
                    ) {
                        throw new \DomainException('Avenant nul ou hors budget.', 409);
                    }
                    $tender['amendments'][] = [
                        'id' => 'amend_' . bin2hex(random_bytes(6)),
                        'deltaCents' => $delta,
                        'reason' => $reason,
                        'scope' => $this->text($payload, 'scope'),
                        'approval' => null,
                    ];
                    break;
                case 'tender.approve':
                    $this->phase($tender, ['awarded', 'in_progress']);
                    $this->independent($tender, $actor);
                    $found = false;
                    foreach ($tender['amendments'] as &$amendment) {
                        if (
                            $amendment['id'] ===
                                ($payload['amendmentId'] ?? '')
                            && $amendment['approval'] === null
                        ) {
                            $amendment['approval'] = [
                                'actorId' => $actor['id'],
                                'reason' => $reason,
                            ];
                            $tender['authorizedCents'] +=
                                $amendment['deltaCents'];
                            $found = true;
                        }
                    }
                    unset($amendment);
                    if (!$found) {
                        throw new \DomainException('Avenant absent ou déjà approuvé.', 409);
                    }
                    break;
                case 'tender.delivery':
                    $this->phase($tender, ['awarded', 'in_progress']);
                    if ($actor['id'] !== $tender['selection']['operatorId']) {
                        throw new \DomainException('Seul le réalisateur retenu peut déclarer la réalisation.', 403);
                    }
                    if ($before === 'awarded') {
                        if ($tender['selection']['kind'] === 'participation') {
                            $this->checkVolunteers($state, $tender);
                        }
                        $tender['status'] = 'in_progress';
                    } else {
                        if (
                            array_filter(
                                $tender['amendments'],
                                static fn (array $item): bool => $item[
                                    'approval'
                                ] === null,
                            )
                        ) {
                            throw new \DomainException('Réglez l’avenant en attente avant la réception.', 409);
                        }
                        $amount = $this->integer(
                            $payload,
                            'actualCents',
                            0,
                            $tender['authorizedCents'],
                        );
                        $volunteerHours =
                            $tender['selection']['kind'] === 'participation'
                                ? $this->integer(
                                    $payload,
                                    'volunteerHours',
                                    0,
                                    array_sum(
                                        $tender['participation']['volunteers'],
                                    ),
                                )
                                : 0;
                        $tender['invoices'][] = [
                            'reference' => $this->text($payload, 'invoice'),
                            'amountCents' => $amount,
                            'observedEffect' => $this->text(
                                $payload,
                                'observedEffect',
                            ),
                            'volunteerHours' => $volunteerHours,
                            'at' => gmdate('c', $this->now()),
                        ];
                        $tender['actualCents'] = $amount;
                        $tender['status'] = 'completed';
                    }
                    break;
                case 'tender.inspect':
                    $this->phase($tender, ['completed']);
                    $this->independent($tender, $actor);
                    $accepted = ($payload['accepted'] ?? false) === true;
                    $tender['inspections'][] = [
                        'actorId' => $actor['id'],
                        'accepted' => $accepted,
                        'reason' => $reason,
                        'at' => gmdate('c', $this->now()),
                    ];
                    $tender['status'] = $accepted ? 'inspected' : 'in_progress';
                    break;
                case 'tender.pay':
                    $this->owner($tender, $actor);
                    $this->phase($tender, ['inspected']);
                    $tender['payment'] = [
                        'reference' => $this->text(
                            $payload,
                            'paymentReference',
                        ),
                        'amountCents' => $tender['actualCents'],
                        'at' => gmdate('c', $this->now()),
                    ];
                    $tender['status'] = 'paid';
                    break;
                case 'tender.cancel':
                    $this->owner($tender, $actor);
                    $this->phase($tender, [
                        'open',
                        'review',
                        'awarded',
                        'in_progress',
                        'paused',
                    ]);
                    $tender['actualCents'] = $this->integer(
                        $payload,
                        'actualCents',
                        0,
                        $tender['budgetCents'],
                    );
                    $tender['status'] = 'cancelled';
                    break;
                default:
                    throw new \DomainException('Action de consultation inconnue.', 400);
            }
            $tender['history'][] = [
                'action' => $action,
                'from' => $before,
                'to' => $tender['status'],
                'actorId' => $actor['id'],
                'reason' => $action === 'tender.bid'
                        ? 'Dépôt chiffré reçu. Contenu non publié avant ouverture.'
                        : $reason,
                'at' => gmdate('c', $this->now()),
            ];

            return $tender['id'];
        }
        throw new \DomainException('Dossier introuvable.', 404);
    }

    private function checkVolunteers(array $state, array $tender): void
    {
        $workers = array_column($state['workers'] ?? [], null, 'id');
        foreach ($tender['participation']['volunteers'] as $id => $hours) {
            $worker = $workers[$id] ?? null;
            if (
                !$worker
                || !$worker['optIn']
                || !$worker['available']
                || $worker['capacityHours'] < $hours
                || !in_array($tender['service'], $worker['skills'], true)
                || self::busy($state, $id, $tender['id'])
            ) {
                throw new \DomainException('Disponibilité ou compétence modifiée : revoir le plan avant de commencer.', 409);
            }
        }
    }
}
