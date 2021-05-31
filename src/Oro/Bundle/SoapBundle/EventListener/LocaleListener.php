<?php

namespace Oro\Bundle\SoapBundle\EventListener;

use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets translatable locale from current request
 */
class LocaleListener implements EventSubscriberInterface
{
    const API_PREFIX = '/api/rest/';

    private TranslatableListener $translatableListener;

    /**
     * @param TranslatableListener $translatableListener
     */
    public function __construct(TranslatableListener $translatableListener)
    {
        $this->translatableListener = $translatableListener;
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $locale = str_replace('-', '_', $request->query->get('locale'));
        if ($locale && $this->isApiRequest($request)) {
            $request->setLocale($locale);
            $this->translatableListener->setTranslatableLocale($locale);
        }
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    protected function isApiRequest(Request $request): bool
    {
        return strpos($request->getPathInfo(), self::API_PREFIX) === 0;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // must be registered after Symfony's original LocaleListener
            KernelEvents::REQUEST  => [['onKernelRequest', -17]],
        ];
    }
}
