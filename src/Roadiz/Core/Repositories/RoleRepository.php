<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Repositories;

use RZ\Roadiz\Core\Entities\Role;

/**
 * Class RoleRepository
 *
 * @package RZ\Roadiz\Core\Repositories
 * @extends EntityRepository<\RZ\Roadiz\Core\Entities\Role>
 */
class RoleRepository extends EntityRepository
{
    /**
     * @param string $roleName
     *
     * @return int
     */
    public function countByName($roleName)
    {
        $roleName = Role::cleanName($roleName);

        $query = $this->createQueryBuilder('r');
        $query->select($query->expr()->countDistinct('r'))
              ->andWhere($query->expr()->eq('r.name', ':name'))
              ->setParameter('name', $roleName);

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string $roleName
     * @return Role
     */
    public function findOneByName($roleName)
    {
        $roleName = Role::cleanName($roleName);

        $query = $this->createQueryBuilder('r');
        $query->andWhere($query->expr()->eq('r.name', ':name'))
              ->setMaxResults(1)
              ->setParameter('name', $roleName);

        $role = $query->getQuery()->getOneOrNullResult();
        if (null === $role) {
            $role = new Role($roleName);
            $this->_em->persist($role);
            $this->_em->flush();
        }

        return $role;
    }

    /**
     * Get every Roles names except for ROLE_SUPERADMIN.
     *
     * @return array
     */
    public function getAllBasicRoleName()
    {
        $builder = $this->createQueryBuilder('r');
        $builder->select('r.name')
              ->andWhere($builder->expr()->neq('r.name', ':name'))
              ->setParameter('name', Role::ROLE_SUPERADMIN);

        $query = $builder->getQuery();
        $query->enableResultCache(3600, 'RZRoleAllBasic');

        return array_map('current', $query->getScalarResult());
    }

    /**
     * Get every Roles names
     *
     * @return array
     */
    public function getAllRoleName()
    {
        $builder = $this->createQueryBuilder('r');
        $builder->select('r.name');

        $query = $builder->getQuery();
        $query->enableResultCache(3600, 'RZRoleAll');

        return array_map('current', $query->getScalarResult());
    }
}
