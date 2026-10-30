<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RequestProtectionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $civicWritesLimiter,
        private RateLimiterFactory $searchReadsLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => [['limitWrites', 4], ['limitSearch', 3]],
            'kernel.response' => ['headers', -10],
        ];
    }

    public function limitWrites(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (
            !$event->isMainRequest()
            || $request->getMethod() !== 'POST'
            || (!str_starts_with($request->getPathInfo(), '/action/')
                && !str_starts_with($request->getPathInfo(), '/agir/')
                && $request->getPathInfo() !== '/recherche'
                && !str_starts_with($request->getPathInfo(), '/api/v1/actions/'))
        ) {
            return;
        }
        // L’adresse sert uniquement à limiter les requêtes, jamais à prouver l’identité ou l’admissibilité.
        $key = hash(
            'sha256',
            ($request->getClientIp() ?? 'unknown') .
                $request->getSession()->get('simulation_owner', 'visitor'),
        );
        $limit = $this->civicWritesLimiter->create($key)->consume();
        if (!$limit->isAccepted()) {
            $event->setResponse(
                new \Symfony\Component\HttpFoundation\JsonResponse(
                    ['error' => 'Trop de requêtes. Réessayez dans une minute.'],
                    429,
                    ['Retry-After' => '60'],
                ),
            );
        }
    }

    public function limitSearch(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->getMethod() !== 'GET'
            || (!str_starts_with($request->getPathInfo(), '/recherche/') && !$request->query->has('q'))) {
            return;
        }
        $key = hash('sha256', $request->getClientIp() ?? 'unknown');
        if (!$this->searchReadsLimiter->create($key)->consume()->isAccepted()) {
            $event->setResponse(new \Symfony\Component\HttpFoundation\Response(
                'Trop de recherches. Réessayez dans une minute.', 429, ['Retry-After' => '60'],
            ));
        }
    }

    public function headers(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin',
        );
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()',
        );
        if ($event->getRequest()->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000',
            );
        }
    }
}
