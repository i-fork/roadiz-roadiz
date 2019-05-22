<?php
/**
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
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
 * @file NodeSourceApi.php
 * @author Maxime Constantinian
 */
namespace RZ\Roadiz\CMS\Utils;

use RZ\Roadiz\Core\Entities\NodesSources;
use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Core\Repositories\NodesSourcesRepository;

/**
 * Class NodeSourceApi
 * @package RZ\Roadiz\CMS\Utils
 */
class NodeSourceApi extends AbstractApi
{
    /**
     * @var string
     */
    protected $repository = NodesSources::class;

    /**
     * @param array $criteria
     * @return string
     */
    protected function getRepositoryName(array $criteria = null)
    {
        if (isset($criteria['node.nodeType']) && $criteria['node.nodeType'] instanceof NodeType) {
            $this->repository = $criteria['node.nodeType']->getSourceEntityFullQualifiedClassName();
            unset($criteria['node.nodeType']);
        } elseif (isset($criteria['node.nodeType']) &&
            is_array($criteria['node.nodeType']) &&
            count($criteria['node.nodeType']) === 1 &&
            $criteria['node.nodeType'][0] instanceof NodeType) {
            $this->repository = $criteria['node.nodeType'][0]->getSourceEntityFullQualifiedClassName();
            unset($criteria['node.nodeType']);
        } else {
            $this->repository = NodesSources::class;
        }

        return $this->repository;
    }

    /**
     * @return NodesSourcesRepository
     */
    public function getRepository()
    {
        return $this->container['em']->getRepository($this->repository);
    }

    /**
     * @param array $criteria
     * @param array|null $order
     * @param null $limit
     * @param null $offset
     * @return array
     */
    public function getBy(
        array $criteria,
        array $order = null,
        $limit = null,
        $offset = null
    ) {
        $this->getRepositoryName($criteria);

        return $this->getRepository()
                    ->findBy(
                        $criteria,
                        $order,
                        $limit,
                        $offset
                    );
    }

    /**
     * @param array $criteria
     * @return int
     */
    public function countBy(
        array $criteria
    ) {
        $this->getRepositoryName($criteria);

        return $this->getRepository()
                    ->countBy(
                        $criteria
                    );
    }

    /**
     * @param array $criteria
     * @param array|null $order
     * @return null|\RZ\Roadiz\Core\Entities\NodesSources
     */
    public function getOneBy(array $criteria, array $order = null)
    {
        $this->getRepositoryName($criteria);

        return $this->getRepository()
                    ->findOneBy(
                        $criteria,
                        $order
                    );
    }

    /**
     * Search Nodes-Sources using LIKE condition on title,
     * meta-title, meta-keywords and meta-description.
     *
     * @param $textQuery
     * @param int $limit
     * @param array $nodeTypes
     * @param bool $onlyVisible
     * @return array
     */
    public function searchBy($textQuery, $limit = 0, $nodeTypes = [], $onlyVisible = false)
    {
        $repository = $this->getRepository();

        if ($repository instanceof NodesSourcesRepository) {
            return $this->getRepository()
                ->findByTextQuery(
                    $textQuery,
                    $limit,
                    $nodeTypes,
                    $onlyVisible
                );
        }

        return [];
    }
}
