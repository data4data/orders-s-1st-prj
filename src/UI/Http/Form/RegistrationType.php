<?php

declare(strict_types=1);

namespace App\UI\Http\Form;

use App\Application\Customer\Input\RegisterCustomerInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Customer registration (Twig + Bootstrap, decision #36): the delivery address fields only count
 * when "delivery same as billing" is unticked.
 *
 * @extends AbstractType<RegisterCustomerInput>
 */
final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'register.email', 'empty_data' => '', 'attr' => ['autocomplete' => 'email']])
            ->add('password', PasswordType::class, ['label' => 'register.password', 'empty_data' => '', 'help' => 'register.password_help', 'attr' => ['autocomplete' => 'new-password', 'minlength' => 8]])
            ->add('firstName', TextType::class, ['label' => 'address.first_name', 'empty_data' => '', 'attr' => ['autocomplete' => 'given-name']])
            ->add('lastName', TextType::class, ['label' => 'address.last_name', 'empty_data' => '', 'attr' => ['autocomplete' => 'family-name']])
            ->add('phone', TelType::class, ['label' => 'address.phone', 'required' => false, 'attr' => ['autocomplete' => 'tel']])
            ->add('billing', AddressType::class, ['label' => false, 'countries' => $options['countries'], 'autocomplete_section' => 'section-billing billing'])
            ->add('deliverySameAsBilling', CheckboxType::class, ['label' => 'register.delivery_same', 'required' => false])
            ->add('delivery', AddressType::class, ['label' => false, 'countries' => $options['countries'], 'autocomplete_section' => 'section-delivery shipping'])
            ->add('acceptTerms', CheckboxType::class, ['label' => 'register.accept_terms', 'required' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegisterCustomerInput::class,
            'countries' => ['NL' => 'Netherlands'],
            'csrf_token_id' => 'register',
        ]);
        $resolver->setAllowedTypes('countries', 'array');
    }
}
