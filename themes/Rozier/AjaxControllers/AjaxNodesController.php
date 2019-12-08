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
 * @file AjaxNodesController.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\AjaxControllers;

use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\Tag;
use RZ\Roadiz\Core\Events\Node\NodeCreatedEvent;
use RZ\Roadiz\Core\Events\Node\NodeDuplicatedEvent;
use RZ\Roadiz\Core\Events\Node\NodePathChangedEvent;
use RZ\Roadiz\Core\Events\Node\NodeStatusChangedEvent;
use RZ\Roadiz\Core\Events\Node\NodeUpdatedEvent;
use RZ\Roadiz\Core\Events\Node\NodeVisibilityChangedEvent;
use RZ\Roadiz\Core\Events\NodesSources\NodesSourcesUpdatedEvent;
use RZ\Roadiz\Utils\Node\NodeDuplicator;
use RZ\Roadiz\Utils\Node\NodeMover;
use RZ\Roadiz\Utils\Node\UniqueNodeGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Workflow\Workflow;

/**
 * Class AjaxNodesController
 * @package Themes\Rozier\AjaxControllers
 */
class AjaxNodesController extends AbstractAjaxController
{
    /**
     *
     * @param  Request $request [description]
     * @param  int  $nodeId  [description]
     * @return JsonResponse
     */
    public function getTagsAction(Request $request, $nodeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');
        $tags = [];
        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $nodeId);

        /** @var Tag $tag */
        foreach ($node->getTags() as $tag) {
            $tags[] = $tag->getFullPath();
        }

        return new JsonResponse(
            $tags
        );
    }

    /**
     * Handle AJAX edition requests for Node
     * such as comming from nodetree widgets.
     *
     * @param Request $request
     * @param int     $nodeId
     *
     * @return Response JSON response
     */
    public function editAction(Request $request, $nodeId)
    {
        /*
         * Validate
         */
        $this->validateRequest($request);
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $nodeId);

        if ($node !== null) {
            $responseArray = null;

            /*
             * Get the right update method against "_action" parameter
             */
            switch ($request->get('_action')) {
                case 'updatePosition':
                    $responseArray = $this->updatePosition($request->request->all(), $node);
                    break;
                case 'duplicate':
                    $duplicator = new NodeDuplicator($node, $this->get('em'));
                    $newNode = $duplicator->duplicate();
                    /*
                     * Dispatch event
                     */
                    $this->get('dispatcher')->dispatch(new NodeCreatedEvent($newNode));
                    $this->get('dispatcher')->dispatch(new NodeDuplicatedEvent($newNode));

                    $msg = $this->getTranslator()->trans('duplicated.node.%name%', [
                        '%name%' => $node->getNodeName(),
                    ]);
                    $this->get('logger')->info($msg, ['source' => $newNode->getNodeSources()->first()]);

                    $responseArray = [
                        'statusCode' => '200',
                        'status' => 'success',
                        'responseText' => $msg,
                    ];
                    break;
            }

            if ($responseArray === null) {
                $responseArray = [
                    'statusCode' => '200',
                    'status' => 'success',
                    'responseText' => $this->getTranslator()->trans('node.%name%.updated', [
                        '%name%' => $node->getNodeName(),
                    ]),
                ];
            }

            return new JsonResponse(
                $responseArray,
                Response::HTTP_PARTIAL_CONTENT
            );
        }

        throw $this->createNotFoundException($this->getTranslator()->trans('node.%nodeId%.not_exists', [
            '%nodeId%' => $nodeId,
        ]));
    }

    /**
     * @param array $parameters
     * @param Node  $node
     */
    protected function updatePosition($parameters, Node $node)
    {
        if ($node->isLocked()) {
            throw new BadRequestHttpException('Locked node cannot be moved.');
        }
        /*
         * First, we set the new parent
         */
        $parent = $this->parseParentNode($parameters, $node->getParent());
        /*
         * Then compute new position
         */
        $position = $this->parsePosition($parameters, $node->getPosition());

        /** @var NodeMover $nodeMover */
        $nodeMover = $this->get(NodeMover::class);
        if ($node->getNodeType()->isReachable()) {
            $oldPaths = $nodeMover->getNodeSourcesUrls($node);
        }

        $nodeMover->move($node, $parent, $position);
        $this->get('em')->flush();
        /*
         * Dispatch event
         */
        if (isset($oldPaths) && count($oldPaths) > 0) {
            $this->get('dispatcher')->dispatch(new NodePathChangedEvent($node, $oldPaths));
        }
        $this->get('dispatcher')->dispatch(new NodeUpdatedEvent($node));

        foreach ($node->getNodeSources() as $nodeSource) {
            $this->get('dispatcher')->dispatch(new NodesSourcesUpdatedEvent($nodeSource));
        }

        $this->get('em')->flush();
    }

    /**
     * @param array $parameters
     * @param Node $default
     *
     * @return Node|null
     */
    protected function parseParentNode(array $parameters, ?Node $default): ?Node
    {
        if (!empty($parameters['newParent']) && $parameters['newParent'] > 0) {
            /** @var Node|null $parent */
            return $this->get('em')->find(Node::class, (int) $parameters['newParent']);
        } elseif (null !== $this->getUser() && null !== $this->getUser()->getChroot()) {
            // If user is jailed in a node, prevent moving nodes out.
            return $this->getUser()->getChroot();
        }
        return null;
    }

    /**
     * @param array $parameters
     * @param float $default
     *
     * @return float
     */
    protected function parsePosition(array $parameters, float $default = 0): float
    {
        if (key_exists('nextNodeId', $parameters) && (int) $parameters['nextNodeId'] > 0) {
            /** @var Node $nextNode */
            $nextNode = $this->get('em')->find(Node::class, (int) $parameters['nextNodeId']);
            if ($nextNode !== null) {
                return $nextNode->getPosition() - 0.5;
            }
        } elseif (key_exists('prevNodeId', $parameters) && $parameters['prevNodeId'] > 0) {
            /** @var Node $prevNode */
            $prevNode = $this->get('em')->find(Node::class, (int) $parameters['prevNodeId']);
            if ($prevNode !== null) {
                return $prevNode->getPosition() + 0.5;
            }
        } elseif (key_exists('firstPosition', $parameters) && (boolean) $parameters['firstPosition'] === true) {
            return -0.5;
        } elseif (key_exists('lastPosition', $parameters) && (boolean) $parameters['lastPosition'] === true) {
            return 99999999;
        }
        return $default;
    }

    /**
     * Update node's status.
     *
     * @param Request $request
     *
     * @return Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function statusesAction(Request $request)
    {
        $this->validateRequest($request);
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        if ($request->get('nodeId', 0) <= 0) {
            throw new BadRequestHttpException($this->getTranslator()->trans('node.id.not_specified'));
        }

        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $request->get('nodeId'));
        if (null === $node) {
            throw $this->createNotFoundException($this->getTranslator()->trans('node.%nodeId%.not_exists', [
                '%nodeId%' => $request->get('nodeId'),
            ]));
        }

        $availableStatuses = [
            'visible' => 'setVisible',
            'locked' => 'setLocked',
            'hideChildren' => 'setHidingChildren',
            'sterile' => 'setSterile',
        ];

        if ("nodeChangeStatus" == $request->get('_action') && "" != $request->get('statusName')) {
            if ($request->get('statusName') === 'status') {
                return $this->changeNodeStatus($node, $request->get('statusValue'));
            }

            /*
             * Check if status name is a valid boolean node field.
             */
            if (in_array($request->get('statusName'), array_keys($availableStatuses))) {
                $setter = $availableStatuses[$request->get('statusName')];
                $value = $request->get('statusValue');
                $node->$setter($value);

                /*
                 * If set locked to true,
                 * need to disable dynamic nodeName
                 */
                if ($request->get('statusName') == 'locked' && $value === true) {
                    $node->setDynamicNodeName(false);
                }

                $this->em()->flush();

                /*
                 * Dispatch event
                 */

                if ($request->get('statusName') === 'visible') {
                    $msg = $this->getTranslator()->trans('node.%name%.visibility_changed_to.%visible%', [
                        '%name%' => $node->getNodeName(),
                        '%visible%' => $node->isVisible() ? $this->getTranslator()->trans('visible') : $this->getTranslator()->trans('invisible'),
                    ]);
                    $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());
                    $this->get('dispatcher')->dispatch(new NodeVisibilityChangedEvent($node));
                } else {
                    $msg = $this->getTranslator()->trans('node.%name%.%field%.updated', [
                        '%name%' => $node->getNodeName(),
                        '%field%' => $request->get('statusName'),
                    ]);
                    $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());
                }
                $this->get('dispatcher')->dispatch(new NodeUpdatedEvent($node));

                $responseArray = [
                    'statusCode' => Response::HTTP_PARTIAL_CONTENT,
                    'status' => 'success',
                    'responseText' => $msg,
                    'name' => $request->get('statusName'),
                    'value' => $value,
                ];
            } else {
                throw new BadRequestHttpException($this->getTranslator()->trans('node.has_no.field.%field%', [
                    '%field%' => $request->get('statusName'),
                ]));
            }
        } else {
            throw new BadRequestHttpException('Status field name is invalid.');
        }

        return new JsonResponse(
            $responseArray,
            $responseArray['statusCode']
        );
    }

    /**
     * @param Node   $node
     * @param string $transition
     *
     * @return JsonResponse
     */
    protected function changeNodeStatus(Node $node, string $transition)
    {
        /** @var Request $request */
        $request = $this->get('requestStack')->getMasterRequest();
        /** @var Workflow $workflow */
        $workflow = $this->get('workflow.registry')->get($node);

        $workflow->apply($node, $transition);
        $this->em()->flush();
        $msg = $this->getTranslator()->trans('node.%name%.status_changed_to.%status%', [
            '%name%' => $node->getNodeName(),
            '%status%' => $this->getTranslator()->trans(Node::getStatusLabel($node->getStatus())),
        ]);
        $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());
        $this->get('dispatcher')->dispatch(new NodeUpdatedEvent($node));
        $this->get('dispatcher')->dispatch(new NodeStatusChangedEvent($node));

        return new JsonResponse(
            [
                'statusCode' => Response::HTTP_PARTIAL_CONTENT,
                'status' => 'success',
                'responseText' => $msg,
                'name' => 'status',
                'value' => $transition,
            ],
            Response::HTTP_PARTIAL_CONTENT
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function quickAddAction(Request $request)
    {
        /*
         * Validate
         */
        $this->validateRequest($request);
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        try {
            /** @var UniqueNodeGenerator $generator */
            $generator = $this->get('utils.uniqueNodeGenerator');
            $source = $generator->generateFromRequest($request);

            /*
             * Dispatch event
             */
            $this->get('dispatcher')->dispatch(new NodeCreatedEvent($source->getNode()));

            $msg = $this->getTranslator()->trans(
                'added.node.%name%',
                [
                    '%name%' => $source->getTitle(),
                ]
            );
            $this->publishConfirmMessage($request, $msg, $source);

            $responseArray = [
                'statusCode' => Response::HTTP_CREATED,
                'status' => 'success',
                'responseText' => $msg,
            ];
        } catch (\Exception $e) {
            $msg = $this->getTranslator()->trans($e->getMessage());
            $this->get('logger')->error($msg);
            throw new BadRequestHttpException($msg);
        }

        return new JsonResponse(
            $responseArray,
            $responseArray['statusCode']
        );
    }
}
