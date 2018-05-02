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
 * @file DoctrineFiltersServiceProvider.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Core\Services;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RZ\Roadiz\Utils\Doctrine\ORM\Filter\ANodesFilter;
use RZ\Roadiz\Utils\Doctrine\ORM\Filter\BNodesFilter;
use RZ\Roadiz\Utils\Doctrine\ORM\Filter\NodesSourcesNodeFilter;
use RZ\Roadiz\Utils\Doctrine\ORM\Filter\NodeTranslationFilter;
use RZ\Roadiz\Utils\Doctrine\ORM\Filter\NodeTypeFilter;

class DoctrineFiltersServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(Container $pimple)
    {
        $pimple->extend('dispatcher', function ($dispatcher) {
            $dispatcher->addSubscriber(new NodeTypeFilter());
            $dispatcher->addSubscriber(new ANodesFilter());
            $dispatcher->addSubscriber(new BNodesFilter());
            $dispatcher->addSubscriber(new NodesSourcesNodeFilter());
            $dispatcher->addSubscriber(new NodeTranslationFilter());

            return $dispatcher;
        });
    }
}
