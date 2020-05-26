<?php
declare(strict_types=1);

namespace RZ\Roadiz\CMS\Forms;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Users selector form field type.
 */
class UsersType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'users' => new ArrayCollection(),
        ]);
        $resolver->setRequired('entityManager');
        $resolver->setAllowedTypes('entityManager', [EntityManager::class]);
        $resolver->setAllowedTypes('users', [Collection::class]);

        /*
         * Use normalizer to populate choices from ChoiceType
         */
        $resolver->setNormalizer('choices', function (Options $options, $choices) {
            /** @var EntityManager $entityManager */
            $entityManager = $options['entityManager'];
            $users = $entityManager->getRepository(User::class)->findAll();

            /** @var User $user */
            foreach ($users as $user) {
                if (!$options['users']->contains($user)) {
                    $choices[$user->getUserName()] = $user->getId();
                }
            }
            return $choices;
        });
    }
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
    }
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'users';
    }
}
