<?php
/*
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
 *
 * @file UsersGroupsController.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Controllers\Users;

use RZ\Roadiz\CMS\Forms\GroupsType;
use RZ\Roadiz\Core\Entities\Group;
use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Themes\Rozier\RozierApp;

/**
 * {@inheritdoc}
 */
class UsersGroupsController extends RozierApp
{
    /**
     * @param Request $request
     * @param int     $userId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editGroupsAction(Request $request, $userId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_USERS');

        $user = $this->get('em')
                     ->find(User::class, (int) $userId);

        if ($user !== null) {
            $this->assignation['user'] = $user;

            $form = $this->buildEditGroupsForm($user);
            $form->handleRequest($request);

            if ($form->isValid()) {
                $group = $this->addUserGroup($form->getData(), $user);

                $msg = $this->getTranslator()->trans('user.%user%.group.%group%.linked', [
                    '%user%' => $user->getUserName(),
                    '%group%' => $group->getName(),
                ]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirect($this->generateUrl(
                    'usersEditGroupsPage',
                    ['userId' => $user->getId()]
                ));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('users/groups.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Return a deletion form for requested group depending on the user.
     *
     * @param Request $request
     * @param int     $userId
     * @param int     $groupId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function removeGroupAction(Request $request, $userId, $groupId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_USERS');

        $user = $this->get('em')->find(User::class, (int) $userId);
        $group = $this->get('em')->find(Group::class, (int) $groupId);

        if (!$this->isGranted($group)) {
            throw $this->createAccessDeniedException();
        }

        if ($user !== null) {
            $this->assignation['user'] = $user;
            $this->assignation['group'] = $group;

            $form = $this->buildRemoveGroupForm($user, $group);
            $form->handleRequest($request);

            if ($form->isValid()) {
                $group = $this->removeUserGroup($form->getData(), $user);

                $msg = $this->getTranslator()->trans('user.%user%.group.%group%.removed', [
                    '%user%' => $user->getUserName(),
                    '%group%' => $group->getName(),
                ]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirect($this->generateUrl(
                    'usersEditGroupsPage',
                    ['userId' => $user->getId()]
                ));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('users/removeGroup.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param array $data
     * @param User  $user
     *
     * @return Group
     */
    private function addUserGroup($data, User $user)
    {
        if ($data['userId'] == $user->getId()) {
            $group = $this->get('em')
                          ->find(Group::class, $data['group']);

            if ($group !== null) {
                $user->addGroup($group);
                $this->get('em')->flush();
            }

            return $group;
        }

        return null;
    }

    /**
     * @param array $data
     * @param User  $user
     *
     * @return Group
     */
    private function removeUserGroup($data, User $user)
    {
        if ($data['userId'] == $user->getId()) {
            $group = $this->get('em')
                          ->find(Group::class, $data['groupId']);

            if ($group !== null) {
                $user->removeGroup($group);
                $this->get('em')->flush();
            }

            return $group;
        }

        return null;
    }

    /**
     * @param User $user
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildEditGroupsForm(User $user)
    {
        $defaults = [
            'userId' => $user->getId(),
        ];
        $builder = $this->createFormBuilder($defaults)
                        ->add(
                            'userId',
                            HiddenType::class,
                            [
                                'data' => $user->getId(),
                                'constraints' => [
                                    new NotBlank(),
                                ],
                            ]
                        )
                        ->add(
                            'group',
                            GroupsType::class,
                            [
                                'label' => 'Group',
                                'entityManager' => $this->get('em'),
                                'authorizationChecker' => $this->get('securityAuthorizationChecker'),
                            ]
                        );

        return $builder->getForm();
    }

    /**
     * @param User  $user
     * @param Group $group
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildRemoveGroupForm(User $user, Group $group)
    {
        $builder = $this->createFormBuilder()
                        ->add(
                            'userId',
                            HiddenType::class,
                            [
                                'data' => $user->getId(),
                                'constraints' => [
                                    new NotBlank(),
                                ],
                            ]
                        )
                        ->add(
                            'groupId',
                            HiddenType::class,
                            [
                                'data' => $group->getId(),
                                'constraints' => [
                                    new NotBlank(),
                                ],
                            ]
                        );

        return $builder->getForm();
    }
}
