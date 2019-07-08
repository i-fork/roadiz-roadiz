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
 * @file RoleRepository.php
 * @author Thomas Aufresne
 */
namespace RZ\Roadiz\Core\Repositories;

use RZ\Roadiz\Core\Entities\Role;

/**
 * {@inheritdoc}
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
        $query->useResultCache(true, 3600, 'RZRoleAllBasic');

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
        $query->useResultCache(true, 3600, 'RZRoleAll');

        return array_map('current', $query->getScalarResult());
    }
}
