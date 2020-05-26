<?php
declare(strict_types=1);

namespace Themes\Rozier\Controllers\Users;

use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\UserSecurityType;
use Themes\Rozier\RozierApp;

/**
 * Provide user security views and forms.
 */
class UsersSecurityController extends RozierApp
{
    /**
     * @param Request $request
     * @param int     $userId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Twig_Error_Runtime
     */
    public function securityAction(Request $request, $userId)
    {
        // Only user managers can review security
        $this->denyAccessUnlessGranted('ROLE_ACCESS_USERS');

        /** @var User $user */
        $user = $this->get('em')->find(User::class, (int) $userId);

        if ($user !== null) {
            $this->assignation['user'] = $user;
            $form = $this->createForm(UserSecurityType::class, $user, [
                'canChroot' => $this->isGranted("ROLE_SUPERADMIN"),
                'entityManager' => $this->get('em'),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->get('em')->flush();

                $msg = $this->getTranslator()->trans(
                    'user.%name%.security.updated',
                    ['%name%' => $user->getUsername()]
                );

                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirect($this->generateUrl(
                    'usersSecurityPage',
                    ['userId' => $user->getId()]
                ));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('users/security.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }
}
