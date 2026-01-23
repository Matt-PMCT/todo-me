<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Simple form type for quickly adding a task from the web UI.
 */
class QuickTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Task title',
                'attr' => [
                    'placeholder' => 'What needs to be done?',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a task title.',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Title cannot be longer than {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Add more details...',
                    'rows' => 3,
                ],
                'constraints' => [
                    new Length([
                        'max' => 2000,
                        'maxMessage' => 'Description cannot be longer than {{ limit }} characters.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'create_task',
        ]);
    }
}
