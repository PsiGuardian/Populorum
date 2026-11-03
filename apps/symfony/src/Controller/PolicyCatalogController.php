<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BetaVisitorProfiles;
use App\Service\PolicyCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Bulletin distinct des consultations historiques, explicitement fictif et non électoral. */
final class PolicyCatalogController extends AbstractController
{
    public function __construct(
        private readonly PolicyCatalog $catalog,
        private readonly BetaVisitorProfiles $profiles,
    ) {
    }

    /** Ancienne URL conservée : elle ouvre la rubrique 2, sans redirection ambiguë. */
    #[Route('/mesures', name: 'policy_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->proposals($request);
    }

    /** Rubrique 2 : examiner les textes officiels avant d'exprimer une opinion. */
    public function proposals(Request $request): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $category = mb_substr(trim($request->query->getString('category')), 0, 80);
        $status = $request->query->getString('status');
        if (!in_array($status, ['all', 'open', 'closed'], true)) {
            $status = 'all';
        }
        $page = min(10000, max(1, $request->query->getInt('page', 1)));
        $profile = $this->currentProfile($request);

        return $this->uncached('pages/policy-catalog.html.twig', [
            'catalog' => $this->catalog->page($page, $search, '', $category, $status),
            'summary' => $this->catalog->summary($profile),
            'categories' => $this->catalog->categories(),
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'profile' => $profile,
            'policySection' => 'proposals',
        ]);
    }

    /** Rubrique 3 : consultations, progression du compte et bulletins. */
    public function consultations(Request $request): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $category = mb_substr(trim($request->query->getString('category')), 0, 80);
        $status = $request->query->getString('status', 'open');
        if (!in_array($status, ['open', 'closed', 'all'], true)) {
            $status = 'open';
        }
        $participation = $request->query->getString('participation');
        if (!in_array($participation, ['all', 'pending', 'voted'], true)) {
            $participation = 'all';
        }
        $page = min(10000, max(1, $request->query->getInt('page', 1)));
        $profile = $this->currentProfile($request);

        return $this->uncached('pages/policy-consultations.html.twig', [
            'catalog' => $this->catalog->page(
                $page, $search, '', $category, $status,
                $profile['citizenId'] ?? null, $participation,
            ),
            'summary' => $this->catalog->summary($profile),
            'categories' => $this->catalog->categories(),
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'participation' => $profile === null ? 'all' : $participation,
            'profile' => $profile,
            'policySection' => 'consultations',
        ]);
    }

    #[Route(
        '/mesures/{id}',
        name: 'policy_detail',
        requirements: ['id' => 'pm_[a-f0-9]{24}'],
        methods: ['GET'],
    )]
    public function detail(Request $request, string $id): Response
    {
        $measure = $this->catalog->measure($id);
        if ($measure === null) {
            throw $this->createNotFoundException('Mesure introuvable dans le catalogue.');
        }
        $profile = $this->currentProfile($request);
        $territory = $measure['territoryCode'];
        $eligible = $profile !== null
            && ($territory === 'FR' || $profile['communeCode'] === $territory);
        $existing = $this->catalog->existingVote($id, $profile);
        $fromConsultations = $request->query->getString('from') === 'consultations';

        return $this->uncached('pages/policy-detail.html.twig', [
            'measure' => $measure,
            'profile' => $profile,
            'eligible' => $eligible,
            'existingVote' => $existing,
            'token' => $this->profiles->formToken($request, 'policy.vote:' . $id),
            'saved' => $request->query->getBoolean('saved'),
            'backRoute' => $fromConsultations ? 'site_consultations' : 'site_proposals',
            'policySection' => $fromConsultations ? 'consultations' : 'proposals',
        ]);
    }

    #[Route(
        '/mesures/{id}/vote',
        name: 'policy_vote',
        requirements: ['id' => 'pm_[a-f0-9]{24}'],
        methods: ['POST'],
    )]
    public function vote(Request $request, string $id): Response
    {
        if (!$this->sameOrigin($request)
            || !$this->profiles->validFormToken(
                $request, 'policy.vote:' . $id, $request->request->getString('_token'),
            )) {
            return new Response('Formulaire expiré ou origine non autorisée.', 403);
        }
        $profile = $this->currentProfile($request);
        if ($profile === null) {
            return new Response('Créez un compte de test pour participer.', 403);
        }
        try {
            $this->catalog->vote(
                $id,
                $profile,
                $request->request->getString('choice'),
                $request->request->getString('argument'),
            );
        } catch (\DomainException $error) {
            $status = in_array($error->getCode(), [400, 403, 409, 503], true)
                ? $error->getCode() : 400;

            return new Response($error->getMessage(), $status);
        }

        $fromConsultations = $request->request->getString('_from') === 'consultations';

        return $this->redirectToRoute(
            'policy_detail',
            ['id' => $id, 'saved' => 1, 'from' => $fromConsultations ? 'consultations' : 'proposals'],
            Response::HTTP_SEE_OTHER,
        );
    }

    /** Les anciens laboratoires n'ont pas encore la table des profils de test. */
    private function currentProfile(Request $request): ?array
    {
        try {
            return $this->profiles->find($request);
        } catch (\DomainException|\Doctrine\DBAL\Exception\TableNotFoundException) {
            return null;
        }
    }

    private function sameOrigin(Request $request): bool
    {
        $from = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (!$from) {
            return false;
        }
        $port = parse_url($from, PHP_URL_PORT);

        return parse_url($from, PHP_URL_HOST) === $request->getHost()
            && parse_url($from, PHP_URL_SCHEME) === $request->getScheme()
            && (int) ($port ?: ($request->isSecure() ? 443 : 80)) === $request->getPort();
    }

    private function uncached(string $view, array $context): Response
    {
        $response = $this->render($view, $context);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
