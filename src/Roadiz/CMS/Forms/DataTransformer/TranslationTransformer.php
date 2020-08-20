<?php
declare(strict_types=1);

namespace RZ\Roadiz\CMS\Forms\DataTransformer;

use Doctrine\Persistence\ObjectManager;
use RZ\Roadiz\Core\Entities\Translation;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class TranslationTransformer implements DataTransformerInterface
{
    private $manager;

    /**
     * NodeTypeTransformer constructor.
     * @param ObjectManager $manager
     */
    public function __construct(ObjectManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @param Translation|null $translation
     * @return int|string
     */
    public function transform($translation)
    {
        if (null === $translation) {
            return '';
        }
        return $translation->getId();
    }

    /**
     * @param mixed $translationId
     * @return null|Translation
     */
    public function reverseTransform($translationId)
    {
        if (!$translationId) {
            return null;
        }

        $translation = $this->manager
            ->getRepository(Translation::class)
            ->find($translationId)
        ;

        if (null === $translation) {
            // causes a validation error
            // this message is not shown to the user
            // see the invalid_message option
            throw new TransformationFailedException(sprintf(
                'A translation with id "%s" does not exist!',
                $translationId
            ));
        }

        return $translation;
    }
}
