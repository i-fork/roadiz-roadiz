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
 *
 * @file NodeTypesController.php
 * @author Ambroise Maupate
 */

namespace Themes\Rozier\Controllers\NodeTypes;

use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Core\Handlers\NodeTypeHandler;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Themes\Rozier\Forms\NodeTypeType;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Utils\SessionListFilters;

/**
 * NodeType controller
 */
class NodeTypesController extends RozierApp
{
    /**
     * List every node-types.
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');
        /*
         * Manage get request to filter list
         */
        $listManager = $this->createEntityListManager(
            NodeType::class,
            [],
            ['name' => 'ASC']
        );
        $listManager->setDisplayingNotPublishedNodes(true);

        /*
         * Stored in session
         */
        $sessionListFilter = new SessionListFilters('node_types_item_per_page');
        $sessionListFilter->handleItemPerPage($request, $listManager);

        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['node_types'] = $listManager->getEntities();

        return $this->render('node-types/list.html.twig', $this->assignation);
    }

    /**
     * Return an edition form for requested node-type.
     *
     * @param Request $request
     * @param int     $nodeTypeId
     *
     * @return Response
     */
    public function editAction(Request $request, $nodeTypeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        /** @var NodeType $nodeType */
        $nodeType = $this->get('em')
                         ->find(NodeType::class, (int) $nodeTypeId);

        if (null !== $nodeType) {
            $this->assignation['nodeType'] = $nodeType;

            $form = $this->createForm(NodeTypeType::class, $nodeType, [
                'em' => $this->get('em'),
                'name' => $nodeType->getName(),
            ]);

            $form->handleRequest($request);

            if ($form->isValid()) {
                try {
                    $this->get('em')->flush();
                    /** @var NodeTypeHandler $handler */
                    $handler = $this->get('factory.handler')->getHandler($nodeType);
                    $handler->updateSchema();

                    $msg = $this->getTranslator()->trans('nodeType.%name%.updated', ['%name%' => $nodeType->getName()]);
                    $this->publishConfirmMessage($request, $msg);
                } catch (EntityAlreadyExistsException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                }
                /*
                 * Redirect to update schema page
                 */
                return $this->redirect($this->generateUrl('nodeTypesSchemaUpdate'));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('node-types/edit.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Return an creation form for requested node-type.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function addAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        $nodeType = new NodeType();

        if (null !== $nodeType) {
            $this->assignation['nodeType'] = $nodeType;

            /*
             * form
             */
            $form = $this->createForm(NodeTypeType::class, $nodeType, [
                'em' => $this->get('em'),
            ]);

            $form->handleRequest($request);
            if ($form->isValid()) {
                try {
                    $this->get('em')->persist($nodeType);
                    $this->get('em')->flush();
                    /** @var NodeTypeHandler $handler */
                    $handler = $this->get('factory.handler')->getHandler($nodeType);
                    $handler->updateSchema();

                    $msg = $this->getTranslator()->trans('nodeType.%name%.created', ['%name%' => $nodeType->getName()]);
                    $this->publishConfirmMessage($request, $msg);

                    /*
                     * Redirect to update schema page
                     */
                    return $this->redirect($this->generateUrl('nodeTypesSchemaUpdate'));
                } catch (EntityAlreadyExistsException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                    return $this->redirect($this->generateUrl(
                        'nodeTypesAddPage'
                    ));
                }
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('node-types/add.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Return an deletion form for requested node-type.
     *
     * @param Request $request
     * @param int     $nodeTypeId
     *
     * @return Response
     */
    public function deleteAction(Request $request, $nodeTypeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES_DELETE');

        /** @var NodeType $nodeType */
        $nodeType = $this->get('em')
                         ->find(NodeType::class, (int) $nodeTypeId);

        if (null !== $nodeType) {
            $this->assignation['nodeType'] = $nodeType;

            $form = $this->buildDeleteForm($nodeType);

            $form->handleRequest($request);

            if ($form->isValid() &&
                $form->getData()['nodeTypeId'] == $nodeType->getId()) {
                /*
                 * Delete All node-type association and schema
                 */
                /** @var NodeTypeHandler $handler */
                $handler = $this->get('factory.handler')->getHandler($nodeType);
                $handler->deleteWithAssociations();

                $msg = $this->getTranslator()->trans('nodeType.%name%.deleted', ['%name%' => $nodeType->getName()]);
                $this->publishConfirmMessage($request, $msg);
                /*
                 * Redirect to update schema page
                 */
                return $this->redirect($this->generateUrl('nodeTypesSchemaUpdate'));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('node-types/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param NodeType $nodeType
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildDeleteForm(NodeType $nodeType)
    {
        $builder = $this->createFormBuilder()
                        ->add('nodeTypeId', HiddenType::class, [
                            'data' => $nodeType->getId(),
                            'constraints' => [
                                new NotBlank(),
                            ],
                        ]);

        return $builder->getForm();
    }
}
