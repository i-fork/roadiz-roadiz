<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Services;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RZ\Roadiz\Core\SearchEngine\DocumentSearchHandler;
use RZ\Roadiz\Core\SearchEngine\NodeSourceSearchHandler;
use RZ\Roadiz\Core\SearchEngine\SolariumFactory;
use RZ\Roadiz\Core\SearchEngine\SolariumFactoryInterface;
use RZ\Roadiz\Markdown\MarkdownInterface;
use Solarium\Client;
use Solarium\Core\Client\Adapter\AdapterInterface;
use Solarium\Core\Client\Adapter\Curl;

/**
 * Register Solr services for dependency injection container.
 */
class SolrServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     *
     * @return Container
     */
    public function register(Container $container)
    {
        $container[AdapterInterface::class] = function (Container $c) {
            $adapter = new Curl();
            if (isset($c['config']['solr']['endpoint'])) {
                $endpoints = $c['config']['solr']['endpoint'];
                $firstEndpoint = reset($endpoints);
                $adapter->setTimeout($firstEndpoint['timeout']);
            }
            return $adapter;
        };

        /**
         * @param Container $c
         *
         * @return null|Client
         */
        $container['solr'] = function (Container $c) {
            if (isset($c['config']['solr']['endpoint'])) {
                $options = $c['config']['solr'];
                if (isset($options['timeout'])) {
                    unset($options['timeout']);
                }
                $solrService = new Client(
                    $c[AdapterInterface::class],
                    $c['dispatcher'],
                    $options
                );
                $solrService->setDefaultEndpoint('localhost');
                return $solrService;
            } else {
                return null;
            }
        };

        /**
         * @param Container $c
         *
         * @return bool
         */
        $container['solr.ready'] = function (Container $c) {
            if (null !== $c['solr']) {
                $c['stopwatch']->start('Ping Solr');
                // create a ping query
                $ping = $c['solr']->createPing();
                // execute the ping query
                try {
                    $c['solr']->ping($ping);
                    $c['stopwatch']->stop('Ping Solr');
                    return true;
                } catch (\Exception $e) {
                    $c['stopwatch']->stop('Ping Solr');
                    return false;
                }
            } else {
                return false;
            }
        };

        /**
         * @param Container $c
         * @return null|NodeSourceSearchHandler
         */
        $container['solr.search.nodeSource'] = $container->factory(function (Container $c) {
            if ($c['solr.ready']) {
                return new NodeSourceSearchHandler($c['solr'], $c['em'], $c['logger']);
            } else {
                return null;
            }
        });

        /**
         * @param Container $c
         * @return null|DocumentSearchHandler
         */
        $container['solr.search.document'] = $container->factory(function (Container $c) {
            if ($c['solr.ready']) {
                return new DocumentSearchHandler($c['solr'], $c['em'], $c['logger']);
            } else {
                return null;
            }
        });

        /**
         * @param Container $c
         * @return SolariumFactoryInterface
         */
        $container[SolariumFactoryInterface::class] = function (Container $c) {
            return new SolariumFactory(
                $c['solr'],
                $c['logger'],
                $c[MarkdownInterface::class],
                $c['dispatcher'],
                $c['factory.handler']
            );
        };

        return $container;
    }
}
