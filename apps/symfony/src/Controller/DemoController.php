<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CivicAccount;
use App\Form\SimulationActionType;
use App\Service\ActionForms;
use App\Service\CharterRenderer;
use App\Service\CharterRepository;
use App\Service\DemoDatabase;
use App\Service\PerformanceTrace;
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
        'accounts' => 'Récits et inquiétudes',
        'services' => 'Services et missions',
        'budget' => 'Budget et priorités',
        'procurement' => 'Besoins et devis',
    ];

    public function __construct(
        private DemoDatabase $database,
        private SimulationEngine $engine,
        private string $projectDir,
        private CharterRenderer $charterRenderer,
        private CharterRepository $charters,
        private \App\Service\CausalLayout $layout,
        private \Symfony\Contracts\Translation\TranslatorInterface $translator,
        private \App\Service\TerritorialReport $territorialReport,
        private \App\Service\PublicEvidence $publicEvidence,
        private \App\Twig\CivicUrlExtension $urls,
        private \App\Service\PublicBudget $publicBudget,
    ) {
    }

    /** Native arrays already have their public schema; no object normalization is necessary. */
    private function arrayJson(
        array $data,
        int $status = 200,
        array $headers = [],
    ): JsonResponse {
        return PerformanceTrace::measure(
            'json_encode',
            static fn () => new JsonResponse($data, $status, $headers),
        );
    }

    protected function render(
        string $view,
        array $parameters = [],
        ?Response $response = null,
    ): Response {
        return PerformanceTrace::measure(
            'twig',
            fn () => parent::render($view, $parameters, $response),
        );
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
    private function context(
        Request $request,
        string $view = 'home',
        bool $complete = true,
        bool $tallies = true,
        bool $includeVotes = true,
    ): array {
        $workspace = PerformanceTrace::measure(
            'workspace',
            fn () => $complete
                ? $this->database->workspace($this->owner($request))
                : $this->database->pageWorkspace($this->owner($request), $includeVotes),
        );
        $state = PerformanceTrace::measure(
            'public_state',
            fn () => $this->engine->publicState($workspace['state'], $tallies),
        );
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
            if ($labels[$key] === 'nav.' . $key) {
                $labels[$key] = $label;
            }
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
            'convergence' => [],
            'categories' => SimulationEngine::CATEGORIES,
            'actionLabels' => array_map(
                static fn (array $definition): string => $definition['title'],
                ActionForms::TYPES,
            ),
            'guardianPath' => $_SERVER['GUARDIAN_LOGIN_PATH'] ??
                '/demo-profiler-shield-populorum',
        ];
    }

    #[Route('/characters/{id}', name: 'fictional_character', methods: ['GET'])]
    public function character(
        Request $request,
        string $id,
        \App\Service\MentalSimulation $simulation,
    ): Response {
        $context = $this->context($request, 'citizen');
        $character = $context['citizenMap'][$id] ?? null;
        if (!$character || !isset($character['mind'])) {
            throw $this->createNotFoundException('Profil fictif introuvable dans ce laboratoire.');
        }
        $votes = array_values(
            array_filter(
                $context['state']['votes'],
                static fn (array $vote): bool => $vote['citizenId'] === $id,
            ),
        );
        $selected = $request->query->getString(
            'consultation',
            $votes[0]['consultationId'] ?? '',
        );
        $vote = null;
        foreach ($votes as $item) {
            if ($item['consultationId'] === $selected) {
                $vote = $item;
                break;
            }
        }
        $round = $request->query->has('round')
            ? filter_var($request->query->get('round'), FILTER_VALIDATE_INT)
            : null;
        if (
            $round === false
            || ($round !== null
                && ($round < 0
                    || $round > \App\Service\MentalSimulation::MAX_ROUND))
        ) {
            throw $this->createNotFoundException('Tour introuvable.');
        }
        $dossier = null;
        if ($vote && (isset($vote['deliberation']) || $round !== null)) {
            try {
                $dossier = $simulation->dossier(
                    $context['state'],
                    $vote,
                    $round,
                );
            } catch (\DomainException $error) {
                throw $this->createNotFoundException($error->getMessage());
            }
        }
        if ($request->query->get('format') === 'json') {
            if (!$dossier) {
                throw $this->createNotFoundException('Ce choix manuel n’a pas de raisonnement automatique.');
            }

            return $this->json(
                $dossier,
                headers: ['Cache-Control' => 'no-store'],
            );
        }
        $rounds = [];
        if ($vote) {
            foreach ($vote['history'] as $entry) {
                if (isset($entry['deliberation'])) {
                    $rounds[] = $entry['deliberation']['round'];
                }
            }
            if (isset($vote['deliberation'])) {
                $rounds[] = $vote['deliberation']['round'];
            }
        }

        return $this->render(
            'pages/character.html.twig',
            $context + [
                'character' => $character,
                'voice' => $simulation->voice($character),
                'priorities' => \App\Service\FictionalMind::PRIORITIES,
                'characterVotes' => $votes,
                'consultationMap' => array_column(
                    $context['state']['consultations'],
                    null,
                    'id',
                ),
                'dossier' => $dossier,
                'selectedVote' => $vote,
                'rounds' => array_values(array_unique($rounds)),
            ],
        );
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
            'view' => 'territories|proposals|consultations|experiments|convergence|memory|transparency|infrastructure|governance|citizen|charter|accounts|services|budget|procurement',
        ],
        methods: ['GET'],
    )]
    public function page(Request $request, string $view = 'home'): Response
    {
        $context = $this->context(
            $request,
            $view,
            false,
            false,
            $view === 'consultations',
        );
        $pageStart = hrtime(true);
        $search = new \App\Service\SearchInput();
        $routeTerm = $request->attributes->get('term');
        $context['q'] = $search->term(
            $routeTerm !== null ? $routeTerm : $request->query->get('q', ''),
        );
        $context['territory'] = $search->territory(
            $request->attributes->get(
                'territory',
                $request->query->get('territory', 'all'),
            ),
            $context['state']['territories'],
        );
        $context['selected'] = (string) $request->attributes->get(
            'id',
            $request->query->get('id', ''),
        );
        if (
            $request->query->has('q')
            && $routeTerm === null
            && in_array(
                $view,
                [
                    'proposals',
                    'territories',
                    'consultations',
                    'experiments',
                    'memory',
                    'accounts',
                ],
                true,
            )
        ) {
            return $this->redirect(
                $this->urls->path('section', [
                    'view' => $view,
                    'q' => $context['q'],
                    'territory' => $context['territory'],
                ]),
            );
        }
        if ($view === 'consultations') {
            $items = array_values(array_filter(
                $context['state']['consultations'],
                static fn (array $item): bool => ($context['selected'] === '' || $item['id'] === $context['selected'])
                    && ($context['territory'] === 'all' || $item['territoryId'] === $context['territory'])
                    && ($context['q'] === '' || str_contains(mb_strtolower($item['frozen']['title']), mb_strtolower($context['q']))),
            ));
            $window = \App\Service\PageWindow::slice($items, $request->attributes->getInt('page', $request->query->getInt('page', 1)), 27);
            $context['pagination'] = $window;
            $visible = $context['state'];
            $visible['consultations'] = $window['items'];
            $context['consultationList'] = PerformanceTrace::measure('visible_tallies', fn () => $this->engine->publicState($visible)['consultations']);
            $context['votesByConsultation'] = [];
            if ($context['selected'] !== '') {
                foreach ($context['state']['votes'] as $vote) {
                    if ($vote['consultationId'] === $context['selected']) {
                        $context['votesByConsultation'][$vote['consultationId']][] = $vote;
                    }
                }
            }
        }
        if ($view === 'procurement') {
            $context['tenders'] = array_values(
                array_filter(
                    $context['state']['tenders'] ?? [],
                    static fn (array $tender): bool => ($context['territory'] ===
                        'all'
                        || $tender['territoryId'] === $context['territory'])
                        && ($context['selected'] === ''
                            || $tender['id'] === $context['selected']),
                ),
            );
            $context['rankings'] = [];
            foreach ($context['tenders'] as $tender) {
                $context['rankings'][
                    $tender['id']
                ] = new \App\Service\PublicProcurement()->ranking($tender);
            }
            $context['serviceCatalog'] =
                \App\Service\EssentialServices::CATALOG;
        }
        if ($view === 'budget') {
            $context['procurementSummary'] = [
                'count' => 0,
                'actualCents' => 0,
                'referenceCents' => 0,
            ];
            foreach ($context['state']['tenders'] ?? [] as $tender) {
                if ($tender['status'] === 'paid') {
                    ++$context['procurementSummary']['count'];
                    $context['procurementSummary']['actualCents'] +=
                        $tender['actualCents'];
                    $context['procurementSummary']['referenceCents'] +=
                        $tender['referenceCents'];
                }
            }
        }
        if ($view === 'services') {
            $context['serviceCatalog'] =
                \App\Service\EssentialServices::CATALOG;
            $context['selectedSkill'] = $request->query->getString('skill');
            if (
                $context['selectedSkill'] !== ''
                && !isset(
                    \App\Service\EssentialServices::CATALOG[
                        $context['selectedSkill']
                    ],
                )
            ) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Compétence inconnue.');
            }
            $context['workers'] = array_values(
                array_filter(
                    $context['state']['workers'] ?? [],
                    static fn (array $worker): bool => ($context['territory'] ===
                        'all'
                        || $worker['territoryId'] === $context['territory'])
                        && ($context['selectedSkill'] === ''
                            || in_array(
                                $context['selectedSkill'],
                                $worker['skills'],
                                true,
                            )),
                ),
            );
            $context['pagination'] = \App\Service\PageWindow::slice($context['workers'], $request->attributes->getInt('page', $request->query->getInt('page', 1)), 30);
            $context['workers'] = $context['pagination']['items'];
            $context['missions'] = $context['state']['missions'] ?? [];
            usort(
                $context['missions'],
                static fn (array $a, array $b): int => [
                    $a['priority'],
                    $a['id'],
                ] <=> [$b['priority'], $b['id']],
            );
            $context['candidates'] = [];
            foreach ($context['missions'] as $mission) {
                $context['candidates'][
                    $mission['id']
                ] = new \App\Service\CityOperations()->candidates(
                    $context['state'],
                    $mission,
                );
            }
        }
        if ($view === 'budget') {
            $context['budgetCities'] = $this->publicBudget->cities(
                $context['state'],
            );
            $context['ledgerCode'] = $request->query->getString(
                'city',
                '75056',
            );
            $context['ledgerPrefix'] = $request->query->getString(
                'account',
                '',
            );
            try {
                $context['ledger'] = $this->publicBudget->ledger(
                    $context['ledgerCode'],
                    $context['ledgerPrefix'],
                    $request->query->getInt('page', 1),
                );
            } catch (\DomainException $exception) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException($exception->getMessage());
            }
            $context['budgetSource'] = $this->publicBudget->dataset();
            $context['savingsPercent'] = $request->query->getInt('saving', 0);
            $context['otherResourcesCents'] = $request->query->getInt(
                'resources',
                0,
            );
            $context['budgetScenarios'] = [];
            try {
                foreach ($context['budgetCities'] as $city) {
                    $context['budgetScenarios'][
                        $city['city']['id']
                    ] = $this->publicBudget->scenario(
                        $city,
                        $context['savingsPercent'],
                        $context['otherResourcesCents'],
                    );
                }
            } catch (\DomainException $exception) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException($exception->getMessage());
            }
        }
        if ($view === 'accounts') {
            $indicators = $this->publicEvidence->compare()['indicators'];
            $context['crimeReports'] = array_map(
                $this->publicEvidence->compare(...),
                $indicators,
            );
        }
        $context['proposalList'] = $context['state']['proposals'];
        usort(
            $context['proposalList'],
            static fn (array $a, array $b): int => ($a['priorityRank'] ?? 3) <=>
            ($b['priorityRank'] ?? 3) ?:
            strcmp($a['title'], $b['title']),
        );
        if ($view === 'proposals' && $context['selected'] === '') {
            $items = array_values(array_filter($context['proposalList'], static fn (array $item): bool => ($context['territory'] === 'all' || $item['territoryId'] === $context['territory'])
                && ($context['q'] === '' || str_contains(mb_strtolower($item['title'] . $item['summary'] . $item['theme']), mb_strtolower($context['q'])))
            ));
            $context['pagination'] = \App\Service\PageWindow::slice($items, $request->attributes->getInt('page', $request->query->getInt('page', 1)), 27);
            $context['proposalList'] = $context['pagination']['items'];
        }
        $context['personal'] = $request->query->getBoolean('personal');
        if ($view === 'home' && $context['proposalList']) {
            $context['featuredProposal'] =
                $context['proposalMap'][$context['selected']] ??
                $context['proposalList'][0];
            $context['graph'] = $this->layout->layout(
                $context['featuredProposal'],
            );
        }
        if ($view === 'convergence') {
            $context['publicEvidence'] = $this->publicEvidence->compare(
                $request->query->getString('indicator'),
            );
            $comparison = PerformanceTrace::measure(
                'convergence',
                fn () => $this->engine->convergencePage(
                    $context['state'],
                    $context['selected'],
                    $context['territory'],
                    $request->query->getInt('page', 1),
                ),
            );
            $context['comparisonCount'] = $comparison['total'];
            $context['comparisonPage'] = $comparison['page'];
            $context['convergence'] = $comparison['items'];
        }
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
            $locale = $this->charters->resolveLocale(
                (string) $request->query->get('locale', 'fr_FR'),
            );
            $context += [
                'locale' => $locale,
                'locales' => $this->charters->locales(),
                'charterHtml' => $this->charterRenderer->render(
                    $this->charters->markdown($locale),
                ),
            ];
        }

        PerformanceTrace::record(
            'page_data',
            (hrtime(true) - $pageStart) / 1e6,
        );

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
        $context = $this->context(
            $request,
            ActionForms::TYPES[$type]['view'],
            false,
            false,
        );
        $initialData = $this->initialActionData($request, $type, $context);
        $proposal =
            $context['proposalMap'][$initialData['proposalId'] ?? ''] ?? null;
        $workerOptions = $context['state']['workers'] ?? [];
        if ($type === 'mission.offer') {
            $mission =
                array_column($context['state']['missions'] ?? [], null, 'id')[
                    $initialData['missionId'] ?? ''
                ] ?? null;
            $workerOptions = $mission
                ? new \App\Service\CityOperations()->candidates(
                    $context['state'],
                    $mission,
                )
                : [];
        }
        $form = $this->createForm(SimulationActionType::class, $initialData, [
            'action_type' => $type,
            'procurement_choices' => $this->procurementChoices(
                $context['state'],
                $initialData['tenderId'] ?? '',
            ),
            'territories' => $context['state']['territories'],
            'causal_nodes' => $proposal['nodes'] ?? [],
            'proposals' => $context['state']['proposals'],
            'workers' => array_map(
                static fn (array $worker): array => $worker + [
                    'label' => $context['citizenMap'][$worker['id']]['name'] .
                        ' · ' .
                        $worker['profession'],
                ],
                $workerOptions,
            ),
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

                if (str_starts_with($type, 'tender.')) {
                    return $this->redirect(
                        $this->urls->path('section', [
                            'view' => 'procurement',
                            'id' => $type === 'tender.create'
                                    ? $next['tenders'][
                                        array_key_last($next['tenders'])
                                    ]['id']
                                    : $payload['tenderId'],
                        ]),
                    );
                }

                return $this->redirect(
                    $this->urls->path('section', [
                        'view' => ActionForms::TYPES[$type]['view'],
                    ]),
                );
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

    #[Route('/recherche', name: 'site_search_submit', methods: ['POST'])]
    public function search(
        Request $request,
        \App\Twig\CivicUrlExtension $urls,
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'search',
                $request->request->getString('_token'),
            )
        ) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Jeton de recherche invalide.');
        }
        $view = $request->request->getString('view', 'proposals');
        if (
            !in_array(
                $view,
                [
                    'proposals',
                    'territories',
                    'consultations',
                    'experiments',
                    'memory',
                    'accounts',
                ],
                true,
            )
        ) {
            throw $this->createNotFoundException('Recherche indisponible dans cette rubrique.');
        }
        $context = $this->context($request, $view);
        $input = new \App\Service\SearchInput();

        return $this->redirect(
            $urls->path('section', [
                'view' => $view,
                'q' => $input->term($request->request->get('q', '')),
                'territory' => $input->territory(
                    $request->request->get('territory', 'all'),
                    $context['state']['territories'],
                ),
            ]),
            303,
        );
    }

    #[Route(
        '/finances-publiques/export/{kind}',
        name: 'public_budget_export',
        requirements: ['kind' => 'aggregates|balances'],
        methods: ['GET'],
    )]
    public function publicBudgetExport(string $kind): Response
    {
        $filename =
            $kind === 'aggregates'
                ? 'municipal-finance-2025.json'
                : 'municipal-balances-2025.json.gz';
        $path = $this->projectDir . '/data/research/' . $filename;
        if (!is_file($path)) {
            throw $this->createNotFoundException('Source indisponible.');
        }

        $response = $this->file($path, $filename);
        $response->headers->set(
            'Content-Type',
            $kind === 'aggregates' ? 'application/json' : 'application/gzip',
        );

        return $response;
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

        return $this->arrayJson(
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
        $context = $this->context($request, 'home', false, false);

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

    /** Short requests leave the PHP worker available for navigation, including php -S. */
    #[Route('/api/v1/live-results', name: 'api_live_results', methods: ['GET'])]
    public function liveResults(Request $request): Response
    {
        $workspace = PerformanceTrace::measure(
            'workspace',
            fn () => $this->database->pageWorkspace($this->owner($request)),
        );
        if ($request->query->has('ids')) {
            $ids = $request->query->get('ids');
            if (!is_string($ids) || !preg_match('/^[a-zA-Z0-9_-]{1,80}(?:,[a-zA-Z0-9_-]{1,80}){0,26}$/D', $ids)) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Sélection de consultations invalide.');
            }
            $wanted = array_fill_keys(explode(',', $ids), true);
            $workspace['state']['consultations'] = array_values(array_filter($workspace['state']['consultations'], static fn (array $item): bool => isset($wanted[$item['id']])));
        }
        $state = PerformanceTrace::measure(
            'public_state',
            fn () => $this->engine->publicState($workspace['state']),
        );
        $consultations = [];
        foreach ($state['consultations'] as $consultation) {
            $tally = array_intersect_key(
                $consultation['tally'],
                array_flip([
                    'pour',
                    'contre',
                    'abstention',
                    'total',
                    'eligible',
                    'participation',
                    'unit',
                    'phase',
                    'individual',
                ]),
            );
            $consultations[] = ['id' => $consultation['id'], 'tally' => $tally];
        }

        return $this->arrayJson(
            [
                'revision' => $workspace['revision'],
                'consultations' => $consultations,
                'simulation' => true,
            ],
            200,
            ['Cache-Control' => 'no-store'],
        );
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
        $context = $this->context(
            $request,
            'home',
            false,
            in_array($resource, ['consultations', 'results'], true),
        );
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
        $territory = new \App\Service\SearchInput()->territory(
            $request->query->get('territory', ''),
            $state['territories'],
        );
        $q = mb_strtolower(
            new \App\Service\SearchInput()->term($request->query->get('q', '')),
        );
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
            return $this->arrayJson(
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

        return $this->arrayJson(
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
    /** Choices only improve usability; every submitted identifier is checked by the domain service. */
    private function procurementChoices(array $state, string $id): array
    {
        $tender =
            array_column($state['tenders'] ?? [], null, 'id')[$id] ?? null;
        $choices = [
            'offerId' => [],
            'selection' => [],
            'questionId' => [],
            'alertId' => [],
            'amendmentId' => [],
        ];
        if (!$tender) {
            return $choices;
        }
        foreach ($tender['offers'] as $offer) {
            $label =
                $offer['company'] .
                ' · ' .
                number_format($offer['totalCents'] / 100, 2, ',', ' ') .
                ' € · ' .
                $offer['id'];
            $choices['offerId'][$label] = $offer['id'];
            if ($tender['evaluations'][$offer['id']]['compliant'] ?? false) {
                $choices['selection'][$label] = $offer['id'];
            }
        }
        if ($tender['participation']) {
            $choices['selection']['Participation habitante volontaire'] =
                'participation';
        }
        foreach (
            [
                'questions' => 'questionId',
                'alerts' => 'alertId',
                'amendments' => 'amendmentId',
            ] as $collection => $field
        ) {
            foreach ($tender[$collection] as $item) {
                $choices[$field][
                    $item['id'] .
                        ' · ' .
                        mb_substr($item['text'] ?? $item['reason'], 0, 100)
                ] = $item['id'];
            }
        }

        return $choices;
    }

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
        $contextKey = $request->attributes->get('contextKey');
        if (is_string($contextKey)) {
            $initialData[$contextKey] = $request->attributes->getString(
                'contextId',
            );
        }
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
                'accountId',
                'missionId',
                'workerId',
                'tenderId',
                'category',
                'choice',
            ] as $key
        ) {
            if ($request->query->has($key)) {
                $initialData[$key] = (string) $request->query->get($key);
            }
        }
        if (str_starts_with($type, 'tender.')) {
            unset($initialData['reason'], $initialData['uncertainty']);
            $initialData = array_replace($initialData, [
                'deadline' => new \DateTimeImmutable(
                    '+14 days',
                    new \DateTimeZone('Europe/Paris'),
                )->format('Y-m-d H:i'),
                'service' => 'maintenance',
                'budgetCents' => 3000000,
                'referenceCents' => 300000,
                'horizonYears' => 10,
                'days' => 30,
                'warrantyMonths' => 12,
                'annualMaintenanceCents' => 0,
                'qualityScore' => 50,
                'actualCents' => 0,
                'hours' => 2,
                'hoursNeeded' => 8,
                'materialsCents' => 50000,
                'insuranceCents' => 10000,
                'supervisionCents' => 20000,
                'controlCents' => 10000,
                'hourValueCents' => 2000,
            ]);
        }
        if (str_starts_with($type, 'worker.')) {
            $worker =
                array_column($context['state']['workers'] ?? [], null, 'id')[
                    $context['actor']['id']
                ] ?? null;
            if ($worker) {
                $initialData = array_merge($initialData, [
                    'profession' => $worker['profession'],
                    'skill' => $worker['skills'],
                    'zone' => $worker['zone'],
                    'hourlyCents' => $worker['hourlyCents'],
                    'capacityHours' => $worker['capacityHours'],
                    'available' => $worker['available'],
                    'optIn' => $worker['optIn'],
                ]);
            }
        }
        if (str_starts_with($type, 'mission.')) {
            unset($initialData['reason']);
            $initialData += [
                'hours' => 2,
                'materialsCents' => 2500,
                'budgetCents' => 50000,
                'priority' => '2',
                'zone' => 'center',
                'service' => 'maintenance',
                'actualCents' => 0,
            ];
        }
        if (str_starts_with($type, 'account.')) {
            unset($initialData['uncertainty'], $initialData['reason']);
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
