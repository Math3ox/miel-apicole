<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute des en-têtes de sécurité HTTP sur chaque réponse principale.
 * Protège contre le clickjacking, le MIME-sniffing et limite les fuites de référent.
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        // Empêche l'affichage du site dans une iframe tierce (clickjacking).
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Empêche le navigateur de deviner le type MIME des ressources.
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Ne transmet le référent complet qu'aux pages de même origine.
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Désactive l'accès à des API sensibles non utilisées.
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }
}
