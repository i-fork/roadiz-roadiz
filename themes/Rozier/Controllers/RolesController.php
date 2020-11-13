<?php
declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use RuntimeException;
use RZ\Roadiz\Core\Entities\Role;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Core\Exceptions\EntityRequiredException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Utils\SessionListFilters;

/**
 * Class RolesController
 *
 * @package Themes\Rozier\Controllers
 */
class RolesController extends RozierApp
{

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_ROLES');

        $listManager = $this->createEntityListManager(
            Role::class,
            [],
            ['name' => 'ASC']
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        /*
         * Stored in session
         */
        $sessionListFilter = new SessionListFilters('role_item_per_page');
        $sessionListFilter->handleItemPerPage($request, $listManager);
        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['roles'] = $listManager->getEntities();

        return $this->render('roles/list.html.twig', $this->assignation);
    }

    /**
     * Return an creation form for requested role.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function addAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_ROLES');

        $form = $this->buildAddForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $role = $this->addRole($form->getData());
                $msg = $this->getTranslator()->trans(
                    'role.%name%.created',
                    ['%name%' => $role->getName()]
                );
                $this->publishConfirmMessage($request, $msg);
            } catch (EntityAlreadyExistsException $e) {
                $this->publishErrorMessage($request, $e->getMessage());
            } catch (RuntimeException $e) {
                $this->publishErrorMessage($request, $e->getMessage());
            }

            return $this->redirect($this->generateUrl('rolesHomePage'));
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('roles/add.html.twig', $this->assignation);
    }

    /**
     * Return an deletion form for requested role.
     *
     * @param Request $request
     * @param int     $roleId
     *
     * @return Response
     */
    public function deleteAction(Request $request, $roleId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_ROLES');

        /** @var Role|null $role */
        $role = $this->get('em')
                     ->find(Role::class, (int) $roleId);
        if ($role !== null) {
            if (!$this->isGranted($role->getRole())) {
                throw $this->createAccessDeniedException('You cannot delete a role you do not have.');
            }
            $form = $this->buildDeleteForm($role);
            $form->handleRequest($request);

            if ($form->isSubmitted() &&
                $form->isValid() &&
                $form->getData()['roleId'] == $role->getId()) {
                try {
                    $this->deleteRole($form->getData(), $role);
                    $msg = $this->getTranslator()->trans(
                        'role.%name%.deleted',
                        ['%name%' => $role->getName()]
                    );
                    $this->publishConfirmMessage($request, $msg);
                } catch (EntityRequiredException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                } catch (RuntimeException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                }

                return $this->redirect($this->generateUrl('rolesHomePage'));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('roles/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Return an edition form for requested role.
     *
     * @param Request $request
     * @param int     $roleId
     *
     * @return Response
     */
    public function editAction(Request $request, $roleId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_ROLES');

        /** @var Role $role */
        $role = $this->get('em')
                     ->find(Role::class, (int) $roleId);

        if ($role !== null &&
            !$role->required()) {
            if (!$this->isGranted($role->getRole())) {
                throw $this->createAccessDeniedException('You cannot edit a role you do not have.');
            }
            $form = $this->buildEditForm($role);
            $form->handleRequest($request);

            if ($form->isSubmitted() &&
                $form->isValid() &&
                $form->getData()['roleId'] == $role->getId()) {
                try {
                    $this->editRole($form->getData(), $role);
                    $msg = $this->getTranslator()->trans(
                        'role.%name%.updated',
                        ['%name%' => $role->getName()]
                    );
                    $this->publishConfirmMessage($request, $msg);
                } catch (EntityAlreadyExistsException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                } catch (RuntimeException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                }

                return $this->redirect($this->generateUrl('rolesHomePage'));
            }

            $this->assignation['form'] = $form->createView();
            $this->assignation['role'] = $role;

            return $this->render('roles/edit.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Build add role form with name constraint.
     *
     * @return FormInterface
     */
    protected function buildAddForm()
    {
        $builder = $this->createFormBuilder()
                        ->add('name', TextType::class, [
                            'label' => 'name',
                            'constraints' => [
                                new NotNull(),
                                new NotBlank(),
                                new Regex([
                                    'pattern' => '#^ROLE_([A-Z0-9\_]+)$#',
                                    'message' => 'role.name.must_comply_with_standard',
                                ]),
                            ],
                        ]);

        return $builder->getForm();
    }

    /**
     * Build delete role form with name constraint.
     *
     * @param Role $role
     *
     * @return FormInterface
     */
    protected function buildDeleteForm(Role $role)
    {
        $builder = $this->createFormBuilder()
                        ->add('roleId', HiddenType::class, [
                            'data' => $role->getId(),
                            'constraints' => [
                                new NotNull(),
                                new NotBlank(),
                            ],
                        ]);

        return $builder->getForm();
    }

    /**
     * Build edit role form with name constraint.
     *
     * @param Role $role
     *
     * @return FormInterface
     */
    protected function buildEditForm(Role $role)
    {
        $defaults = [
            'name' => $role->getName(),
        ];
        $builder = $this->createFormBuilder($defaults)
                        ->add('roleId', HiddenType::class, [
                            'data' => $role->getId(),
                            'constraints' => [
                                new NotNull(),
                                new NotBlank(),
                            ],
                        ])
                        ->add('name', TextType::class, [
                            'data' => $role->getName(),
                            'label' => 'name',
                            'constraints' => [
                                new Regex([
                                    'pattern' => '#^ROLE_([A-Z\_]+)$#',
                                    'message' => 'role.name.must_comply_with_standard',
                                ]),
                            ],
                        ]);

        return $builder->getForm();
    }

    /**
     * @param array $data
     *
     * @return Role
     * @throws EntityAlreadyExistsException
     */
    protected function addRole(array $data)
    {
        if (isset($data['name'])) {
            $existing = $this->get('em')
                             ->getRepository(Role::class)
                             ->findOneBy(['name' => $data['name']]);
            if ($existing !== null) {
                throw new EntityAlreadyExistsException($this->getTranslator()->trans("role.name.already.exists"), 1);
            }

            $role = new Role($data['name']);
            $this->get('em')->persist($role);
            $this->get('em')->flush();

            // Clear result cache
            $cacheDriver = $this->get('em')->getConfiguration()->getResultCacheImpl();
            if ($cacheDriver !== null) {
                $cacheDriver->deleteAll();
            }

            return $role;
        } else {
            throw new RuntimeException("Role name is not defined", 1);
        }
    }

    /**
     * @param array $data
     * @param Role  $role
     *
     * @return Role
     * @throws EntityAlreadyExistsException
     * @throws EntityRequiredException
     */
    protected function editRole(array $data, Role $role)
    {
        if ($role->required()) {
            throw new EntityRequiredException($this->getTranslator()->trans("role.required.cannot_be_updated"), 1);
        }

        if (isset($data['name'])) {
            $existing = $this->get('em')
                             ->getRepository(Role::class)
                             ->findOneBy(['name' => $data['name']]);
            if ($existing !== null &&
                $existing->getId() != $role->getId()) {
                throw new EntityAlreadyExistsException($this->getTranslator()->trans("role.name.already.exists"), 1);
            }

            $role->setName($data['name']);
            $this->get('em')->flush();

            // Clear result cache
            $cacheDriver = $this->get('em')->getConfiguration()->getResultCacheImpl();
            if ($cacheDriver !== null) {
                $cacheDriver->deleteAll();
            }

            return $role;
        } else {
            throw new RuntimeException("Role name is not defined", 1);
        }
    }

    /**
     * @param array $data
     * @param Role  $role
     *
     * @throws EntityRequiredException
     */
    protected function deleteRole(array $data, Role $role)
    {
        if (!$role->required()) {
            $this->get('em')->remove($role);
            $this->get('em')->flush();

            // Clear result cache
            $cacheDriver = $this->get('em')->getConfiguration()->getResultCacheImpl();
            if ($cacheDriver !== null) {
                $cacheDriver->deleteAll();
            }
        } else {
            throw new EntityRequiredException($this->getTranslator()->trans("role.is.required"), 1);
        }
    }
}
