<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Events;

use RZ\Roadiz\Core\Entities\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class FilterUserEvent
 *
 * @package RZ\Roadiz\Core\Events
 */
abstract class FilterUserEvent extends Event
{
    /**
     * @var User
     */
    private $user;

    /**
     * FilterUserEvent constructor.
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}
