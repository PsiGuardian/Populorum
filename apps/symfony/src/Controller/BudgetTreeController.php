<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BudgetCatalog;
use App\Service\BudgetTree;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Read-only, paginated endpoints: never expose the database or non-public files. */
final class BudgetTreeController extends AbstractController
{
    public function __construct(private readonly BudgetTree $tree, private readonly BudgetCatalog $catalog)
    {
    }

    #[Route('/finances-publiques/arbre/noeuds', name: 'budget_tree_nodes', methods: ['GET'])]
    public function nodes(Request $request): JsonResponse
    {
        $node = $request->query->getString('node', 'root');
        if (strlen($node) > 130 || !preg_match('/\A[a-zA-Z0-9_:|-]+\z/D', $node)) {
            return new JsonResponse(['error' => 'Identifiant de branche incorrect.'], 400);
        }
        $response = new JsonResponse($this->tree->children($node, $request->query->getInt('offset', 0)));
        $response->headers->set('Cache-Control', 'private, max-age=120');

        return $response;
    }

    #[Route('/finances-publiques/communes', name: 'budget_city_search', methods: ['GET'])]
    public function cities(Request $request): JsonResponse
    {
        $term = $request->query->getString('q', '');
        $response = new JsonResponse(['cities' => $this->catalog->search($term)]);
        $response->headers->set('Cache-Control', 'private, max-age=120');

        return $response;
    }
}
