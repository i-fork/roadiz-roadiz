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
 * @file AjaxFoldersController.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\AjaxControllers;

use RZ\Roadiz\Core\Entities\Folder;
use RZ\Roadiz\Core\Handlers\FolderHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * {@inheritdoc}
 */
class AjaxFoldersController extends AbstractAjaxController
{
    /**
     * Handle AJAX edition requests for Folder
     * such as comming from tagtree widgets.
     *
     * @param Request $request
     * @param int     $folderId
     *
     * @return Response JSON response
     */
    public function editAction(Request $request, $folderId)
    {
        /*
         * Validate
         */
        $this->validateRequest($request);
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        $folder = $this->get('em')
            ->find(Folder::class, (int) $folderId);

        if ($folder !== null) {
            $responseArray = null;

            /*
             * Get the right update method against "_action" parameter
             */
            switch ($request->get('_action')) {
                case 'updatePosition':
                    $this->updatePosition($request->request->all(), $folder);
                    break;
            }

            if ($responseArray === null) {
                $responseArray = [
                    'statusCode' => '200',
                    'status' => 'success',
                    'responseText' => $this->getTranslator()->trans('folder.%name%.updated', [
                        '%name%' => $folder->getName(),
                    ])
                ];
            }

            return new JsonResponse(
                $responseArray,
                Response::HTTP_PARTIAL_CONTENT
            );
        }


        $responseArray = [
            'statusCode' => '403',
            'status'    => 'danger',
            'responseText' => $this->getTranslator()->trans('folder.does_not_exist')
        ];

        return new JsonResponse(
            $responseArray,
            Response::HTTP_OK
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function searchAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        if ($request->query->has('search') && $request->get('search') != "") {
            $responseArray = [];

            $pattern = strip_tags($request->get('search'));
            $folders = $this->get('em')
                        ->getRepository(Folder::class)
                        ->searchBy(
                            $pattern,
                            [],
                            [],
                            10
                        );
            /** @var Folder $folder */
            foreach ($folders as $folder) {
                $responseArray[] = $folder->getFullPath();
            }

            return new JsonResponse(
                $responseArray,
                Response::HTTP_OK
            );
        }

        throw $this->createNotFoundException($this->getTranslator()->trans('no.folder.found'));
    }

    /**
     * @param array $parameters
     * @param Folder $folder
     */
    protected function updatePosition($parameters, Folder $folder)
    {
        /*
         * First, we set the new parent
         */
        $parent = null;

        if (!empty($parameters['newParent']) &&
            $parameters['newParent'] > 0) {
            /** @var Folder $parent */
            $parent = $this->get('em')
                ->find(Folder::class, (int) $parameters['newParent']);

            if ($parent !== null) {
                $folder->setParent($parent);
            }
        } else {
            $folder->setParent(null);
        }

        /*
         * Then compute new position
         */
        if (!empty($parameters['nextFolderId']) &&
            $parameters['nextFolderId'] > 0) {
            /** @var Folder $nextFolder */
            $nextFolder = $this->get('em')
                ->find(Folder::class, (int) $parameters['nextFolderId']);
            if ($nextFolder !== null) {
                $folder->setPosition($nextFolder->getPosition() - 0.5);
            }
        } elseif (!empty($parameters['prevFolderId']) &&
            $parameters['prevFolderId'] > 0) {
            /** @var Folder $prevFolder */
            $prevFolder = $this->get('em')
                ->find(Folder::class, (int) $parameters['prevFolderId']);
            if ($prevFolder !== null) {
                $folder->setPosition($prevFolder->getPosition() + 0.5);
            }
        }
        // Apply position update before cleaning
        $this->get('em')->flush();

        /** @var FolderHandler $handler */
        $handler = $this->get('folder.handler');
        $handler->setFolder($folder);
        $handler->cleanPositions();

        $this->get('em')->flush();
    }
}
