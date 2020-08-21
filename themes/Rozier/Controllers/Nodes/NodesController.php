<?php
declare(strict_types=1);

namespace Themes\Rozier\Controllers\Nodes;

use RZ\Roadiz\Core\Authorization\Chroot\NodeChrootResolver;
use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Entities\User;
use RZ\Roadiz\Core\Events\Node\NodeCreatedEvent;
use RZ\Roadiz\Core\Events\Node\NodeDeletedEvent;
use RZ\Roadiz\Core\Events\Node\NodePathChangedEvent;
use RZ\Roadiz\Core\Events\Node\NodeUndeletedEvent;
use RZ\Roadiz\Core\Events\Node\NodeUpdatedEvent;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Core\Handlers\NodeHandler;
use RZ\Roadiz\Utils\Node\Exception\SameNodeUrlException;
use RZ\Roadiz\Utils\Node\NodeMover;
use RZ\Roadiz\Utils\Node\UniqueNodeGenerator;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Workflow\Workflow;
use Themes\Rozier\Forms;
use Themes\Rozier\Forms\Node\AddNodeType;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Traits\NodesTrait;
use Themes\Rozier\Utils\SessionListFilters;

/**
 * Class NodesController
 *
 * @package Themes\Rozier\Controllers\Nodes
 */
class NodesController extends RozierApp
{
    use NodesTrait;

    /**
     * List every nodes.
     *
     * @param Request $request
     * @param string  $filter
     *
     * @return Response
     * @throws \Twig_Error_Runtime
     */
    public function indexAction(Request $request, $filter = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');
        $translation = $this->get('defaultTranslation');

        /** @var User|null $user */
        $user = $this->getUser();

        switch ($filter) {
            case 'draft':
                $this->assignation['mainFilter'] = $filter;
                $arrayFilter = [
                    'status' => Node::DRAFT,
                ];
                break;
            case 'pending':
                $this->assignation['mainFilter'] = $filter;
                $arrayFilter = [
                    'status' => Node::PENDING,
                ];
                break;
            case 'archived':
                $this->assignation['mainFilter'] = $filter;
                $arrayFilter = [
                    'status' => Node::ARCHIVED,
                ];
                break;
            case 'deleted':
                $this->assignation['mainFilter'] = $filter;
                $arrayFilter = [
                    'status' => Node::DELETED,
                ];
                break;
            default:
                $this->assignation['mainFilter'] = 'all';
                $arrayFilter = [];
                break;
        }

        if (null !== $user) {
            $arrayFilter["chroot"] = $this->get(NodeChrootResolver::class)->getChroot($user);
        }

        /*
         * Manage get request to filter list
         */
        $listManager = $this->createEntityListManager(
            Node::class,
            $arrayFilter
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        $listManager->setDisplayingAllNodesStatuses(true);

        /*
         * Stored in session
         */
        $sessionListFilter = new SessionListFilters('node_list_item_per_page');
        $sessionListFilter->handleItemPerPage($request, $listManager);
        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['translation'] = $translation;
        $this->assignation['availableTranslations'] = $this->get('em')
            ->getRepository(Translation::class)
            ->findAllAvailable();
        $this->assignation['nodes'] = $listManager->getEntities();
        $this->assignation['nodeTypes'] = $this->get('em')
            ->getRepository(NodeType::class)
            ->findBy([
                'visible' => true,
            ]);

        return $this->render('nodes/list.html.twig', $this->assignation);
    }

    /**
     * Return an edition form for requested node.
     *
     * @param Request $request
     * @param int     $nodeId
     * @param int     $translationId
     *
     * @return Response
     * @throws \Twig_Error_Runtime
     */
    public function editAction(Request $request, $nodeId, $translationId = null)
    {
        $this->validateNodeAccessForRole('ROLE_ACCESS_NODES_SETTING', $nodeId);

        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $nodeId);

        if (null !== $node) {
            $this->get('em')->refresh($node);
            $translation = $this->get('defaultTranslation');

            $this->assignation['node'] = $node;
            $this->assignation['source'] = $node->getNodeSources()->first();
            $this->assignation['translation'] = $translation;

            $this->assignation['available_translations'] = [];
            foreach ($node->getNodeSources() as $ns) {
                $this->assignation['available_translations'][] = $ns->getTranslation();
            }

            /*
             * Handle StackTypes form
             */
            $stackTypesForm = $this->buildStackTypesForm($node);
            if (null !== $stackTypesForm) {
                $stackTypesForm->handleRequest($request);
                if ($stackTypesForm->isSubmitted() && $stackTypesForm->isValid()) {
                    try {
                        $type = $this->addStackType($stackTypesForm->getData(), $node);
                        $msg = $this->getTranslator()->trans(
                            'stack_node.%name%.has_new_type.%type%',
                            [
                                '%name%' => $node->getNodeName(),
                                '%type%' => $type->getDisplayName(),
                            ]
                        );
                        $this->publishConfirmMessage($request, $msg);
                        return $this->redirect($this->generateUrl(
                            'nodesEditPage',
                            ['nodeId' => $node->getId()]
                        ));
                    } catch (EntityAlreadyExistsException $e) {
                        $stackTypesForm->addError(new FormError($e->getMessage()));
                    }
                }

                $this->assignation['stackTypesForm'] = $stackTypesForm->createView();
            }

            /*
             * Handle main form
             */
            $form = $this->createForm(Forms\NodeType::class, $node, [
                'em' => $this->get('em'),
                'nodeName' => $node->getNodeName(),
            ]);
            try {
                if ($node->getNodeType()->isReachable() && !$node->isHome()) {
                    $oldPaths = $this->get(NodeMover::class)->getNodeSourcesUrls($node);
                }
            } catch (SameNodeUrlException $e) {
                $oldPaths = [];
            }
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->get('em')->flush();
                    /*
                     * Dispatch event
                     */
                    if (isset($oldPaths) && count($oldPaths) > 0 && !$node->isHome()) {
                        $this->get('dispatcher')->dispatch(new NodePathChangedEvent($node, $oldPaths));
                    }
                    $this->get('dispatcher')->dispatch(new NodeUpdatedEvent($node));

                    $this->get('em')->flush();

                    $msg = $this->getTranslator()->trans('node.%name%.updated', [
                        '%name%' => $node->getNodeName(),
                    ]);
                    $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());
                    return $this->redirect($this->generateUrl(
                        'nodesEditPage',
                        ['nodeId' => $node->getId()]
                    ));
                } catch (EntityAlreadyExistsException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }
            $this->assignation['form'] = $form->createView();
            $this->assignation['securityAuthorizationChecker'] = $this->get("securityAuthorizationChecker");

            return $this->render('nodes/edit.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException(sprintf('Node #%s does not exist.', $nodeId));
    }

    /**
     * @param Request $request
     * @param int $nodeId
     * @param int $typeId
     * @return Response
     */
    public function removeStackTypeAction(Request $request, $nodeId, $typeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, $nodeId);
        /** @var NodeType $type */
        $type = $this->get('em')->find(NodeType::class, $typeId);

        if (null !== $node && null !== $type) {
            $node->removeStackType($type);
            $this->get('em')->flush();

            $msg = $this->getTranslator()->trans(
                'stack_type.%type%.has_been_removed.%name%',
                [
                    '%name%' => $node->getNodeName(),
                    '%type%' => $type->getDisplayName(),
                ]
            );
            $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());

            return $this->redirect($this->generateUrl('nodesEditPage', ['nodeId'=>$node->getId()]));
        }

        throw new ResourceNotFoundException(sprintf('Node #%s does not exist.', $nodeId));
    }

    /**
     * Handle node creation pages.
     *
     * @param Request $request
     * @param int     $nodeTypeId
     * @param int     $translationId
     *
     * @return Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Runtime
     */
    public function addAction(Request $request, $nodeTypeId, $translationId = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var NodeType $type */
        $type = $this->get('em')
            ->find(NodeType::class, $nodeTypeId);

        /** @var Translation $translation */
        $translation = $this->get('defaultTranslation');

        if ($translationId !== null) {
            $translation = $this->get('em')
                ->find(Translation::class, (int) $translationId);
        }

        if ($type !== null && $translation !== null) {
            $node = new Node($type);

            $chroot = $this->get(NodeChrootResolver::class)->getChroot($this->getUser());
            if (null !== $chroot) {
                // If user is jailed in a node, prevent moving nodes out.
                $node->setParent($chroot);
            }

            /** @var Form $form */
            $form = $this->createForm(AddNodeType::class, $node, [
                'nodeName' => '',
                'em' => $this->get('em'),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $node = $this->createNode($form->get('title')->getData(), $translation, $node);
                    $this->get('em')->refresh($node);
                    /*
                     * Dispatch event
                     */
                    $this->get('dispatcher')->dispatch(new NodeCreatedEvent($node));

                    $msg = $this->getTranslator()->trans(
                        'node.%name%.created',
                        ['%name%' => $node->getNodeName()]
                    );
                    $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());

                    return $this->redirect($this->generateUrl(
                        'nodesEditSourcePage',
                        [
                            'nodeId' => $node->getId(),
                            'translationId' => $translation->getId()
                        ]
                    ));
                } catch (EntityAlreadyExistsException $e) {
                    $form->addError(new FormError($e->getMessage()));
                } catch (\InvalidArgumentException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }

            $this->assignation['translation'] = $translation;
            $this->assignation['form'] = $form->createView();
            $this->assignation['type'] = $type;
            $this->assignation['nodeTypesCount'] = true;

            return $this->render('nodes/add.html.twig', $this->assignation);
        }
        throw new ResourceNotFoundException(sprintf('Node-type #%s does not exist.', $nodeTypeId));
    }

    /**
     * Handle node creation pages.
     *
     * @param Request $request
     * @param int     $nodeId
     * @param int     $translationId
     *
     * @return Response
     */
    public function addChildAction(Request $request, $nodeId = null, $translationId = null)
    {
        // include CHRoot to enable creating node in it
        $this->validateNodeAccessForRole('ROLE_ACCESS_NODES', $nodeId, true);

        $translation = $this->get('defaultTranslation');

        $nodeTypesCount = $this->get('em')
            ->getRepository(NodeType::class)
            ->countBy([]);

        if (null !== $translationId) {
            /** @var Translation $translation */
            $translation = $this->get('em')->find(Translation::class, (int) $translationId);
        }

        if ($nodeId > 0) {
            /** @var Node $parentNode */
            $parentNode = $this->get('em')
                ->find(Node::class, (int) $nodeId);
        } else {
            $parentNode = null;
        }

        if (null !== $translation) {
            $node = new Node();
            if (null !== $parentNode) {
                $node->setParent($parentNode);
            }

            /** @var Form $form */
            $form = $this->createForm(AddNodeType::class, $node, [
                'nodeName' => '',
                'em' => $this->get('em'),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $node = $this->createNode($form->get('title')->getData(), $translation, $node);
                    $this->get('em')->refresh($node);

                    /*
                     * Dispatch event
                     */
                    $this->get('dispatcher')->dispatch(new NodeCreatedEvent($node));

                    $msg = $this->getTranslator()->trans(
                        'child_node.%name%.created',
                        ['%name%' => $node->getNodeName()]
                    );
                    $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());

                    return $this->redirect($this->generateUrl(
                        'nodesEditSourcePage',
                        [
                            'nodeId' => $node->getId(),
                            'translationId' => $translation->getId()
                        ]
                    ));
                } catch (EntityAlreadyExistsException $e) {
                    $form->addError(new FormError($e->getMessage()));
                } catch (\InvalidArgumentException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }

            $this->assignation['translation'] = $translation;
            $this->assignation['form'] = $form->createView();
            $this->assignation['parentNode'] = $parentNode;
            $this->assignation['nodeTypesCount'] = $nodeTypesCount;

            return $this->render('nodes/add.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException(sprintf('Translation does not exist'));
    }

    /**
     * Return an deletion form for requested node.
     *
     * @param Request $request
     * @param int     $nodeId
     *
     * @return Response
     */
    public function deleteAction(Request $request, $nodeId)
    {
        $this->validateNodeAccessForRole('ROLE_ACCESS_NODES_DELETE', $nodeId);

        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $nodeId);

        if (null === $node) {
            throw new ResourceNotFoundException(sprintf('Node #%s does not exist.', $nodeId));
        }

        /** @var Workflow $workflow */
        $workflow = $this->get('workflow.registry')->get($node);
        if (!$workflow->can($node, 'delete')) {
            throw new BadRequestHttpException(sprintf('Node #%s cannot be deleted.', $nodeId));
        }

        $this->assignation['node'] = $node;
        $form = $this->buildDeleteForm($node);
        $form->handleRequest($request);

        if ($form->isSubmitted() &&
            $form->isValid() &&
            $form->getData()['nodeId'] == $node->getId()) {
            /** @var Node $parent */
            $parent = $node->getParent();
            /*
             * Dispatch event
             */
            $this->get('dispatcher')->dispatch(new NodeDeletedEvent($node));

            /** @var NodeHandler $nodeHandler */
            $nodeHandler = $this->get('node.handler')->setNode($node);
            $nodeHandler->softRemoveWithChildren();
            $this->get('em')->flush();

            $msg = $this->getTranslator()->trans(
                'node.%name%.deleted',
                ['%name%' => $node->getNodeName()]
            );
            $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());

            if ($request->query->has('referer')) {
                return $this->redirect($request->query->get('referer'));
            }
            if (null !== $parent) {
                return $this->redirect($this->generateUrl(
                    'nodesEditSourcePage',
                    [
                        'nodeId' => $parent->getId(),
                        'translationId' => $this->get('defaultTranslation')->getId()
                    ]
                ));
            }
            return $this->redirect($this->generateUrl('nodesHomePage'));
        }
        $this->assignation['form'] = $form->createView();
        return $this->render('nodes/delete.html.twig', $this->assignation);
    }

    /**
     * Empty trash action.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function emptyTrashAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES_DELETE');

        $form = $this->buildEmptyTrashForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $criteria = ['status' => Node::DELETED];
            /** @var Node|null $chroot */
            $chroot = $this->get(NodeChrootResolver::class)->getChroot($this->getUser());
            if ($chroot !== null) {
                /** @var NodeHandler $nodeHandler */
                $nodeHandler = $this->get('node.handler')->setNode($chroot);
                $ids = $nodeHandler->getAllOffspringId();
                $criteria["parent"] = $ids;
            }

            $nodes = $this->get('em')
                ->getRepository(Node::class)
                ->setDisplayingAllNodesStatuses(true)
                ->setDisplayingNotPublishedNodes(true)
                ->findBy($criteria);

            /** @var Node $node */
            foreach ($nodes as $node) {
                /** @var NodeHandler $nodeHandler */
                $nodeHandler = $this->get('node.handler')->setNode($node);
                $nodeHandler->removeWithChildrenAndAssociations();
            }
            /*
             * Final flush
             */
            $this->get('em')->flush();

            $msg = $this->getTranslator()->trans('node.trash.emptied');
            $this->publishConfirmMessage($request, $msg);

            return $this->redirect($this->generateUrl('nodesHomeDeletedPage'));
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('nodes/emptyTrash.html.twig', $this->assignation);
    }
    /**
     * Return an deletion form for requested node.
     *
     * @param Request $request
     * @param int     $nodeId
     *
     * @return Response
     */
    public function undeleteAction(Request $request, $nodeId)
    {
        $this->validateNodeAccessForRole('ROLE_ACCESS_NODES_DELETE', $nodeId);

        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $nodeId);

        if (null === $node) {
            throw new ResourceNotFoundException(sprintf('Node #%s does not exist.', $nodeId));
        }

        /** @var Workflow $workflow */
        $workflow = $this->get('workflow.registry')->get($node);
        if (!$workflow->can($node, 'undelete')) {
            throw new BadRequestHttpException(sprintf('Node #%s cannot be undeleted.', $nodeId));
        }

        $this->assignation['node'] = $node;
        $form = $this->createForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->get('dispatcher')->dispatch(new NodeUndeletedEvent($node));

            /** @var NodeHandler $nodeHandler */
            $nodeHandler = $this->get('node.handler')->setNode($node);
            $nodeHandler->softUnremoveWithChildren();
            $this->get('em')->flush();

            $msg = $this->getTranslator()->trans(
                'node.%name%.undeleted',
                ['%name%' => $node->getNodeName()]
            );
            $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());
            /*
             * Force redirect to avoid resending form when refreshing page
             */
            return $this->redirect($this->generateUrl('nodesEditPage', [
                'nodeId' => $node->getId(),
            ]));
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('nodes/undelete.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function generateAndAddNodeAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        try {
            /** @var UniqueNodeGenerator $generator */
            $generator = $this->get('utils.uniqueNodeGenerator');
            $source = $generator->generateFromRequest($request);
            /*
             * Dispatch event
             */
            $this->get('dispatcher')->dispatch(new NodeCreatedEvent($source->getNode()));

            return $this->redirect($this->generateUrl(
                'nodesEditSourcePage',
                ['nodeId' => $source->getNode()->getId(), 'translationId' => $source->getTranslation()->getId()]
            ));
        } catch (\Exception $e) {
            $msg = $this->getTranslator()->trans('node.noCreation.alreadyExists');
            throw new ResourceNotFoundException($msg);
        }
    }
    /**
     * @param  Request $request
     * @param  integer  $nodeId
     * @return Response
     */
    public function publishAllAction(Request $request, $nodeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES_STATUS');
        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $nodeId);

        if (null === $node) {
            throw new ResourceNotFoundException(sprintf('Node #%s does not exist.', $nodeId));
        }

        /** @var Workflow $workflow */
        $workflow = $this->get('workflow.registry')->get($node);
        if (!$workflow->can($node, 'publish')) {
            throw new BadRequestHttpException(sprintf('Node #%s cannot be publish.', $nodeId));
        }

        $form = $this->createForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var NodeHandler $nodeHandler */
            $nodeHandler = $this->get('node.handler')->setNode($node);
            $nodeHandler->publishWithChildren();
            $this->get('em')->flush();

            $msg = $this->getTranslator()->trans('node.offspring.published');
            $this->publishConfirmMessage($request, $msg);

            return $this->redirect($this->generateUrl('nodesEditSourcePage', [
                'nodeId' => $nodeId,
                'translationId' => $node->getNodeSources()->first()->getTranslation()->getId(),
            ]));
        }

        $this->assignation['node'] = $node;
        $this->assignation['form'] = $form->createView();

        return $this->render('nodes/publishAll.html.twig', $this->assignation);
    }
}
