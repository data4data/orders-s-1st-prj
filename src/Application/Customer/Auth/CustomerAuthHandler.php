<?php

declare(strict_types=1);

namespace App\Application\Customer\Auth;

use App\Application\Customer\AddressWriter;
use App\Application\Customer\CustomerSessionInterface;
use App\Application\Customer\PasswordResetTokensInterface;
use App\Application\Customer\Port\CustomerRepositoryInterface;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Domain\Customer\AddressBookPolicy;
use App\Entity\Customer;
use App\Entity\CustomerAddress;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Registration and password reset for storefront customers (per store, decision #6).
 */
final readonly class CustomerAuthHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private CustomerRepositoryInterface $customers,
        private AddressWriter $addressWriter,
        private AddressBookPolicy $policy,
        private UserPasswordHasherInterface $passwordHasher,
        private CustomerSessionInterface $session,
        private PasswordResetTokensInterface $resetTokens,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function register(RegisterCustomer $command): string
    {
        $input = $command->input;
        if (null !== $this->customers->findByEmail($input->email)) {
            throw ValidationException::forField('email', 'An account with this email already exists. Log in or reset your password.');
        }

        $customer = new Customer($input->email, trim($input->firstName), trim($input->lastName));
        $phone = trim($input->phone ?? '');
        $customer->updateProfile($customer->getFirstName(), $customer->getLastName(), '' === $phone ? null : $phone);
        $customer->changePassword($this->passwordHasher->hashPassword($customer, $input->password));

        // "Delivery same as billing": one address row with both flags (decision #35).
        $input->billing->usableForBilling = true;
        $input->billing->usableForShipping = $input->deliverySameAsBilling;
        $billing = new CustomerAddress($customer, $this->addressWriter->country($input->billing->countryCode, 'billing'));
        $this->addressWriter->write($input->billing, $billing, 'billing');
        $customer->addAddress($billing);
        $delivery = $billing;
        if (!$input->deliverySameAsBilling) {
            $input->delivery->usableForBilling = false;
            $input->delivery->usableForShipping = true;
            $delivery = new CustomerAddress($customer, $this->addressWriter->country($input->delivery->countryCode, 'delivery'));
            $this->addressWriter->write($input->delivery, $delivery, 'delivery');
            $customer->addAddress($delivery);
        }

        $this->policy->assertComplete($customer->entries());
        $this->customers->save($customer);
        $customer->setDefaults($billing, $delivery, $this->policy);
        $this->customers->save($customer);
        $this->session->logIn($customer);

        return $customer->getPublicId()->toRfc4122();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function requestReset(RequestPasswordReset $command): void
    {
        $customer = $this->customers->findByEmail($command->email);
        if (null === $customer) {
            return;
        }

        $store = $this->tenantContext->requireStore();
        $url = $this->urls->generate('customer_reset_password', ['token' => $this->resetTokens->create($customer)], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->mailer->send((new TemplatedEmail())
            ->from(new Address($store->getContactEmail() ?? 'noreply@'.$store->getCode().'.test', $store->getName()))
            ->to(new Address($customer->getEmail(), trim($customer->getFirstName().' '.$customer->getLastName())))
            ->subject(sprintf('Reset your password for %s', $store->getName()))
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context(['store_name' => $store->getName(), 'first_name' => $customer->getFirstName(), 'reset_url' => $url]));
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function checkToken(CheckPasswordResetToken $query): bool
    {
        return null !== $this->resetTokens->verify($query->token);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function reset(ResetPassword $command): void
    {
        $customer = $this->resetTokens->verify($command->token)
            ?? throw ValidationException::forField('token', 'This link has expired or was already used. Request a new one.');
        $customer->changePassword($this->passwordHasher->hashPassword($customer, $command->input->password));
        $this->customers->save($customer);
        $this->session->logIn($customer);
    }
}
