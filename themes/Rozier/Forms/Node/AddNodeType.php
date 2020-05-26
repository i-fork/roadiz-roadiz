<?php
declare(strict_types=1);

namespace Themes\Rozier\Forms\Node;

use Doctrine\Persistence\ObjectManager;
use RZ\Roadiz\CMS\Forms\NodeTypesType;
use RZ\Roadiz\CMS\Forms\DataTransformer\NodeTypeTransformer;
use RZ\Roadiz\Core\Entities\Node;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Add node form type.
 *
 * @package Themes\Rozier\Forms\Node
 */
class AddNodeType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', TextType::class, [
            'label' => 'title',
            'mapped' => false,
            'constraints' => [
                new NotBlank(),
                new Length([
                    'max' => 255
                ])
            ],
        ]);

        if ($options['showNodeType'] === true) {
            $builder->add('nodeType', NodeTypesType::class, [
                'label' => 'nodeType',
                'entityManager' => $options['em'],
                'constraints' => [
                    new NotBlank(),
                ],
            ]);
            $builder->get('nodeType')->addModelTransformer(new NodeTypeTransformer($options['em']));
        }

        $builder->add('dynamicNodeName', CheckboxType::class, [
            'label' => 'node.dynamicNodeName',
            'required' => false,
            'help' => 'dynamic_node_name_will_follow_any_title_change_on_default_translation',
        ])
        ->add('visible', CheckboxType::class, [
            'label' => 'visible',
            'required' => false,
        ])
        ->add('locked', CheckboxType::class, [
            'label' => 'locked',
            'required' => false,
        ])
        ->add('hideChildren', CheckboxType::class, [
            'label' => 'hiding-children',
            'required' => false,
        ])
        ->add('status', ChoiceType::class, [
            'label' => 'node.status',
            'required' => true,
            'choices' => [
                Node::getStatusLabel(Node::DRAFT) => Node::DRAFT,
                Node::getStatusLabel(Node::PENDING) => Node::PENDING,
                Node::getStatusLabel(Node::PUBLISHED) => Node::PUBLISHED,
                Node::getStatusLabel(Node::ARCHIVED) => Node::ARCHIVED,
            ],
        ])
        ;
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'childnode';
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => false,
            'nodeName' => '',
            'showNodeType' => true,
            'attr' => [
                'class' => 'uk-form childnode-form',
            ],
        ]);

        $resolver->setRequired([
            'em',
        ]);

        $resolver->setAllowedTypes('em', ObjectManager::class);
        $resolver->setAllowedTypes('nodeName', 'string');
        $resolver->setAllowedTypes('showNodeType', 'boolean');
    }
}
