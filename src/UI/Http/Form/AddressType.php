<?php

declare(strict_types=1);

namespace App\UI\Http\Form;

use App\Application\Customer\Input\AddressInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Address fields (Bootstrap form theme, jQuery live validation through the HTML attributes).
 *
 * @extends AbstractType<AddressInput>
 */
final class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $prefix = $options['autocomplete_section'];
        $builder
            ->add('firstName', TextType::class, ['label' => 'address.first_name', 'empty_data' => '', 'attr' => ['autocomplete' => $prefix.' given-name']])
            ->add('lastName', TextType::class, ['label' => 'address.last_name', 'empty_data' => '', 'attr' => ['autocomplete' => $prefix.' family-name']])
            ->add('company', TextType::class, ['label' => 'address.company', 'required' => false, 'attr' => ['autocomplete' => $prefix.' organization']])
            ->add('vatId', TextType::class, ['label' => 'address.vat_id', 'required' => false, 'help' => 'address.vat_id_help'])
            ->add('street', TextType::class, ['label' => 'address.street', 'empty_data' => '', 'attr' => ['autocomplete' => $prefix.' address-line1']])
            ->add('houseNumber', TextType::class, ['label' => 'address.house_number', 'empty_data' => ''])
            ->add('postcode', TextType::class, ['label' => 'address.postcode', 'empty_data' => '', 'attr' => ['autocomplete' => $prefix.' postal-code']])
            ->add('city', TextType::class, ['label' => 'address.city', 'empty_data' => '', 'attr' => ['autocomplete' => $prefix.' address-level2']])
            ->add('countryCode', ChoiceType::class, ['label' => 'address.country', 'empty_data' => 'NL', 'choices' => array_flip($options['countries']), 'choice_translation_domain' => false])
            ->add('phone', TelType::class, ['label' => 'address.phone', 'required' => false, 'attr' => ['autocomplete' => $prefix.' tel']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AddressInput::class, 'countries' => ['NL' => 'Netherlands'], 'autocomplete_section' => 'billing']);
        $resolver->setAllowedTypes('countries', 'array');
    }
}
