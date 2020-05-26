<?php
declare(strict_types=1);

namespace Themes\Rozier\Forms;

use Doctrine\ORM\EntityManagerInterface;
use RZ\Roadiz\CMS\Forms\ColorType;
use RZ\Roadiz\CMS\Forms\Constraints\UniqueFilename;
use RZ\Roadiz\CMS\Forms\DocumentCollectionType;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Utils\Asset\Packages;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class DocumentEditType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('referer', HiddenType::class, [
                'data' => $options['referer'],
                'mapped' => false,
            ])
            ->add('filename', TextType::class, [
                'label' => 'filename',
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => '/\.[a-z0-9]+$/i',
                        'htmlPattern' => ".[a-z0-9]+$",
                        'message' => 'value_is_not_a_valid_filename'
                    ]),
                    new UniqueFilename([
                        'document' => $builder->getData(),
                        'packages' => $options['assetPackages'],
                    ]),
                ],
            ])
            ->add('mimeType', TextType::class, [
                'label' => 'document.mimeType',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('private', CheckboxType::class, [
                'label' => 'private',
                'help' => 'document.private.help',
                'required' => false,
            ])
            ->add('newDocument', FileType::class, [
                'label' => 'overwrite.document',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File()
                ],
            ])
            ->add('embed', DocumentEmbedType::class, [
                'label' => 'document.embed',
                'required' => false,
                'inherit_data' => true,
                'document_platforms' => $options['document_platforms'],
            ])
            ->add('imageAverageColor', ColorType::class, [
                'label' => 'document.imageAverageColor',
                'help' => 'document.imageAverageColor.help',
                'required' => false,
            ])
        ;
        /*
         * Display thumbnails only if current Document is original.
         */
        if (null === $builder->getData()->getOriginal()) {
            $builder->add('thumbnails', DocumentCollectionType::class, [
                'label' => 'document.thumbnails',
                'multiple' => true,
                'required' => false,
                'entityManager' => $options['entityManager'],
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Document::class
        ]);

        $resolver->setRequired('assetPackages');
        $resolver->setAllowedTypes('assetPackages', [Packages::class]);

        $resolver->setRequired('referer');
        $resolver->setAllowedTypes('referer', ['null', 'string']);

        $resolver->setRequired('document_platforms');
        $resolver->setAllowedTypes('document_platforms', ['array']);

        $resolver->setRequired('entityManager');
        $resolver->setAllowedTypes('entityManager', EntityManagerInterface::class);
    }


    /**
     * @inheritDoc
     */
    public function getBlockPrefix()
    {
        return 'document_edit';
    }
}
