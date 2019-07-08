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
 * @file CustomFormFieldType.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Forms;

use Doctrine\Common\Persistence\ObjectManager;
use RZ\Roadiz\CMS\Forms\Constraints\UniqueCustomFormFieldName;
use RZ\Roadiz\CMS\Forms\MarkdownType;
use RZ\Roadiz\Core\Entities\CustomForm;
use RZ\Roadiz\Core\Entities\CustomFormField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 *
 */
class CustomFormFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label', TextType::class, [
                'label' => 'label',
                'constraints' => [
                    new NotBlank(),
                    new UniqueCustomFormFieldName([
                        'entityManager' => $options['em'],
                        'currentValue' => $options['fieldName'],
                        'customForm' => $options['customForm'],
                    ]),
                ],
            ])
            ->add('description', MarkdownType::class, [
                'label' => 'description',
                'required' => false,
            ])
            ->add('placeholder', TextType::class, [
                'label' => 'placeholder',
                'required' => false,
                'help' => 'label_for_field_with_empty_data',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'type',
                'required' => true,
                'choices' => array_flip(CustomFormField::$typeToHuman),
                'choices_as_values' => true,
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'required',
                'required' => false,
                'help' => 'make_this_field_mandatory_for_users',
            ])
            ->add('expanded', CheckboxType::class, [
                'label' => 'expanded',
                'help' => 'use_checkboxes_or_radio_buttons_instead_of_select_box',
                'required' => false,
            ])
            ->add(
                'defaultValues',
                TextType::class,
                [
                    'label' => 'defaultValues',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'enter_values_comma_separated',
                    ],
                ]
            )
            ->add('groupName', TextType::class, [
                'label' => 'groupName',
                'required' => false,
                'help' => 'use_the_same_group_names_over_fields_to_gather_them_in_tabs',
            ]);
    }

    public function getBlockPrefix()
    {
        return 'customformfield';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => false,
            'fieldName' => '',
            'customForm' => null,
            'data_class' => CustomFormField::class,
            'attr' => [
                'class' => 'uk-form custom-form-field-form',
            ],
        ]);

        $resolver->setRequired([
            'customForm',
            'em',
        ]);

        $resolver->setAllowedTypes('em', ObjectManager::class);
        $resolver->setAllowedTypes('fieldName', 'string');
        $resolver->setAllowedTypes('customForm', CustomForm::class);
    }
}
