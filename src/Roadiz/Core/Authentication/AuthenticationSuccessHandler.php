<?php
declare(strict_types=1);
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
 * @file AuthenticationSuccessHandler.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Core\Authentication;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Authentication\Manager\LoginAttemptManager;
use RZ\Roadiz\Core\Entities\LoginAttempt;
use RZ\Roadiz\Core\Entities\User;
use RZ\Roadiz\Core\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * {@inheritdoc}
 */
class AuthenticationSuccessHandler extends DefaultAuthenticationSuccessHandler implements LoginAttemptAwareInterface
{
    protected $em;
    protected $rememberMeServices;
    private $loginAttemptManager;

    /**
     * Constructor.
     *
     * @param HttpUtils $httpUtils
     * @param EntityManager $em
     * @param RememberMeServicesInterface $rememberMeServices
     * @param array $options Options for processing a successful authentication attempt.
     */
    public function __construct(
        HttpUtils $httpUtils,
        EntityManager $em,
        RememberMeServicesInterface $rememberMeServices = null,
        array $options = []
    ) {
        parent::__construct($httpUtils, $options);
        $this->em = $em;
        $this->rememberMeServices = $rememberMeServices;

        /*
         * Enable session based _target_url
         */
        $this->setProviderKey(Kernel::SECURITY_DOMAIN);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $user = $token->getUser();
        if (null !== $user && $user instanceof User) {
            $this->getLoginAttemptManager()->onSuccessLoginAttempt($user->getUsername());
            $user->setLastLogin(new \DateTime('now'));
            $this->em->flush();
        }

        $response = parent::onAuthenticationSuccess($request, $token);

        if (null !== $this->rememberMeServices) {
            $this->rememberMeServices->loginSuccess($request, $response, $token);
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function getLoginAttemptManager(): LoginAttemptManager
    {
        return $this->loginAttemptManager;
    }

    /**
     * @inheritDoc
     */
    public function setLoginAttemptManager(LoginAttemptManager $loginAttemptManager)
    {
        $this->loginAttemptManager = $loginAttemptManager;
        return $this;
    }
}
