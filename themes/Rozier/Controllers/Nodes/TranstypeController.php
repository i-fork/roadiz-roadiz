<?php
/**
 * Copyright (c) 2016. Ambroise Maupate and Julien Blanchet
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
 * @file TranstypeController.php
 * @author ambroisemaupate
 */
namespace Themes\Rozier\Controllers\Nodes;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\NodesSources;
use RZ\Roadiz\Core\Entities\NodesSourcesDocuments;
use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Core\Entities\NodeTypeField;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Entities\UrlAlias;
use RZ\Roadiz\Core\Events\FilterNodeEvent;
use RZ\Roadiz\Core\Events\NodeEvents;
use RZ\Roadiz\Core\Repositories\NodeTypeFieldRepository;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\TranstypeType;
use Themes\Rozier\RozierApp;

/**
 * Class TranstypeController
 * @package Themes\Rozier\Controllers\Nodes
 */
class TranstypeController extends RozierApp
{
    /**
     * @param Request $request
     * @param int $nodeId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Twig_Error_Runtime
     */
    public function transtypeAction(Request $request, $nodeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var Node $node */
        $node = $this->get('em')->find(Node::class, (int) $nodeId);
        $this->get('em')->refresh($node);

        if (null === $node) {
            throw new ResourceNotFoundException();
        }

        /** @var Form $form */
        $form = $this->createForm(TranstypeType::class, null, [
            'em' => $this->get('em'),
            'currentType' => $node->getNodeType(),
        ]);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();

            /** @var NodeType $newNodeType */
            $newNodeType = $this->get('em')->find(NodeType::class, (int) $data['nodeTypeId']);

            $this->doTranstype($node, $newNodeType);
            $this->get('em')->refresh($node);

            /*
             * Dispatch event
             */
            $event = new FilterNodeEvent($node);
            $this->get('dispatcher')->dispatch(NodeEvents::NODE_UPDATED, $event);

            $msg = $this->getTranslator()->trans('%node%.transtyped_to.%type%', [
                '%node%' => $node->getNodeName(),
                '%type%' => $newNodeType->getName(),
            ]);
            $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());

            return $this->redirect($this->generateUrl(
                'nodesEditSourcePage',
                [
                    'nodeId' => $node->getId(),
                    'translationId' => $node->getNodeSources()->first()->getTranslation()->getId(),
                ]
            ));
        }

        $this->assignation['form'] = $form->createView();
        $this->assignation['node'] = $node;
        $this->assignation['parentNode'] = $node->getParent();
        $this->assignation['type'] = $node->getNodeType();

        return $this->render('nodes/transtype.html.twig', $this->assignation);
    }

    /**
     * @param Node $node
     * @param NodeType $nodeType
     */
    protected function doTranstype(Node $node, NodeType $nodeType)
    {
        /*
         * Get an association between old fields and new fields
         * to find data that can be transferred during trans-typing.
         */
        $fieldAssociations = [];
        $oldFields = $node->getNodeType()->getFields();
        /** @var NodeTypeFieldRepository $er */
        $er = $this->get('em')->getRepository(NodeTypeField::class);

        foreach ($oldFields as $oldField) {
            $matchingField = $er->findOneBy([
                'nodeType' => $nodeType,
                'name' => $oldField->getName(),
                'type' => $oldField->getType(),
            ]);

            if (null !== $matchingField) {
                $fieldAssociations[] = [
                    $oldField, // old type field
                    $matchingField, // new type field
                ];
            }
        }

        $sourceClass = NodeType::getGeneratedEntitiesNamespace() . "\\" . $nodeType->getSourceEntityClassName();

        /*
         * Testing if new nodeSource class is available
         * and cache have been cleared before actually performing
         * trans-type, not to get an orphan node.
         */
        $this->mockTranstype($nodeType);

        /*
         * Perform actual trans-typing
         */
        /** @var NodesSources $existingSource */
        foreach ($node->getNodeSources() as $existingSource) {
            $this->doTranstypeSingleSource($node, $existingSource, $sourceClass, $fieldAssociations);
        }

        $node->setNodeType($nodeType);
        $this->get('em')->flush();
    }

    /**
     * @param Node         $node
     * @param NodesSources $existingSource
     * @param string       $sourceClass
     * @param array        $fieldAssociations
     *
     * @return NodesSources
     */
    protected function doTranstypeSingleSource(
        Node $node,
        NodesSources $existingSource,
        string $sourceClass,
        array $fieldAssociations
    ): NodesSources {
        // First plan old source deletion.
        $node->removeNodeSources($existingSource);
        $this->get('em')->remove($existingSource);
        // Need to flush before creating new sources
        // to avoid unique constraints
        $this->get('em')->flush();

        /** @var NodesSources $source */
        $source = new $sourceClass($node, $existingSource->getTranslation());
        $this->get('em')->persist($source);
        $source->setTitle($existingSource->getTitle());

        foreach ($fieldAssociations as $fields) {
            /** @var NodeTypeField $oldField */
            $oldField = $fields[0];
            /** @var NodeTypeField $matchingField */
            $matchingField = $fields[1];

            if (!$oldField->isVirtual()) {
                /*
                 * Copy simple data from source to another
                 */
                $setter = $oldField->getSetterName();
                $getter = $oldField->getGetterName();
                $source->$setter($existingSource->$getter());
            } elseif ($oldField->getType() === NodeTypeField::DOCUMENTS_T) {
                /*
                 * Copy documents.
                 */
                $documents = $existingSource->getDocumentsByFieldsWithName($oldField->getName());
                foreach ($documents as $document) {
                    $nsDoc = new NodesSourcesDocuments($source, $document, $matchingField);
                    $this->get('em')->persist($nsDoc);
                    $source->getDocumentsByFields()->add($nsDoc);
                }
            }
        }

        /*
         * Recreate url-aliases too.
         */
        /** @var UrlAlias $urlAlias */
        foreach ($existingSource->getUrlAliases() as $urlAlias) {
            $newUrlAlias = new UrlAlias($source);
            $newUrlAlias->setAlias($urlAlias->getAlias());
            $source->addUrlAlias($newUrlAlias);
            $this->get('em')->persist($newUrlAlias);
        }

        $this->get('em')->flush();
        return $source;
    }

    /**
     * @param NodeType $nodeType
     */
    protected function mockTranstype(NodeType $nodeType)
    {
        $sourceClass = NodeType::getGeneratedEntitiesNamespace() . "\\" . $nodeType->getSourceEntityClassName();
        $uniqueId = uniqid();
        /*
         * Testing if new nodeSource class is available
         * and cache have been cleared before actually performing
         * transtype, not to get an orphan node.
         */
        $node = new Node();
        $node->setNodeName('testing_before_transtype' . $uniqueId);
        $this->get('em')->persist($node);

        $translation = new Translation();
        $translation->setAvailable(true);
        $translation->setLocale(substr($uniqueId, 0, 10));
        $translation->setName('test' . $uniqueId);
        $this->get('em')->persist($translation);

        /** @var NodesSources $testSource */
        $testSource = new $sourceClass($node, $translation);
        $testSource->setTitle('testing_before_transtype' . $uniqueId);
        $this->get('em')->persist($testSource);
        $this->get('em')->flush();

        // then remove it if OK
        $this->get('em')->remove($testSource);
        $this->get('em')->remove($node);
        $this->get('em')->remove($translation);
        $this->get('em')->flush();
    }
}
