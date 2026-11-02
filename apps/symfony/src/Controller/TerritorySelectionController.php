<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GeographyCatalog;
use App\Service\TerritorySelection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TerritorySelectionController extends AbstractController
{
    private const DESTINATIONS = [
        'home', 'territories', 'communes', 'proposals', 'consultations', 'experiments',
        'convergence', 'memory', 'transparency', 'infrastructure', 'governance',
        'citizen', 'charter', 'accounts', 'services', 'budget', 'procurement',
    ];

    public function __construct(
        private readonly GeographyCatalog $geography,
        private readonly TerritorySelection $selection,
    ) {
    }

    #[Route('/territoire/choisir', name: 'territory_choose', methods: ['POST'])]
    public function choose(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('territory_choose', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sélection territoriale invalide.');
        }
        $submitted = strtoupper(trim($request->request->getString('code', '')));
        $explicit = preg_match('/\A(?:INSEE|COG)\s*:\s*((?:[0-9]{5}|2[AB][0-9]{3}))\z/iD', $submitted, $m) === 1;
        $code = $explicit ? $m[1] : $submitted;
        // Protect the POST action as well: bare five digits denote a postal code,
        // not an INSEE code. Only a selected suggestion or named commune card is unambiguous.
        $ambiguous = preg_match('/\A[0-9]{5}\z/D', $submitted) === 1
            && !$explicit && $request->request->getString('code_type', '') !== 'insee';
        $destination = $request->request->getString('destination', 'home');
        if (!in_array($destination, self::DESTINATIONS, true)) {
            $destination = 'home';
        }
        if ($ambiguous || !$this->selection->choose($request, $code)) {
            // A city name or ambiguous postal code must never silently select a different municipality.
            return $this->redirectToRoute('site_communes', [
                'q' => substr(trim($request->request->getString('code', '')), 0, 70),
            ], Response::HTTP_SEE_OTHER);
        }
        $route = $destination === 'home' ? 'home' : 'site_' . $destination;

        return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/communes/suggestions', name: 'communes_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->query->getString('q', '');
        // A postal code can identify more than twelve municipalities.
        $postal = preg_match('/\\A[0-9]{2,5}\\z/D', trim($query)) === 1;
        $results = $this->geography->search($query, $postal ? 50 : 12);
        $coverage = $this->geography->coverage();
        $response = new JsonResponse([
            'communes' => $results, 'count' => count($results), 'postalQuery' => $postal,
            'postalAvailable' => $coverage['postals'],
        ]);
        $response->headers->set('Cache-Control', 'private, max-age=60');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
