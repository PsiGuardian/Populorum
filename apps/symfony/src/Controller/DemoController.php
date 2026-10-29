<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SimulationActionType;
use App\Service\ActionForms;
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
    ) {
    }

    /** Une session de navigateur possède son scénario ; ce n’est pas une identité civique. */
    private function owner(Request $request): string
    {
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
        $actor = $request->getSession()->get('citizen_id', 'u1');
        $citizens = array_column($state['citizens'], null, 'id');
        if (!isset($citizens[$actor])) {
            $actor = 'u1';
        }

        return [
            'state' => $state,
            'revision' => $workspace['revision'],
            'view' => $view,
            'labels' => self::LABELS,
            'actor' => $citizens[$actor],
            'territoryMap' => array_column($state['territories'], null, 'id'),
            'citizenMap' => $citizens,
            'proposalMap' => array_column($state['proposals'], null, 'id'),
            'convergence' => $this->engine->convergence($state),
            'categories' => SimulationEngine::CATEGORIES,
            'guardianPath' => $_SERVER['GUARDIAN_LOGIN_PATH'] ?? '/demo-profiler-shield-populorum',
        ];
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
        $context['territory'] = (string) $request->query->get('territory', 'all');
        $context['selected'] = (string) $request->query->get('id', '');
        if ($view === 'charter') {
            $locale = (string) $request->query->get('locale', 'fr_FR');
            $translationsDirectory = dirname($this->projectDir, 2) . '/docs/translations';
            $locales = array_map('basename', glob($translationsDirectory . '/*', GLOB_ONLYDIR));
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
                'charter' => file_get_contents($charterFile),
            ];
        }

        return $this->render('pages/' . $view . '.html.twig', $context);
    }

    #[Route('/action/{type}', name: 'simulation_action', methods: ['GET', 'POST'])]
    public function action(Request $request, string $type): Response
    {
        if (!isset(ActionForms::TYPES[$type])) {
            throw $this->createNotFoundException();
        }
        $context = $this->context($request, ActionForms::TYPES[$type]['view']);
        $initialData = $this->initialActionData($request, $type, $context);
        $form = $this->createForm(SimulationActionType::class, $initialData, [
            'action_type' => $type,
            'territories' => $context['state']['territories'],
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
                if (in_array($type, ['proposal.create', 'proposal.revise'], true)) {
                    foreach (SimulationEngine::CATEGORIES as $nodeIndex => $category) {
                        $payload['nodes'][] = [
                            'content' => $payload['node' . $nodeIndex],
                            'kind' => $payload['kind' . $nodeIndex],
                        ];
                        unset($payload['node' . $nodeIndex], $payload['kind' . $nodeIndex]);
                    }
                }
                // Le contrôle optimiste empêche un ancien formulaire d’écraser une autre écriture.
                $workspace = $this->database->workspace($this->owner($request));
                if ($workspace['revision'] !== $revision) {
                    throw new \DomainException('La simulation a évolué. Rechargez le formulaire.', 409);
                }
                $next = $this->engine->apply($workspace['state'], $type, $payload, $operationId);
                $this->database->save($this->owner($request), $revision, $next);
                $this->addFlash('success', 'Opération enregistrée dans votre simulation fictive.');

                return $this->redirectToRoute('section', ['view' => ActionForms::TYPES[$type]['view']]);
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
        if (!$this->isCsrfTokenValid('persona', (string) $request->request->get('_token'))) {
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
        requirements: ['kind' => 'memory|proofs|citizen|consultation|experiment'],
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
                        fn ($proposal) => $proposal['authorId'] === $context['actor']['id'],
                    ),
                ),
                'votes' => array_values(
                    array_filter(
                        $state['votes'],
                        fn ($value) => $value['citizenId'] === $context['actor']['id'],
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
                    array_filter($state['votes'], fn ($value) => $value['consultationId'] === $id),
                ),
            ];
        }
        if ($kind === 'experiment') {
            $items = array_filter($state['experiments'], fn ($experiment) => $experiment['id'] === $id);
            $experiment = reset($items);
            if (!$experiment) {
                throw $this->createNotFoundException();
            }
            $payload = [
                'simulation' => true,
                'experiment' => $experiment,
                'proposal' => $context['proposalMap'][$experiment['proposalId']],
            ];
        }

        return new JsonResponse($payload, 200, [
            'Cache-Control' => 'no-store',
            'Content-Disposition' => 'attachment; filename="populorum-' . $kind . '.json"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
            ->add('submit', SubmitType::class, ['label' => 'Vérifier cet export'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $value = json_decode(
                    file_get_contents($form->get('proof')->getData()->getPathname()),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $result = $this->engine->verify($value);
            } catch (\Throwable) {
                $result = ['valid' => false, 'reason' => 'Fichier invalide ou preuve incomplète.'];
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
            ['state' => $context['state'], 'revision' => $context['revision'], 'simulation' => true],
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    /** Prépare les valeurs affichées ; aucune donnée n’est enregistrée ici. */
    private function initialActionData(Request $request, string $type, array $context): array
    {
        $initialData = [
            'revision' => (string) $context['revision'],
            'operationId' => bin2hex(random_bytes(16)),
            'territoryId' => $context['actor']['territoryId'],
            'theme' => 'Mobilités',
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
            ['proposalId', 'consultationId', 'experimentId', 'governanceId', 'caseId', 'nodeId', 'choice'] as $key
        ) {
            if ($request->query->has($key)) {
                $initialData[$key] = (string) $request->query->get($key);
            }
        }
        if (
            in_array($type, ['proposal.revise', 'proposal.publish'], true)
            && isset($initialData['proposalId'])
        ) {
            $proposal = $context['proposalMap'][$initialData['proposalId']] ?? null;
            if (!$proposal) {
                throw $this->createNotFoundException();
            }
            $initialData['solution'] = $proposal['solution'];
            $initialData['risk'] = $proposal['risk'];
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
                $initialData['kind' . $nodeIndex] = $nodeIndex === 4 ? 'observation' : 'hypothesis';
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
