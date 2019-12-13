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
 * @file TranslationType.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Forms;

use Doctrine\Persistence\ObjectManager;
use RZ\Roadiz\CMS\Forms\Constraints\UniqueTranslationLocale;
use RZ\Roadiz\CMS\Forms\Constraints\UniqueTranslationOverrideLocale;
use RZ\Roadiz\Core\Entities\Translation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * TranslationType.
 */
class TranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'label' => 'name',
            'constraints' => [
                new NotBlank(),
                new Length([
                    'max' => 255,
                ])
            ],
        ])
        ->add('locale', ChoiceType::class, [
            'label' => 'locale',
            'required' => true,
            'choices' => array_flip(Translation::$availableLocales),
            'constraints' => [
                new UniqueTranslationLocale([
                    'entityManager' => $options['em'],
                    'currentValue' => $options['locale'],
                ]),
            ],
        ])
        ->add('available', CheckboxType::class, [
            'label' => 'available',
            'required' => false,
        ])
        ->add('overrideLocale', TextType::class, [
            'label' => 'overrideLocale',
            'required' => false,
            'constraints' => [
                new UniqueTranslationOverrideLocale([
                    'entityManager' => $options['em'],
                    'currentValue' => $options['overrideLocale'],
                ]),
                new Length([
                    'max' => 7,
                ])
            ],
        ]);
    }

    public function getBlockPrefix()
    {
        return 'translation';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => false,
            'locale' => '',
            'overrideLocale' => '',
            'data_class' => Translation::class,
            'attr' => [
                'class' => 'uk-form translation-form',
            ],
        ]);

        $resolver->setRequired([
            'em',
        ]);

        $resolver->setAllowedTypes('em', ObjectManager::class);
        $resolver->setAllowedTypes('locale', ['string']);
        $resolver->setAllowedTypes('overrideLocale', ['string', 'null']);
    }
}
