<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\SearchEngine;

use Doctrine\Persistence\ObjectManager;
use RZ\Roadiz\Core\Entities\DocumentTranslation;
use RZ\Roadiz\Core\Entities\NodesSources;
use JMS\Serializer\Annotation as JMS;

/**
 * Wrapper over Solr search results and metas.
 *
 * @package RZ\Roadiz\Core\SearchEngine
 */
class SolrSearchResults implements SearchResultsInterface
{
    /**
     * @JMS\Exclude()
     */
    protected array $response;
    /**
     * @JMS\Exclude()
     */
    protected ObjectManager $entityManager;
    /**
     * @JMS\Exclude()
     */
    protected int $position;
    /**
     * @JMS\Exclude()
     */
    protected ?array $resultItems;

    /**
     * @param array $response
     * @param ObjectManager $entityManager
     */
    public function __construct(array $response, ObjectManager $entityManager)
    {
        $this->response = $response;
        $this->entityManager = $entityManager;
        $this->position = 0;
        $this->resultItems = null;
    }

    /**
     * @return int
     * @JMS\Groups({"search_results"})
     * @JMS\VirtualProperty()
     */
    public function getResultCount(): int
    {
        if (null !== $this->response &&
            isset($this->response['response']['numFound'])) {
            return (int) $this->response['response']['numFound'];
        }
        return 0;
    }

    /**
     * @return array
     * @JMS\Groups({"search_results"})
     * @JMS\VirtualProperty()
     */
    public function getResultItems(): array
    {
        if (null === $this->resultItems) {
            $this->resultItems = [];
            if (null !== $this->response &&
                isset($this->response['response']['docs'])) {
                $this->resultItems = array_filter(array_map(
                    function ($item) {
                        $object = $this->getHydratedItem($item);
                        if (isset($this->response["highlighting"])) {
                            $key = 'object';
                            if ($object instanceof NodesSources) {
                                $key = 'nodeSource';
                            }
                            return [
                                $key => $object,
                                'highlighting' => $this->getHighlighting($item['id']),
                            ];
                        }
                        return $object;
                    },
                    $this->response['response']['docs']
                ));
            }
        }

        return $this->resultItems;
    }

    /**
     * Merge collection_txt localized fields.
     *
     * @param string $id
     * @return array|array[]|mixed
     */
    protected function getHighlighting(string $id)
    {
        $highlights = $this->response['highlighting'][$id];
        if (!isset($highlights['collection_txt'])) {
            $collectionTxt = [];
            foreach ($highlights as $field => $value) {
                $collectionTxt = array_merge($collectionTxt, $value);
            }
            $highlights = array_merge($highlights, [
                'collection_txt' => $collectionTxt
            ]);
        }
        return $highlights;
    }

    /**
     * @param callable $callable
     *
     * @return array
     */
    public function map(callable $callable): array
    {
        return array_map($callable, $this->getResultItems());
    }

    /**
     * @param array $item
     *
     * @return array|object|null
     */
    protected function getHydratedItem(array $item)
    {
        if (isset($item[AbstractSolarium::TYPE_DISCRIMINATOR])) {
            switch ($item[AbstractSolarium::TYPE_DISCRIMINATOR]) {
                case SolariumNodeSource::DOCUMENT_TYPE:
                    return $this->entityManager->find(
                        NodesSources::class,
                        $item[SolariumNodeSource::IDENTIFIER_KEY]
                    );
                case SolariumDocumentTranslation::DOCUMENT_TYPE:
                    return $this->entityManager->find(
                        DocumentTranslation::class,
                        $item[SolariumDocumentTranslation::IDENTIFIER_KEY]
                    );
            }
        }

        return $item;
    }

    /**
     * Return the current element
     *
     * @link https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->getResultItems()[$this->position];
    }

    /**
     * Move forward to next element
     *
     * @link https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        ++$this->position;
    }

    /**
     * Return the key of the current element
     *
     * @link https://php.net/manual/en/iterator.key.php
     * @return string|float|int|bool|null scalar on success, or null on failure.
     * @since 5.0
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->position;
    }

    /**
     * Checks if current position is valid
     *
     * @link https://php.net/manual/en/iterator.valid.php
     * @return bool The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return isset($this->getResultItems()[$this->position]);
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @link https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = 0;
    }
}
