<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Restriction applicative de la démo publique ; le bundle reste intact. */
final class DemoProfilerReadOnlySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['guard', 512]];
    }

    public function guard(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (
            in_array(
                $request->attributes->get('_route'),
                ['_profiler_phpinfo', '_profiler_open_file', '_profiler_xdebug'],
                true,
            )
        ) {
            $event->setController(
                static fn () => new Response('Not Found', 404, ['Cache-Control' => 'no-store']),
            );

            return;
        }
        if (
            !$event->isMainRequest()
            || !$request->isMethod('POST')
            || $request->attributes->get('_route') !== 'omega_guardian_entry'
        ) {
            return;
        }
        $action = (string) $request->request->get('action', 'login');
        if ($action === 'logout' || ($action === 'login' && $request->request->get('username') === 'demo')) {
            return;
        }
        $event->setController(
            static fn () => new Response(
                'Démo publique en lecture seule : compte demo uniquement, aucune modification distante des comptes ou des règles.',
                403,
                ['Cache-Control' => 'no-store', 'Content-Type' => 'text/plain; charset=UTF-8'],
            ),
        );
    }
}
