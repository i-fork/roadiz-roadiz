<?php
declare(strict_types=1);
/**
 * Copyright (c) 2017. Ambroise Maupate and Julien Blanchet
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
 * @file JoinNodeTypeFieldConfiguration.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class JoinNodeTypeFieldConfiguration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder('join');
        $root = $builder->getRootNode();
        $root->children()
            ->scalarNode('classname')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('Full qualified class name for Doctrine entity.')
            ->end()
            ->scalarNode('displayable')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('Method name to display entity name/title as a string.')
            ->end()
            ->scalarNode('alt_displayable')
                ->info('Method name to display entity secondary information as a string.')
            ->end()
            ->arrayNode('searchable')
                ->requiresAtLeastOneElement()
                ->prototype('scalar')
                ->cannotBeEmpty()
                ->end()
                ->info('Searchable entity fields for entity explorer.')
            ->end()
            ->arrayNode('where')
                ->prototype('array')
                    ->children()
                        ->scalarNode('field')->end()
                        ->scalarNode('value')->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('orderBy')
                ->prototype('array')
                    ->children()
                        ->scalarNode('field')->end()
                        ->scalarNode('direction')->end()
                    ->end()
                ->end()
            ->end();

        return $builder;
    }
}
