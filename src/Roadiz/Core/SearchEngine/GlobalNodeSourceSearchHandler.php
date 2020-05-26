<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\SearchEngine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use RZ\Roadiz\Core\Entities\NodesSources;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Repositories\NodesSourcesRepository;

/**
 * Class GlobalNodeSourceSearchHandler
 * @package RZ\Roadiz\Core\SearchEngine
 */
class GlobalNodeSourceSearchHandler
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var NodesSourcesRepository
     */
    private $repository;

    /**
     * GlobalNodeSourceSearchHandler constructor.
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        /** @var NodesSourcesRepository repository */
        $this->repository = $this->em->getRepository(NodesSources::class);
    }

    /**
     * @param bool $displayNonPublishedNodes
     *
     * @return $this
     */
    public function setDisplayNonPublishedNodes(bool $displayNonPublishedNodes)
    {
        $this->repository->setDisplayingNotPublishedNodes($displayNonPublishedNodes);
        return $this;
    }

    /**
     * @param string $searchTerm
     * @param int $resultCount
     * @param Translation|null $translation
     * @return NodesSources[]
     */
    public function getNodeSourcesBySearchTerm($searchTerm, $resultCount, ?Translation $translation = null)
    {
        $safeSearchTerms = strip_tags($searchTerm);

        /*
         * First try with Solr
         */
        /** @var array $nodesSources */
        $nodesSources = $this->repository->findBySearchQuery(
            $safeSearchTerms,
            $resultCount
        );

        /*
         * Second try with sources fields
         */
        if (count($nodesSources) === 0) {
            $nodesSources = $this->repository->searchBy(
                $safeSearchTerms,
                [],
                [],
                $resultCount
            );

            if (count($nodesSources) === 0) {
                /*
                 * Then try with node name.
                 */
                $qb = $this->repository->createQueryBuilder('ns');

                $qb->select('ns, n')
                    ->innerJoin('ns.node', 'n')
                    ->andWhere($qb->expr()->orX(
                        $qb->expr()->like('n.nodeName', ':nodeName'),
                        $qb->expr()->like('ns.title', ':nodeName')
                    ))
                    ->setMaxResults($resultCount)
                    ->setParameter('nodeName', '%' . $safeSearchTerms . '%');

                if (null !== $translation) {
                    $qb->andWhere($qb->expr()->eq('ns.translation', ':translation'))
                        ->setParameter('translation', $translation);
                }
                try {
                    return $qb->getQuery()->getResult();
                } catch (NoResultException $e) {
                    return [];
                }
            }
        }

        return $nodesSources;
    }
}
