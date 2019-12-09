<?php
/**
 * Copyright (c) 2018. Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file NodeFilter.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Utils\Doctrine\ORM\Filter;

use RZ\Roadiz\Core\Events\FilterNodesSourcesQueryBuilderCriteriaEvent;
use RZ\Roadiz\Core\Events\FilterQueryBuilderCriteriaEvent;
use RZ\Roadiz\Core\Events\QueryBuilder\QueryBuilderNodesSourcesBuildEvent;
use RZ\Roadiz\Core\Repositories\EntityRepository;
use RZ\Roadiz\Utils\Doctrine\ORM\SimpleQueryBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class NodeTypeFilter.
 *
 * Filter on nodeType fields when criteria contains nodeType. prefix.
 *
 * @package RZ\Roadiz\Utils\Doctrine\ORM\Filter
 */
class NodesSourcesNodeFilter implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            QueryBuilderNodesSourcesBuildEvent::class => [['onNodesSourcesQueryBuilderBuild', -10]],
        ];
    }

    /**
     * @param FilterQueryBuilderCriteriaEvent $event
     *
     * @return bool
     */
    protected function supports(FilterQueryBuilderCriteriaEvent $event): bool
    {
        if ($event instanceof FilterNodesSourcesQueryBuilderCriteriaEvent &&
            $event->supports()) {
            return true;
        }

        return false;
    }

    /**
     * @param FilterQueryBuilderCriteriaEvent $event
     */
    public function onNodesSourcesQueryBuilderBuild(FilterQueryBuilderCriteriaEvent $event)
    {
        if ($this->supports($event)) {
            $simpleQB = new SimpleQueryBuilder($event->getQueryBuilder());
            if (false !== strpos($event->getProperty(), 'node.')) {
                // Prevent other query builder filters to execute
                $event->stopPropagation();
                $qb = $event->getQueryBuilder();
                $baseKey = $simpleQB->getParameterKey($event->getProperty());

                if (!$simpleQB->joinExists(
                    $simpleQB->getRootAlias(),
                    EntityRepository::NODE_ALIAS
                )
                ) {
                    $qb->innerJoin(
                        $simpleQB->getRootAlias() . '.node',
                        EntityRepository::NODE_ALIAS
                    );
                }

                $prefix = EntityRepository::NODE_ALIAS . '.';
                $key = str_replace('node.', '', $event->getProperty());
                $qb->andWhere($simpleQB->buildExpressionWithoutBinding($event->getValue(), $prefix, $key, $baseKey));
            }
        }
    }
}
