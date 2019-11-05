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
 * @file DoctrineRoleHierarchy.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Utils\Security;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\Role;
use RZ\Roadiz\Core\Repositories\RoleRepository;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Class DoctrineRoleHierarchy
 * @package RZ\Roadiz\Utils\Security
 */
class DoctrineRoleHierarchy extends RoleHierarchy
{
    /**
     * DoctrineRoleHierarchy constructor.
     * @param EntityManager|null $em
     */
    public function __construct(EntityManager $em = null)
    {
        if (null !== $em) {
            /** @var RoleRepository<Role> $roleRepository */
            $roleRepository = $em->getRepository(Role::class);
            $hierarchy = [
                Role::ROLE_SUPERADMIN => $roleRepository->getAllBasicRoleName(),
                Role::ROLE_BACKEND_USER => ['IS_AUTHENTICATED_ANONYMOUSLY'],
                Role::ROLE_DEFAULT => ['IS_AUTHENTICATED_ANONYMOUSLY'],
            ];
            parent::__construct($hierarchy);
        } else {
            parent::__construct([
                Role::ROLE_SUPERADMIN => [Role::ROLE_BACKEND_USER, Role::ROLE_DEFAULT],
                Role::ROLE_BACKEND_USER => ['IS_AUTHENTICATED_ANONYMOUSLY'],
                Role::ROLE_DEFAULT => ['IS_AUTHENTICATED_ANONYMOUSLY'],
            ]);
        }
    }
}
