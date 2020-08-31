<?php
declare(strict_types=1);

namespace RZ\Roadiz\Utils\Clearer\EventListener;

use RZ\Roadiz\Core\Events\Cache\CachePurgeRequestEvent;
use RZ\Roadiz\Utils\Clearer\OPCacheClearer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OPCacheEventSubscriber implements EventSubscriberInterface
{
    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            CachePurgeRequestEvent::class => ['onPurgeRequest', 3],
        ];
    }

    /**
     * @param CachePurgeRequestEvent $event
     */
    public function onPurgeRequest(CachePurgeRequestEvent $event)
    {
        try {
            $clearer = new OPCacheClearer();
            if (false !== $clearer->clear()) {
                $event->addMessage($clearer->getOutput(), static::class, 'OPCode cache');
            }
        } catch (\Exception $e) {
            $event->addError($e->getMessage(), static::class, 'OPCode cache');
        }
    }
}
