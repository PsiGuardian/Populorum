<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BetaVisitorProfiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class BetaVisitorController extends AbstractController
{
    public function __construct(
        private readonly BetaVisitorProfiles $profiles,
        private readonly RateLimiterFactory $betaProfilesLimiter,
        private readonly string $appSecret,
    ) {
    }

    #[Route('/compte-test', name: 'beta_profile', methods: ['GET'])]
    public function view(Request $request): Response
    {
        $profile = $this->profiles->find($request);

        return $this->page($request, $profile);
    }

    #[Route('/compte-test', name: 'beta_profile_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->protectPost($request, 'create');
        try {
            $this->profiles->create(
                $request,
                $request->request->getString('first_name'),
                $request->request->getString('last_name'),
                $request->request->getString('age'),
                $request->request->getString('commune_code'),
            );
        } catch (\DomainException $error) {
            return $this->page($request, null, $error->getMessage(), 422);
        }

        return $this->redirectToRoute('beta_profile', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/compte-test/effacer', name: 'beta_profile_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        $this->protectPost($request, 'delete');
        $this->profiles->delete($request);

        return $this->redirectToRoute('beta_profile', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/confidentialite', name: 'site_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        $response = $this->render('security/beta_privacy.html.twig');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function page(Request $request, ?array $profile, ?string $error = null, int $status = 200): Response
    {
        $response = $this->render('security/beta_profile.html.twig', [
            'betaProfile' => $profile,
            'formToken' => $this->profiles->formToken($request, 'create'),
            'deleteToken' => $this->profiles->formToken($request, 'delete'),
            'error' => $error,
        ]);
        $response->setStatusCode($status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function protectPost(Request $request, string $action): void
    {
        // Stateless anti-CSRF: explicit same-origin policy plus a short-lived IP-bound HMAC.
        $origin = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');
        $source = $origin ?: $referer;
        if ($source === null
            || parse_url($source, PHP_URL_HOST) !== $request->getHost()
            || (int) (parse_url($source, PHP_URL_PORT) ?: ($request->isSecure() ? 443 : 80)) !== $request->getPort()
            || parse_url($source, PHP_URL_SCHEME) !== $request->getScheme()) {
            throw $this->createAccessDeniedException('Origine du formulaire non autorisée.');
        }
        if (!$this->profiles->validFormToken($request, $action, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré ou invalide.');
        }
        $ip = $request->getClientIp();
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw $this->createAccessDeniedException('Connexion non identifiable.');
        }
        $key = hash_hmac('sha256', 'beta-rate:' . $ip, $this->appSecret);
        if (!$this->betaProfilesLimiter->create($key)->consume()->isAccepted()) {
            throw $this->createAccessDeniedException('Trop de demandes. Réessayez plus tard.');
        }
    }
}
