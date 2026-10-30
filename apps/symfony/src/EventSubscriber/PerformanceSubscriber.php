<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\PerformanceTrace;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class PerformanceSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['begin', 4096],
            'kernel.response' => ['finish', -4096],
        ];
    }

    public function begin(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            PerformanceTrace::begin();
        }
    }

    public function finish(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $parts = [];
        foreach (PerformanceTrace::report() as $name => $measurement) {
            $parts[] =
                $name . ';dur=' . number_format($measurement['ms'], 3, '.', '');
        }
        if ($parts) {
            $event
                ->getResponse()
                ->headers->set('Server-Timing', implode(', ', $parts));
        }
    }
}
