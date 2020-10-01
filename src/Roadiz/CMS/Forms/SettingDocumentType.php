<?php
declare(strict_types=1);

namespace RZ\Roadiz\CMS\Forms;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Utils\Asset\Packages;
use RZ\Roadiz\Utils\Document\AbstractDocumentFactory;
use RZ\Roadiz\Utils\Document\DocumentFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingDocumentType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($value) use ($options) {
                if (null !== $value) {
                    /** @var Packages $packages */
                    $packages = $options['assetPackages'];
                    /** @var Document|null $document */
                    $document = $options['entityManager']->find(Document::class, $value);
                    if (null !== $document) {
                        // transform the array to a string
                        return new File($packages->getDocumentFilePath($document), false);
                    }
                }
                return null;
            },
            function ($file) use ($options) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    /** @var AbstractDocumentFactory $factory */
                    $factory = $options['documentFactory'];
                    $factory->setFile($file);
                    $document = $factory->getDocument();

                    if (null !== $document && $document instanceof Document) {
                        $options['entityManager']->persist($document);
                        $options['entityManager']->flush();

                        return $document->getId();
                    }
                }
                return null;
            }
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired([
            'entityManager',
            'documentFactory',
            'assetPackages',
        ]);

        $resolver->setAllowedTypes('entityManager', [EntityManager::class]);
        $resolver->setAllowedTypes('documentFactory', [DocumentFactory::class]);
        $resolver->setAllowedTypes('assetPackages', [Packages::class]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return FileType::class;
    }
}
