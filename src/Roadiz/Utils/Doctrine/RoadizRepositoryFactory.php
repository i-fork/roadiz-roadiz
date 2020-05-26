<?php
declare(strict_types=1);

namespace RZ\Roadiz\Utils\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;
use Pimple\Container;
use RZ\Roadiz\Core\Repositories\EntityRepository;

class RoadizRepositoryFactory implements RepositoryFactory
{
    /**
     * @var bool
     */
    private $isPreview;

    /**
     * The list of EntityRepository instances.
     *
     * @var \Doctrine\Common\Persistence\ObjectRepository[]
     */
    private $repositoryList = [];
    /**
     * @var Container
     */
    private $container;

    /**
     * RoadizRepositoryFactory constructor.
     * @param Container $container
     * @param bool $isPreview
     */
    public function __construct(Container $container, $isPreview = false)
    {
        $this->isPreview = $isPreview;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository(EntityManagerInterface $entityManager, $entityName)
    {
        $repositoryHash = $entityManager->getClassMetadata($entityName)->getName() . spl_object_hash($entityManager);

        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }

        return $this->repositoryList[$repositoryHash] = $this->createRepository($entityManager, $entityName);
    }

    /**
     * Create a new repository instance for an entity class.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager The EntityManager instance.
     * @param string                               $entityName    The name of the entity.
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    private function createRepository(EntityManagerInterface $entityManager, $entityName)
    {
        /* @var $metadata \Doctrine\ORM\Mapping\ClassMetadata */
        $metadata            = $entityManager->getClassMetadata($entityName);
        $repositoryClassName = $metadata->customRepositoryClassName
            ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        if (is_subclass_of($repositoryClassName, EntityRepository::class) ||
            $repositoryClassName == EntityRepository::class) {
            return new $repositoryClassName($entityManager, $metadata, $this->container, $this->isPreview);
        }

        return new $repositoryClassName($entityManager, $metadata);
    }
}
