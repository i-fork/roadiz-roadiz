<?php

declare(strict_types=1);

namespace RZ\Roadiz\Core\Events;

use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Explorer\Event\NodeExplorerListEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Override default EntityListManager method arguments to allow more specific search criteria
 * when searching for a specific NodeType.
 */
final class NodeExplorerListEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            NodeExplorerListEvent::class => ['onNodeExplorerList', 100],
        ];
    }

    public function onNodeExplorerList(NodeExplorerListEvent $event): void
    {
        $entity = $event->getEntity();
        $criteria = $event->getCriteria();
        $ordering = $event->getOrdering();

        /*
         * If we are searching for a specific NodeType, we need to change entity class
         * to allow more specific search criteria.
         */
        if ($entity !== Node::class
            || !isset($criteria['nodeType'])
            || count($criteria['nodeType']) !== 1
            || !$criteria['nodeType'][0] instanceof NodeType
        ) {
            return;
        }

        $event->setEntity($criteria['nodeType'][0]->getSourceEntityFullQualifiedClassName());
        unset($criteria['nodeType']);
        $nodeFields = ['position', 'visible', 'locked', 'status', 'nodeName', 'createdAt', 'updatedAt'];

        // Prefix all criteria array keys names with "node." and recompose criteria array
        $event->setCriteria(array_combine(
            array_map(function ($key) use ($nodeFields) {
                if (in_array($key, $nodeFields)) {
                    return 'node.' . $key;
                }
                return $key;
            }, array_keys($criteria)),
            array_values($criteria)
        ));
        $event->setOrdering(array_combine(
            array_map(function ($key) use ($nodeFields) {
                if (in_array($key, $nodeFields)) {
                    return 'node.' . $key;
                }
                return $key;
            }, array_keys($ordering)),
            array_values($ordering)
        ));
    }
}
