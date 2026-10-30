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
