<?php
declare(strict_types=1);

namespace RZ\Roadiz\Utils\Clearer\EventListener;

use RZ\Roadiz\Core\Events\Cache\CachePurgeRequestEvent;
use RZ\Roadiz\Utils\Clearer\RoutingCacheClearer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RoutingCacheEventSubscriber implements EventSubscriberInterface
{
    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            CachePurgeRequestEvent::class => ['onPurgeRequest', 1],
        ];
    }

    /**
     * @param CachePurgeRequestEvent $event
     */
    public function onPurgeRequest(CachePurgeRequestEvent $event)
    {
        try {
            $clearer = new RoutingCacheClearer($event->getKernel()->getCacheDir());
            $clearer->clear();
            $event->addMessage($clearer->getOutput(), static::class, 'routingCache');
        } catch (\Exception $e) {
            $event->addError($e->getMessage(), static::class, 'routingCache');
        }
    }
}
