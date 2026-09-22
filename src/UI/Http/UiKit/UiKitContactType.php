<?php

declare(strict_types=1);

namespace App\UI\Http\UiKit;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<UiKitContactData>
 */
final class UiKitContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'ui_kit.form.name', 'empty_data' => '', 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'ui_kit.form.email', 'empty_data' => '', 'attr' => ['autocomplete' => 'email']])
            ->add('postcode', TextType::class, [
                'label' => 'ui_kit.form.postcode',
                'empty_data' => '',
                'help' => 'ui_kit.form.postcode_help',
                'attr' => ['pattern' => '\d{4}\s?[A-Za-z]{2}', 'data-msg-pattern' => 'Use the format 1234 AB.'],
            ])
            ->add('message', TextareaType::class, ['label' => 'ui_kit.form.message', 'empty_data' => '', 'attr' => ['minlength' => 10, 'rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => UiKitContactData::class]);
    }
}
