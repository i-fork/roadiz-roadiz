<?php
declare(strict_types=1);

namespace RZ\Roadiz\Attribute\Form;

use Doctrine\ORM\EntityManagerInterface;
use RZ\Roadiz\CMS\Forms\Constraints\UniqueEntity;
use RZ\Roadiz\CMS\Forms\DataTransformer\TranslationTransformer;
use RZ\Roadiz\CMS\Forms\TranslationsType;
use RZ\Roadiz\Core\Entities\AttributeTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class AttributeTranslationType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label', TextType::class, [
                'empty_data' => "",
                'label' => false,
                'required' => false,
            ])
            ->add('translation', TranslationsType::class, [
                'label' => false,
                'required' => true,
                'entityManager' => $options['entityManager'],
                'constraints' => [
                    new NotNull()
                ]
            ])
            ->add('options', CollectionType::class, [
                'label' => 'attributes.form.options',
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'entry_options' => [
                    'required' => false,
                ],
                'attr' => [
                    'class' => 'rz-collection-form-type'
                ],
            ])
        ;

        $builder->get('translation')->addModelTransformer(new TranslationTransformer($options['entityManager']));
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('data_class', AttributeTranslation::class);
        $resolver->setRequired('entityManager');
        $resolver->setAllowedTypes('entityManager', [EntityManagerInterface::class]);

        $resolver->setNormalizer('constraints', function (Options $options) {
            return [
                new UniqueEntity([
                    'fields' => ['attribute', 'translation'],
                    'entityManager' => $options['entityManager'],
                ])
            ];
        });
    }

    /**
     * @inheritDoc
     */
    public function getBlockPrefix()
    {
        return 'attribute_translation';
    }
}
