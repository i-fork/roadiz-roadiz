<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Handlers;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Class UserProvider
 *
 * @package RZ\Roadiz\Core\Handlers
 */
class UserProvider implements UserProviderInterface
{
    protected $em;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return User
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        /** @var User|null $user */
        $user = $this->em
                     ->getRepository(User::class)
                     ->findOneBy(['username' => $username]);

        if ($user !== null) {
            return $user;
        } else {
            throw new UsernameNotFoundException();
        }
    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the RZ\Roadiz\Core\Entities\User
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     * @return User
     * @throws UnsupportedUserException
     */
    public function refreshUser(UserInterface $user)
    {
        if ($user instanceof User) {
            /** @var User|null $refreshUser */
            $refreshUser = $this->em->find(User::class, (int) $user->getId());

            if ($refreshUser !== null) {
                // If change important user fields
                if(!$refreshUser->isEqualTo($user)){
                    throw new UsernameNotFoundException('Authentication required again');
                }

                return $refreshUser;
            } else {
                throw new UnsupportedUserException();
            }
        }
        throw new UnsupportedUserException();
    }
    /**
     * Whether this provider supports the given user class
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        if ($class == User::class) {
            return true;
        }

        return false;
    }
}
