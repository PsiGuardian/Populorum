<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CivicAccount;
use App\Form\SimulationActionType;
use App\Service\ActionForms;
use App\Service\CharterRenderer;
use App\Service\DemoDatabase;
use App\Service\SimulationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class DemoController extends AbstractController
{
    private const LABELS = [
        'home' => 'Vue d’ensemble',
        'territories' => 'Les territoires',
        'proposals' => 'Les chemins',
        'consultations' => 'Les consultations',
        'experiments' => 'Les expériences',
        'convergence' => 'La convergence',
        'memory' => 'Mémoire collective',
        'transparency' => 'Transparence',
        'infrastructure' => 'Infrastructure',
        'governance' => 'Gouvernance',
        'citizen' => 'Participer',
        'charter' => 'La charte',
    ];

    public function __construct(
        private DemoDatabase $database,
        private SimulationEngine $engine,
        private string $projectDir,
        private CharterRenderer $charterRenderer,
        private \App\Service\CausalLayout $layout,
        private \Symfony\Contracts\Translation\TranslatorInterface $translator,
        private \App\Service\TerritorialReport $territorialReport,
    ) {
    }

    /** Une session de navigateur possède son scénario ; ce n’est pas une identité civique. */
    private function owner(Request $request): string
    {
        $account = $this->getUser();
        if ($account instanceof CivicAccount) {
            return $account->workspaceId;
        }
        $session = $request->getSession();
        if (!$session->has('simulation_owner')) {
            $session->set('simulation_owner', bin2hex(random_bytes(24)));
        }

        return $session->get('simulation_owner');
    }

    /** Données publiques destinées à Twig : le coffre et l’anti-rejeu restent côté serveur. */
    private function context(Request $request, string $view = 'home'): array
    {
        $workspace = $this->database->workspace($this->owner($request));
        $state = $this->engine->publicState($workspace['state']);
        $account = $this->getUser();
        $actor =
            $account instanceof CivicAccount
                ? $account->citizenId
                : $request->getSession()->get('citizen_id', 'u1');
        $citizens = array_column($state['citizens'], null, 'id');
        if (!isset($citizens[$actor])) {
            $actor = 'u1';
        }

        $locale = $citizens[$actor]['preferences']['locale'] ?? 'fr_FR';
        $labels = [];
        foreach (self::LABELS as $key => $label) {
            $labels[$key] = $this->translator->trans(
                'nav.' . $key,
                locale: $locale,
            );
        }

        return [
            'state' => $state,
            'revision' => $workspace['revision'],
            'view' => $view,
            'labels' => $labels,
            'actor' => $citizens[$actor],
            'territoryMap' => array_column($state['territories'], null, 'id'),
            'citizenMap' => $citizens,
            'proposalMap' => array_column($state['proposals'], null, 'id'),
            'convergence' => $this->engine->convergence($state),
            'categories' => SimulationEngine::CATEGORIES,
            'actionLabels' => array_map(
                static fn (array $definition): string => $definition['title'],
                ActionForms::TYPES,
            ),
            'guardianPath' => $_SERVER['GUARDIAN_LOGIN_PATH'] ??
                '/demo-profiler-shield-populorum',
        ];
    }

    #[Route(
        '/demo/new-workspace',
        name: 'demo_new_workspace',
        methods: ['POST'],
    )]
    public function newWorkspace(Request $request): Response
    {
        if (
            !$this->isCsrfTokenValid(
                'new_workspace',
                $request->request->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }
        if ($this->getUser() instanceof CivicAccount) {
            throw $this->createAccessDeniedException('Le compte reste lié à son espace de participation.');
        }
        $request
            ->getSession()
            ->set('simulation_owner', bin2hex(random_bytes(24)));
        $request->getSession()->remove('citizen_id');

        return $this->redirectToRoute('home');
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    #[Route(
        '/{view}',
        name: 'section',
        requirements: [
            'view' => 'territories|proposals|consultations|experiments|convergence|memory|transparency|infrastructure|governance|citizen|charter',
        ],
        methods: ['GET'],
    )]
    public function page(Request $request, string $view = 'home'): Response
    {
        $context = $this->context($request, $view);
        $context['q'] = trim((string) $request->query->get('q', ''));
        $context['territory'] = (string) $request->query->get(
            'territory',
            'all',
        );
        $context['selected'] = (string) $request->query->get('id', '');
        $context['proposalList'] = $context['state']['proposals'];
        usort(
            $context['proposalList'],
            static fn (array $a, array $b): int => ($a['priorityRank'] ?? 3) <=>
            ($b['priorityRank'] ?? 3) ?:
            strcmp($a['title'], $b['title']),
        );
        $context['personal'] = $request->query->getBoolean('personal');
        if ($view === 'territories') {
            $context['territorialReports'] = $this->territorialReport->reports(
                $context['state'],
            );
        }
        if (
            $view === 'proposals'
            && isset($context['proposalMap'][$context['selected']])
        ) {
            $context['graph'] = $this->layout->layout(
                $context['proposalMap'][$context['selected']],
            );
        }
        if ($view === 'charter') {
            $locale = (string) $request->query->get('locale', 'fr_FR');
            $translationsDirectory =
                dirname($this->projectDir, 2) . '/docs/translations';
            $locales = array_map(
                'basename',
                glob($translationsDirectory . '/*', GLOB_ONLYDIR),
            );
            if (!in_array($locale, $locales, true)) {
                $locale = 'fr_FR';
            }
            $charterFile =
                $locale === 'fr_FR'
                    ? dirname($this->projectDir, 2) . '/CHARTER.md'
                    : $translationsDirectory . '/' . $locale . '/CHARTER.md';
            $context += [
                'locale' => $locale,
                'locales' => $locales,
                'charterHtml' => $this->charterRenderer->render(
                    file_get_contents($charterFile),
                ),
            ];
        }

        return $this->render('pages/' . $view . '.html.twig', $context);
    }

    #[Route(
        '/action/{type}',
        name: 'simulation_action',
        methods: ['GET', 'POST'],
    )]
    public function action(Request $request, string $type): Response
    {
        if (!isset(ActionForms::TYPES[$type])) {
            throw $this->createNotFoundException();
        }
        if (
            filter_var(
                $_SERVER['POPULORUM_REQUIRE_LOGIN'] ?? false,
                FILTER_VALIDATE_BOOL,
            )
            && !$this->getUser()
        ) {
            return $this->redirectToRoute('citizen_login');
        }
        $context = $this->context($request, ActionForms::TYPES[$type]['view']);
        $initialData = $this->initialActionData($request, $type, $context);
        $proposal =
            $context['proposalMap'][$initialData['proposalId'] ?? ''] ?? null;
        $form = $this->createForm(SimulationActionType::class, $initialData, [
            'action_type' => $type,
            'territories' => $context['state']['territories'],
            'causal_nodes' => $proposal['nodes'] ?? [],
        ]);
        $form->handleRequest($request);
        $error = null;
        $status = 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $payload = $form->getData();
                $revision = (int) $payload['revision'];
                $operationId = $payload['operationId'];
                unset($payload['revision'], $payload['operationId']);
                // L’auteur vient de la session : un champ de formulaire ne peut pas l’usurper.
                $payload['citizenId'] = $context['actor']['id'];
                if (
                    in_array(
                        $type,
                        ['proposal.create', 'proposal.revise'],
                        true,
                    )
                ) {
                    foreach (
                        $proposal['nodes'] ??
                            array_map(
                                static fn (string $category): array => [
                                    'category' => $category,
                                ],
                                SimulationEngine::CATEGORIES,
                            ) as $nodeIndex => $node
                    ) {
                        $payload['nodes'][] = [
                            'id' => $node['id'] ?? 'n' . $nodeIndex,
                            'category' => $node['category'],
                            'content' => $payload['node' . $nodeIndex] ?? '',
                            'kind' => $payload['kind' . $nodeIndex],
                        ];
                        unset(
                            $payload['node' . $nodeIndex],
                            $payload['kind' . $nodeIndex],
                        );
                    }
                }
                // Le contrôle optimiste empêche un ancien formulaire d’écraser une autre écriture.
                $workspace = $this->database->workspace($this->owner($request));
                if ($workspace['revision'] !== $revision) {
                    throw new \DomainException('La simulation a évolué. Rechargez le formulaire.', 409);
                }
                $next = $this->engine->apply(
                    $workspace['state'],
                    $type,
                    $payload,
                    $operationId,
                );
                $this->database->save($this->owner($request), $revision, $next);
                $this->addFlash(
                    'success',
                    'Opération enregistrée dans votre simulation fictive.',
                );

                return $this->redirectToRoute('section', [
                    'view' => ActionForms::TYPES[$type]['view'],
                ]);
            } catch (\DomainException $exception) {
                $error = $exception->getMessage();
                $status = $exception->getCode() ?: 400;
            }
        } elseif ($form->isSubmitted()) {
            $status = 400;
        }

        return $this->render(
            'action.html.twig',
            $context + [
                'form' => $form->createView(),
                'title' => ActionForms::TYPES[$type]['title'],
                'type' => $type,
                'error' => $error,
            ],
            new Response(status: $status),
        );
    }

    #[Route('/persona', name: 'persona', methods: ['POST'])]
    public function persona(Request $request): Response
    {
        if ($this->getUser()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Un compte connecté conserve son identité de personnage.');
        }
        if (
            !$this->isCsrfTokenValid(
                'persona',
                (string) $request->request->get('_token'),
            )
        ) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Jeton CSRF invalide.');
        }
        $context = $this->context($request, 'citizen');
        $id = (string) $request->request->get('citizen');
        if (!isset($context['citizenMap'][$id])) {
            throw $this->createNotFoundException();
        }
        $request->getSession()->set('citizen_id', $id);

        return $this->redirectToRoute('section', ['view' => 'citizen']);
    }

    #[Route(
        '/export/{kind}',
        name: 'export',
        requirements: [
            'kind' => 'memory|proofs|citizen|consultation|experiment',
        ],
        methods: ['GET'],
    )]
    public function export(Request $request, string $kind): Response
    {
        $context = $this->context($request);
        $state = $context['state'];
        $payload = $state;
        $id = (string) $request->query->get('id', '');
        if ($kind === 'citizen') {
            $payload = [
                'citizen' => $context['actor'],
                'proposals' => array_values(
                    array_filter(
                        $state['proposals'],
                        fn ($proposal) => $proposal['authorId'] ===
                            $context['actor']['id'],
                    ),
                ),
                'votes' => array_values(
                    array_filter(
                        $state['votes'],
                        fn ($value) => $value['citizenId'] ===
                            $context['actor']['id'],
                    ),
                ),
            ];
        }
        if ($kind === 'consultation') {
            $consultations = array_filter(
                $state['consultations'],
                fn ($consultation) => $consultation['id'] === $id,
            );
            $consultation = reset($consultations);
            if (!$consultation) {
                throw $this->createNotFoundException();
            }
            $payload = $consultation + [
                'votes' => array_values(
                    array_filter(
                        $state['votes'],
                        fn ($value) => $value['consultationId'] === $id,
                    ),
                ),
            ];
        }
        if ($kind === 'experiment') {
            $items = array_filter(
                $state['experiments'],
                fn ($experiment) => $experiment['id'] === $id,
            );
            $experiment = reset($items);
            if (!$experiment) {
                throw $this->createNotFoundException();
            }
            $payload = [
                'simulation' => true,
                'experiment' => $experiment,
                'proposalVersion' => $experiment['proposalVersion'] ??
                    $this->experimentConsultation($state, $experiment)[
                        'version'
                    ],
                'proposal' => $experiment['frozenProposal'] ??
                    $this->experimentConsultation($state, $experiment)[
                        'frozen'
                    ],
            ];
        }

        return new JsonResponse($payload, 200, [
            'Cache-Control' => 'no-store',
            'Content-Disposition' => 'attachment; filename="populorum-' . $kind . '.json"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Les anciennes expériences se rattachent aussi à la version réellement consultée. */
    private function experimentConsultation(
        array $state,
        array $experiment,
    ): array {
        foreach ($state['consultations'] as $consultation) {
            if ($consultation['id'] === $experiment['consultationId']) {
                return $consultation;
            }
        }
        throw new \DomainException('Consultation de référence introuvable.', 409);
    }

    #[Route('/verify', name: 'verify', methods: ['GET', 'POST'])]
    public function verify(Request $request): Response
    {
        $context = $this->context($request, 'transparency');
        $result = $this->engine->verify($context['state']);
        $form = $this->createFormBuilder()
            ->add('proof', FileType::class, [
                'label' => 'Export JSON à vérifier',
                'constraints' => [new Assert\File(maxSize: '5M')],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Vérifier cet export',
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $value = json_decode(
                    file_get_contents(
                        $form->get('proof')->getData()->getPathname(),
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $result = $this->engine->verify($value);
            } catch (\Throwable) {
                $result = [
                    'valid' => false,
                    'reason' => 'Fichier invalide ou preuve incomplète.',
                ];
            }
        }

        return $this->render(
            'verify.html.twig',
            $context + ['result' => $result, 'form' => $form->createView()],
        );
    }

    #[Route('/api/v1/state', name: 'api_state', methods: ['GET'])]
    public function api(Request $request): Response
    {
        $context = $this->context($request);

        return $this->json(
            [
                'state' => $context['state'],
                'revision' => $context['revision'],
                'simulation' => true,
            ],
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    #[Route('/api/v1/replica', name: 'api_replica', methods: ['GET'])]
    public function replica(): Response
    {
        $file = $this->projectDir . '/var/replicas/published.json';
        if (!is_file($file)) {
            return $this->json(
                ['error' => 'Aucun laboratoire signé publié par l’opérateur.'],
                404,
            );
        }

        return new Response(file_get_contents($file), 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[Route('/api/v1/session', name: 'api_session', methods: ['GET'])]
    public function apiSession(
        Request $request,
        \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokens,
    ): Response {
        $context = $this->context($request);

        return $this->json(
            [
                'csrfToken' => (string) $tokens->getToken('api_mutation'),
                'revision' => $context['revision'],
                'actorId' => $context['actor']['id'],
                'authenticated' => $this->getUser() !== null,
            ],
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    #[Route('/api/v1/actions/{type}', name: 'api_action', methods: ['POST'])]
    public function apiAction(Request $request, string $type): Response
    {
        if (!isset(ActionForms::TYPES[$type])) {
            return $this->json(['error' => 'Action inconnue.'], 404);
        }
        if (
            filter_var(
                $_SERVER['POPULORUM_REQUIRE_LOGIN'] ?? false,
                FILTER_VALIDATE_BOOL,
            )
            && !$this->getUser()
        ) {
            return $this->json(
                ['error' => 'Connexion citoyenne requise.'],
                401,
            );
        }
        if (
            !$this->isCsrfTokenValid(
                'api_mutation',
                $request->headers->get('X-CSRF-Token', ''),
            )
        ) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], 403);
        }
        if (strlen($request->getContent()) > 262144) {
            return $this->json(['error' => 'Requête trop volumineuse.'], 413);
        }
        try {
            $body = json_decode(
                $request->getContent(),
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
            if (
                !is_array($body)
                || !is_array($body['payload'] ?? null)
                || !is_int($body['revision'] ?? null)
                || !is_string($body['operationId'] ?? null)
            ) {
                throw new \DomainException('Corps de requête invalide.');
            }
            $payload = $body['payload'];
            $allowed = array_keys(ActionForms::TYPES[$type]['fields']);
            if (in_array($type, ['proposal.create', 'proposal.revise'], true)) {
                $allowed = array_merge($allowed, ['nodes', 'edges', 'sources']);
            }
            if (array_diff(array_keys($payload), $allowed)) {
                throw new \DomainException('Champ non autorisé.');
            }
            $context = $this->context($request);
            $workspace = $this->database->workspace($this->owner($request));
            if ($workspace['revision'] !== $body['revision']) {
                throw new \DomainException('Révision périmée.', 409);
            }
            $payload['citizenId'] = $context['actor']['id'];
            $next = $this->engine->apply(
                $workspace['state'],
                $type,
                $payload,
                $body['operationId'],
            );
            $this->database->save(
                $this->owner($request),
                $workspace['revision'],
                $next,
            );

            return $this->json(
                [
                    'ok' => true,
                    'revision' => $workspace['revision'] + 1,
                    'operationId' => $body['operationId'],
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (\DomainException $exception) {
            return $this->json(
                ['error' => $exception->getMessage()],
                $exception->getCode() ?: 400,
            );
        } catch (\JsonException|\TypeError) {
            return $this->json(['error' => 'Format de données invalide.'], 400);
        }
    }

    #[Route('/api/v1/events', name: 'api_events', methods: ['GET'])]
    public function events(Request $request): Response
    {
        $owner = $this->owner($request);
        $last = (int) $request->headers->get('Last-Event-ID', '-1');
        $once = $request->query->getBoolean('once');
        $request->getSession()->save();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($owner, $last, $once): void {
                $deadline = microtime(true) + ($once ? 0 : 20);
                do {
                    $workspace = $this->database->workspace($owner);
                    if ($workspace['revision'] !== $last) {
                        $state = $this->engine->publicState(
                            $workspace['state'],
                        );
                        echo 'id: ' . $workspace['revision'] . "\n";
                        echo "event: state\n";
                        echo 'data: ' .
                            json_encode(
                                [
                                    'revision' => $workspace['revision'],
                                    'consultations' => $state['consultations'],
                                    'simulation' => true,
                                ],
                                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                            ) .
                            "\n\n";
                        $last = $workspace['revision'];
                    } else {
                        echo ": keep-alive\n\n";
                    }
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    if ($once || connection_aborted()) {
                        break;
                    }
                    usleep(1000000);
                } while (microtime(true) < $deadline);
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-store',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    #[Route(
        '/api/v1/{resource}',
        name: 'api_resource',
        requirements: [
            'resource' => 'territories|proposals|pathways|graphs|consultations|results|observations|evaluations|proofs|finance|nodes|rules|alerts',
        ],
        methods: ['GET'],
    )]
    public function resource(Request $request, string $resource): Response
    {
        $context = $this->context($request);
        $state = $context['state'];
        $items = match ($resource) {
            'territories' => $state['territories'],
            'pathways', 'proposals' => $state['proposals'],
            'graphs' => array_map(
                static fn (array $proposal): array => [
                    'id' => $proposal['id'],
                    'territoryId' => $proposal['territoryId'],
                    'nodes' => $proposal['nodes'],
                    'edges' => $proposal['edges'],
                ],
                $state['proposals'],
            ),
            'results' => array_map(
                static fn (array $consultation): array => [
                    'id' => $consultation['id'],
                    'territoryId' => $consultation['territoryId'],
                    'tally' => $consultation['tally'],
                    'version' => $consultation['version'],
                ],
                $state['consultations'],
            ),
            'observations' => array_merge(
                [],
                ...array_map(
                    static fn (array $experiment): array => array_map(
                        static fn (array $observation): array => $observation + [
                            'experimentId' => $experiment['id'],
                            'territoryId' => $experiment['territoryId'],
                        ],
                        $experiment['observations'],
                    ),
                    $state['experiments'],
                ),
            ),
            'evaluations' => array_values(
                array_filter(
                    $state['experiments'],
                    static fn (array $experiment): bool => isset(
                        $experiment['evaluation'],
                    ),
                ),
            ),
            'proofs' => $state['events'],
            'rules' => $state['ruleSets'] ?? [],
            default => $state[$resource],
        };
        $territory = (string) $request->query->get('territory', '');
        $q = mb_strtolower(trim((string) $request->query->get('q', '')));
        $items = array_values(
            array_filter(
                $items,
                static fn (array $item): bool => ($territory === ''
                    || ($item['territoryId'] ?? $item['id']) === $territory)
                    && ($q === ''
                        || str_contains(
                            mb_strtolower(
                                json_encode($item, JSON_UNESCAPED_UNICODE),
                            ),
                            $q,
                        )),
            ),
        );
        $sort = $request->query->get('sort', 'date');
        if (!in_array($sort, ['date', 'title', 'status', 'theme'], true)) {
            return $this->json(
                [
                    'error' => 'Tri inconnu.',
                    'allowed' => ['date', 'title', 'status', 'theme'],
                ],
                400,
            );
        }
        usort(
            $items,
            static fn (array $a, array $b): int => strcmp(
                (string) ($a[$sort] ??
                    ($a['createdAt'] ?? ($a['at'] ?? $a['id']))),
                (string) ($b[$sort] ??
                    ($b['createdAt'] ?? ($b['at'] ?? $b['id']))),
            ),
        );
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $page = max(1, $request->query->getInt('page', 1));

        return $this->json(
            [
                'data' => array_slice($items, ($page - 1) * $limit, $limit),
                'meta' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($items),
                    'pages' => (int) ceil(count($items) / $limit),
                    'revision' => $context['revision'],
                    'sort' => $sort,
                    'simulation' => true,
                ],
            ],
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    /** Prépare les valeurs affichées ; aucune donnée n’est enregistrée ici. */
    private function initialActionData(
        Request $request,
        string $type,
        array $context,
    ): array {
        $initialData = [
            'revision' => (string) $context['revision'],
            'operationId' => bin2hex(random_bytes(16)),
            'territoryId' => $context['actor']['territoryId'],
            'theme' => 'Mobilités',
            'locale' => 'fr_FR',
            'changeAllowed' => true,
            'tags' => 'trafic, accessibilité',
            'forecast' => 0,
            'days' => 14,
            'quorum' => 50,
            'uncertainty' => 5,
            'reason' => 'Clôture pédagogique volontaire',
            'start' => gmdate('Y-m-d'),
            'end' => gmdate('Y-m-d', time() + 90 * 86400),
            'date' => gmdate('Y-m-d'),
            'unexpected' => 'Aucun imprévu déclaré',
            'bandwidth' => 128,
            'baseline' => 120,
            'unit' => 'véhicules / heure',
        ];
        foreach (
            [
                'proposalId',
                'consultationId',
                'experimentId',
                'governanceId',
                'caseId',
                'appealId',
                'alertId',
                'nodeId',
                'choice',
            ] as $key
        ) {
            if ($request->query->has($key)) {
                $initialData[$key] = (string) $request->query->get($key);
            }
        }
        // Le schéma des rubriques dépend du chemin aussi pendant le POST.
        // Ce choix prépare le formulaire ; l’auteur et la révision restent contrôlés ensuite.
        if ($request->isMethod('POST')) {
            $submitted = $request->request->all('simulation_action');
            if (is_string($submitted['proposalId'] ?? null)) {
                $initialData['proposalId'] = $submitted['proposalId'];
            }
        }
        if (
            in_array(
                $type,
                [
                    'proposal.revise',
                    'proposal.publish',
                    'proposal.node',
                    'proposal.edge',
                    'proposal.source',
                ],
                true,
            )
            && isset($initialData['proposalId'])
        ) {
            $proposal =
                $context['proposalMap'][$initialData['proposalId']] ?? null;
            if (!$proposal) {
                throw $this->createNotFoundException();
            }
            foreach (
                ActionForms::TYPES['proposal.create']['fields'] as $field => $definition
            ) {
                if (array_key_exists($field, $proposal)) {
                    $initialData[$field] = $proposal[$field];
                }
            }
            $initialData['tags'] = implode(', ', $proposal['tags']);
            $initialData['sources'] = $proposal['sources'];
            foreach ($proposal['nodes'] as $nodeIndex => $node) {
                $initialData['node' . $nodeIndex] = $node['content'];
                $initialData['kind' . $nodeIndex] = $node['kind'];
            }
        }
        if ($type === 'proposal.create') {
            foreach (SimulationEngine::CATEGORIES as $nodeIndex => $category) {
                $initialData['node' . $nodeIndex] =
                    $nodeIndex === 9
                        ? 'Aucun imprévu observé à ce stade'
                        : ($nodeIndex === 11
                            ? 'Première version du chemin'
                            : '');
                $initialData['kind' . $nodeIndex] =
                    $nodeIndex === 4 ? 'observation' : 'hypothesis';
            }
        }
        if ($type === 'citizen.update') {
            $initialData['name'] = $context['actor']['name'];
        }
        if ($type === 'citizen.consent') {
            $initialData['consent'] = false;
        }

        return $initialData;
    }
}
