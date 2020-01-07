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
 * @file DoctrineHandler.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Utils\Log\Handler;

use Doctrine\ORM\EntityManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use RZ\Roadiz\Core\Entities\Log;
use RZ\Roadiz\Core\Entities\NodesSources;
use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * A log system which store message in database.
 */
class DoctrineHandler extends AbstractProcessingHandler
{
    /**
     * @var EntityManager
     */
    protected $em;
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;
    /**
     * @var null|User
     */
    protected $user = null;
    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(
        EntityManager $em,
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack,
        $level = Logger::DEBUG,
        $bubble = true
    ) {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;

        parent::__construct($level, $bubble);
    }

    /**
     * @return TokenStorageInterface
     */
    public function getTokenStorage(): TokenStorageInterface
    {
        return $this->tokenStorage;
    }
    /**
     * @param TokenStorageInterface $tokenStorage
     *
     * @return $this
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage): DoctrineHandler
    {
        $this->tokenStorage = $tokenStorage;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(User $user = null): DoctrineHandler
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return RequestStack
     */
    public function getRequestStack(): RequestStack
    {
        return $this->requestStack;
    }

    /**
     * @param RequestStack $requestStack
     * @return DoctrineHandler
     */
    public function setRequestStack(RequestStack $requestStack): DoctrineHandler
    {
        $this->requestStack = $requestStack;
        return $this;
    }

    /**
     * @param array  $record
     */
    public function write(array $record): void
    {
        try {
            if ($this->em->isOpen()) {
                $log = new Log(
                    $record['level'],
                    $record['message']
                );

                /*
                 * Use available securityAuthorizationChecker to provide a valid user
                 */
                if (null !== $this->getTokenStorage() &&
                    null !== $this->getTokenStorage()->getToken() &&
                    null !== $user = $this->getTokenStorage()->getToken()->getUser()) {
                    if ($user instanceof User) {
                        $log->setUser($user);
                    }
                }
                /*
                 * Use manually set user
                 */
                if (null !== $this->getUser()) {
                    $log->setUser($this->getUser());
                }

                /*
                 * Add client IP to log if it’s an HTTP request
                 */
                if (null !== $this->requestStack->getMasterRequest()) {
                    $log->setClientIp($this->requestStack->getMasterRequest()->getClientIp());
                }

                /*
                 * Add a related node-source entity
                 */
                if (isset($record['context']['source']) &&
                    null !== $record['context']['source'] &&
                    $record['context']['source'] instanceof NodesSources) {
                    $log->setNodeSource($record['context']['source']);
                }

                $this->em->persist($log);
                $this->em->flush();
            }
        } catch (\Exception $e) {
            /*
             * Need to prevent SQL errors over throwing
             * if PDO has fault
             */
        }
    }
}
