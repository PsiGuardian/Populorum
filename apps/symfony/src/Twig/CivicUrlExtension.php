<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** One URL policy for all links: real named routes, canonical search and detail paths. */
final class CivicUrlExtension extends AbstractExtension
{
    public function __construct(private UrlGeneratorInterface $router)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('civic_path', [$this, 'path'])];
    }

    public function path(string $name, array $parameters = []): string
    {
        // Canonical service paths never serialize plan/scenario as query parameters.
        if ($name === 'site_services' || ($name === 'section' && ($parameters['view'] ?? '') === 'services')) {
            unset($parameters['view']);
            $plan = $parameters['plan'] ?? null;
            $scenario = $parameters['scenario'] ?? null;
            unset($parameters['plan'], $parameters['scenario']);
            if ($scenario !== null) {
                return $this->router->generate('site_service_scenario', ['plan' => $plan ?? 'water', 'scenario' => $scenario] + $parameters);
            }
            if ($plan !== null) {
                return $this->router->generate('site_service_plan', ['plan' => $plan] + $parameters);
            }

            return $this->router->generate('site_services', $parameters);
        }

        // Public filters are path segments, not query strings.
        $publicBase = match (true) {
            $name === 'site_markets' => 'marches-publics',
            $name === 'site_communes' => 'communes',
            $name === 'site_convergence' || ($name === 'section' && ($parameters['view'] ?? '') === 'convergence') => 'convergence',
            $name === 'site_budget' || ($name === 'section' && ($parameters['view'] ?? '') === 'budget') => 'budget-et-priorites',
            default => null,
        };
        if ($publicBase !== null) {
            unset($parameters['view']);
            $route = match ($publicBase) {
                'marches-publics' => 'site_markets',
                'communes' => 'site_communes',
                'convergence' => 'site_convergence',
                default => 'site_budget',
            };

            return $this->router->generate($route)
                . \App\Service\PublicFilterPath::encode($publicBase, $parameters);
        }
        if ($name === 'simulation_action') {
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
                ] as $key
            ) {
                if (!empty($parameters[$key])) {
                    $id = $parameters[$key];
                    unset($parameters[$key]);

                    return $this->router->generate(
                        'site_action_object',
                        [
                            'contextKey' => $key,
                            'contextId' => $id,
                        ] + $parameters,
                    );
                }
            }
        }
        if ($name !== 'section') {
            return $this->router->generate($name, $parameters);
        }
        // Les rubriques 2 et 3 sont désormais des pages nationales autonomes :
        // les anciens identifiants de communes réelles (c13068, etc.) ne doivent
        // jamais être transmis au routeur t1/t2/t3 des anciennes simulations.
        if (($parameters['view'] ?? '') === 'proposals') {
            return $this->router->generate('site_proposals');
        }
        if (($parameters['view'] ?? '') === 'consultations') {
            return $this->router->generate('site_consultations');
        }
        $view = $parameters['view'] ?? 'proposals';
        unset($parameters['view']);
        if (
            array_key_exists('q', $parameters)
            || isset($parameters['territory'])
        ) {
            $parameters += ['territory' => 'all', 'q' => ''];
            $parameters['term'] = $parameters['q'];
            $route =
                $parameters['term'] === ''
                    ? 'site_search_empty'
                    : 'site_search';
            if ($route === 'site_search_empty') {
                unset($parameters['term']);
            }
            unset($parameters['q']);

            return $this->router->generate(
                $route,
                ['view' => $view] + $parameters,
            );
        }
        if (!empty($parameters['id'])) {
            return $this->router->generate(
                'site_selected',
                ['view' => $view] + $parameters,
            );
        }
        unset($parameters['id']);

        return $this->router->generate('site_' . $view, $parameters);
    }
}
