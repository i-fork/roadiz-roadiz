<?php
/**
 * Copyright © 2014, REZO ZERO
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
 * Except as contained in this notice, the name of the REZO ZERO shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from the REZO ZERO SARL.
 *
 * @file NodesTrait.php
 * @copyright REZO ZERO 2014
 * @author Maxime Constantinian
 */
namespace Themes\Rozier\Traits;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\CMS\Forms\Constraints\UniqueNodeName;
use RZ\Roadiz\CMS\Forms\NodeTypesType;
use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Utils\Node\NodeFactory;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

trait NodesTrait
{
    /**
     * @param string $title
     * @param Translation $translation
     * @param Node|null $node
     * @param NodeType|null $type
     * @return Node
     */
    protected function createNode($title, Translation $translation, Node $node = null, NodeType $type = null)
    {
        /** @var NodeFactory $factory */
        $factory = $this->get(NodeFactory::class);
        $node = $factory->create($title, $type, $translation, $node);

        /** @var EntityManager $entityManager */
        $entityManager = $this->get('em');
        $entityManager->flush();

        return $node;
    }

    /**
     * @param array $data
     * @param Node  $node
     *
     * @return NodeType|null
     */
    public function addStackType($data, Node $node)
    {
        if ($data['nodeId'] == $node->getId() &&
            !empty($data['nodeTypeId'])) {
            $nodeType = $this->get('em')->find(NodeType::class, (int) $data['nodeTypeId']);

            if (null !== $nodeType) {
                $node->addStackType($nodeType);
                $this->get('em')->flush();

                return $nodeType;
            }
        }

        return null;
    }

    /**
     * @param Node $node
     *
     * @return \Symfony\Component\Form\Form
     */
    public function buildStackTypesForm(Node $node)
    {
        if ($node->isHidingChildren()) {
            $defaults = [];
            $builder = $this->createNamedFormBuilder('add_stack_type', $defaults)
                            ->add('nodeId', HiddenType::class, [
                                'data' => (int) $node->getId(),
                            ])
                            ->add('nodeTypeId', NodeTypesType::class, [
                                'entityManager' => $this->get('em'),
                                'label' => false,
                                'constraints' => [
                                    new NotBlank(),
                                ],
                            ]);

            return $builder->getForm();
        } else {
            return null;
        }
    }

    /**
     * @param Node $parentNode
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildAddChildForm(Node $parentNode = null)
    {
        $defaults = [];

        $builder = $this->createFormBuilder($defaults)
                        ->add('nodeName', TextType::class, [
                            'label' => 'nodeName',
                            'constraints' => [
                                new NotBlank(),
                                new UniqueNodeName([
                                    'entityManager' => $this->get('em'),
                                ]),
                            ],
                        ])
            ->add('nodeTypeId', NodeTypesType::class, [
                'label' => 'nodeType',
                'entityManager' => $this->get('em'),
                'constraints' => [
                    new NotBlank(),
                ],
            ]);

        if (null !== $parentNode) {
            $builder->add('parentId', HiddenType::class, [
                'data' => (int) $parentNode->getId(),
                'constraints' => [
                    new NotBlank(),
                ],
            ]);
        }

        return $builder->getForm();
    }

    /**
     * @param Node $node
     *
     * @return FormInterface
     */
    protected function buildDeleteForm(Node $node)
    {
        $builder = $this->createNamedFormBuilder('remove_stack_type_'.$node->getId())
                        ->add('nodeId', HiddenType::class, [
                            'data' => $node->getId(),
                            'constraints' => [
                                new NotBlank(),
                            ],
                        ]);

        return $builder->getForm();
    }

    /**
     * @return \Symfony\Component\Form\Form
     */
    protected function buildEmptyTrashForm()
    {
        $builder = $this->createFormBuilder();
        return $builder->getForm();
    }
}
