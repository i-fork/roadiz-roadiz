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
 * @file ExportController.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Controllers\Nodes;

use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\NodesSources;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Serializers\NodeSourceXlsxSerializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Themes\Rozier\RozierApp;

/**
 * Class ExportController
 * @package Themes\Rozier\Controllers\Nodes
 */
class ExportController extends RozierApp
{
    /**
     * Export all Node in a XLSX file (Excel).
     *
     * @param Request $request
     * @param int     $translationId
     * @param int|null     $parentNodeId
     *
     * @return Response
     */
    public function exportAllXlsxAction(Request $request, $translationId, $parentNodeId = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /*
         * Get translation
         */
        $translation = $this->get('em')
            ->find(Translation::class, $translationId);

        if (null === $translation) {
            $translation = $this->get('em')
                ->getRepository(Translation::class)
                ->findDefault();
        }
        $criteria = ["translation" => $translation];
        $order = ['node.nodeType' => 'ASC'];
        $filename = 'nodes-' . date("YmdHis") . '.' . $translation->getLocale() . '.xlsx';

        if (null !== $parentNodeId) {
            /** @var Node|null $parentNode */
            $parentNode = $this->get('em')->find(Node::class, $parentNodeId);
            if (null === $parentNode) {
                throw $this->createNotFoundException();
            }
            $criteria['node.parent'] = $parentNode;
            $filename = $parentNode->getNodeName() . '-' . date("YmdHis") . '.' . $translation->getLocale() . '.xlsx';
        }

        $sources = $this->get('em')
            ->getRepository(NodesSources::class)
            ->setDisplayingAllNodesStatuses(true)
            ->setDisplayingNotPublishedNodes(true)
            ->findBy($criteria, $order);

        $serializer = new NodeSourceXlsxSerializer($this->get('em'), $this->get('translator'), $this->get('urlGenerator'));
        $serializer->setOnlyTexts(true);
        $serializer->addUrls($request, $this->get('settingsBag')->get('force_locale'));
        $xlsx = $serializer->serialize($sources);

        $response = new Response(
            $xlsx,
            Response::HTTP_OK,
            []
        );

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            )
        );

        $response->prepare($request);

        return $response;
    }
}
