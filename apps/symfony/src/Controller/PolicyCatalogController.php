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

    #[Route('/mesures', name: 'policy_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $scope = $request->query->getString('scope');
        $page = min(10000, max(1, $request->query->getInt('page', 1)));

        return $this->uncached('pages/policy-catalog.html.twig', [
            'catalog' => $this->catalog->page($page, $search, $scope),
            'search' => $search,
            'scope' => $scope,
            'hasProfile' => $this->profiles->find($request) !== null,
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
        $profile = $this->profiles->find($request);
        $territory = $measure['territoryCode'];
        $eligible = $profile !== null
            && ($territory === 'FR' || $profile['communeCode'] === $territory);
        $existing = $this->catalog->existingVote($id, $profile);

        return $this->uncached('pages/policy-detail.html.twig', [
            'measure' => $measure,
            'profile' => $profile,
            'eligible' => $eligible,
            'existingVote' => $existing,
            'token' => $this->profiles->formToken($request, 'policy.vote:' . $id),
            'saved' => $request->query->getBoolean('saved'),
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
        $profile = $this->profiles->find($request);
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

        return $this->redirectToRoute(
            'policy_detail', ['id' => $id, 'saved' => 1], Response::HTTP_SEE_OTHER,
        );
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
