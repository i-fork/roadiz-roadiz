<?php
/**
 * Copyright © 2015, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
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
 * @file SolariumSubscriber.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Events;

use Psr\Log\LoggerInterface;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Entities\DocumentTranslation;
use RZ\Roadiz\Core\Entities\NodesSources;
use RZ\Roadiz\Core\Events\DocumentEvents;
use RZ\Roadiz\Core\Events\FilterDocumentEvent;
use RZ\Roadiz\Core\Events\FilterFolderEvent;
use RZ\Roadiz\Core\Events\FilterNodeEvent;
use RZ\Roadiz\Core\Events\FilterNodesSourcesEvent;
use RZ\Roadiz\Core\Events\FilterTagEvent;
use RZ\Roadiz\Core\Events\FolderEvents;
use RZ\Roadiz\Core\Events\NodeEvents;
use RZ\Roadiz\Core\Events\NodesSourcesEvents;
use RZ\Roadiz\Core\Events\TagEvents;
use RZ\Roadiz\Core\Handlers\HandlerFactory;
use RZ\Roadiz\Core\SearchEngine\SolariumDocumentTranslation;
use RZ\Roadiz\Core\SearchEngine\SolariumNodeSource;
use RZ\Roadiz\Markdown\MarkdownInterface;
use Solarium\Client;
use Solarium\Exception\HttpException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to Node and NodesSources event to update
 * a Solr server documents.
 */
class SolariumSubscriber implements EventSubscriberInterface
{
    /**
     * @var null|Client
     */
    protected $solr;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var MarkdownInterface
     */
    protected $markdown;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var HandlerFactory
     */
    private $handlerFactory;

    /**
     * SolariumSubscriber constructor.
     *
     * @param Client|null              $solr
     * @param EventDispatcherInterface $dispatcher
     * @param LoggerInterface          $logger
     * @param HandlerFactory           $handlerFactory
     * @param MarkdownInterface        $markdown
     */
    public function __construct(
        ?Client $solr,
        EventDispatcherInterface $dispatcher,
        LoggerInterface $logger,
        HandlerFactory $handlerFactory,
        MarkdownInterface $markdown
    ) {
        $this->solr = $solr;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->handlerFactory = $handlerFactory;
        $this->markdown = $markdown;
    }

    public static function getSubscribedEvents()
    {
        return [
            NodeEvents::NODE_STATUS_CHANGED => 'onSolariumNodeUpdate',
            NodeEvents::NODE_VISIBILITY_CHANGED => 'onSolariumNodeUpdate',
            NodesSourcesEvents::NODE_SOURCE_UPDATED => 'onSolariumSingleUpdate',
            NodesSourcesEvents::NODE_SOURCE_DELETED => 'onSolariumSingleDelete',
            NodeEvents::NODE_DELETED => 'onSolariumNodeDelete',
            NodeEvents::NODE_UNDELETED => 'onSolariumNodeUpdate',
            NodeEvents::NODE_TAGGED => 'onSolariumNodeUpdate',
            NodeEvents::NODE_CREATED => 'onSolariumNodeUpdate',
            TagEvents::TAG_UPDATED => 'onSolariumTagUpdate',
            DocumentEvents::DOCUMENT_FILE_UPLOADED => 'onSolariumDocumentUpdate',
            DocumentEvents::DOCUMENT_TRANSLATION_UPDATED => 'onSolariumDocumentUpdate',
            DocumentEvents::DOCUMENT_IN_FOLDER => 'onSolariumDocumentUpdate',
            DocumentEvents::DOCUMENT_OUT_FOLDER => 'onSolariumDocumentUpdate',
            DocumentEvents::DOCUMENT_UPDATED => 'onSolariumDocumentUpdate',
            DocumentEvents::DOCUMENT_DELETED => 'onSolariumDocumentDelete',
            FolderEvents::FOLDER_UPDATED => 'onSolariumFolderUpdate',
        ];
    }

    /**
     * Update or create Solr document for current Node-source.
     *
     * @param  FilterNodesSourcesEvent $event
     */
    public function onSolariumSingleUpdate(FilterNodesSourcesEvent $event)
    {
        // Update Solr Search engine if setup
        if (null !== $this->solr) {
            try {
                $solrSource = $this->getSolariumNodeSource(
                    $event->getNodeSource()
                );
                $solrSource->getDocumentFromIndex();
                $solrSource->updateAndCommit();
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Delete solr document for current Node-source.
     *
     * @param  FilterNodesSourcesEvent $event
     */
    public function onSolariumSingleDelete(FilterNodesSourcesEvent $event)
    {
        // Update Solr Search engine if setup
        if (null !== $this->solr) {
            try {
                $solrSource = $this->getSolariumNodeSource(
                    $event->getNodeSource()
                );
                $solrSource->getDocumentFromIndex();
                $solrSource->removeAndCommit();
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Delete solr documents for each Node sources.
     *
     * @param  FilterNodeEvent $event
     */
    public function onSolariumNodeDelete(FilterNodeEvent $event)
    {
        if (null !== $this->solr) {
            try {
                foreach ($event->getNode()->getNodeSources() as $nodeSource) {
                    $solrSource = $this->getSolariumNodeSource(
                        $nodeSource
                    );
                    $solrSource->getDocumentFromIndex();
                    $solrSource->removeAndCommit();
                }
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Update or create solr documents for each Node sources.
     *
     * @param FilterNodeEvent $event
     */
    public function onSolariumNodeUpdate(FilterNodeEvent $event)
    {
        if (null !== $this->solr) {
            try {
                foreach ($event->getNode()->getNodeSources() as $nodeSource) {
                    $solrSource = $this->getSolariumNodeSource(
                        $nodeSource
                    );
                    $solrSource->getDocumentFromIndex();
                    $solrSource->updateAndCommit();
                }
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }


    /**
     * Delete solr documents for each Document translation.
     *
     * @param FilterDocumentEvent $event
     */
    public function onSolariumDocumentDelete(FilterDocumentEvent $event)
    {
        $document = $event->getDocument();
        if (null !== $this->solr && $document instanceof Document) {
            try {
                foreach ($document->getDocumentTranslations() as $documentTranslation) {
                    $solarium = $this->getSolariumDocumentTranslation(
                        $documentTranslation
                    );
                    $solarium->getDocumentFromIndex();
                    $solarium->removeAndCommit();
                }
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Update or create solr documents for each Document translation.
     *
     * @param FilterDocumentEvent $event
     */
    public function onSolariumDocumentUpdate(FilterDocumentEvent $event)
    {
        $document = $event->getDocument();
        if (null !== $this->solr && $document instanceof Document) {
            try {
                foreach ($document->getDocumentTranslations() as $documentTranslation) {
                    $solarium = $this->getSolariumDocumentTranslation(
                        $documentTranslation
                    );
                    $solarium->getDocumentFromIndex();
                    $solarium->updateAndCommit();
                }
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Update solr documents linked to current event Tag.
     *
     * @param  FilterTagEvent $event
     */
    public function onSolariumTagUpdate(FilterTagEvent $event)
    {
        if (null !== $this->solr) {
            try {
                $update = $this->solr->createUpdate();
                $nodes = $event->getTag()->getNodes();

                foreach ($nodes as $node) {
                    foreach ($node->getNodeSources() as $nodeSource) {
                        $solrSource = $this->getSolariumNodeSource(
                            $nodeSource
                        );
                        $solrSource->getDocumentFromIndex();
                        $solrSource->update($update);
                    }
                }
                $this->solr->update($update);

                // then optimize
                $optimizeUpdate = $this->solr->createUpdate();
                $optimizeUpdate->addOptimize(true, true, 5);
                $this->solr->update($optimizeUpdate);
                // and commit
                $finalCommitUpdate = $this->solr->createUpdate();
                $finalCommitUpdate->addCommit(true, true, false);
                $this->solr->update($finalCommitUpdate);
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Update solr documents linked to current event Folder.
     *
     * @param FilterFolderEvent $event
     */
    public function onSolariumFolderUpdate(FilterFolderEvent $event)
    {
        if (null !== $this->solr) {
            try {
                $update = $this->solr->createUpdate();
                $documents = $event->getFolder()->getDocuments();

                /** @var Document $document */
                foreach ($documents as $document) {
                    foreach ($document->getDocumentTranslations() as $documentTranslation) {
                        $solarium = $this->getSolariumDocumentTranslation(
                            $documentTranslation
                        );
                        $solarium->getDocumentFromIndex();
                        $solarium->update($update);
                    }
                }
                $this->solr->update($update);

                // then optimize
                $optimizeUpdate = $this->solr->createUpdate();
                $optimizeUpdate->addOptimize(true, true, 5);
                $this->solr->update($optimizeUpdate);
                // and commit
                $finalCommitUpdate = $this->solr->createUpdate();
                $finalCommitUpdate->addCommit(true, true, false);
                $this->solr->update($finalCommitUpdate);
            } catch (HttpException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * @param NodesSources $nodeSource
     *
     * @return SolariumNodeSource
     */
    protected function getSolariumNodeSource(NodesSources $nodeSource): SolariumNodeSource
    {
        return new SolariumNodeSource(
            $nodeSource,
            $this->solr,
            $this->dispatcher,
            $this->handlerFactory,
            $this->logger,
            $this->markdown
        );
    }

    /**
     * @param DocumentTranslation $documentTranslation
     *
     * @return SolariumDocumentTranslation
     */
    protected function getSolariumDocumentTranslation(DocumentTranslation $documentTranslation): SolariumDocumentTranslation
    {
        return new SolariumDocumentTranslation(
            $documentTranslation,
            $this->solr,
            $this->logger,
            $this->markdown
        );
    }
}
