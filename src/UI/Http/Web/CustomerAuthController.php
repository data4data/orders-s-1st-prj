<?php

declare(strict_types=1);

namespace App\UI\Http\Web;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Customer\Auth\CheckPasswordResetToken;
use App\Application\Customer\Auth\RegisterCustomer;
use App\Application\Customer\Auth\RequestPasswordReset;
use App\Application\Customer\Auth\ResetPassword;
use App\Application\Customer\Input\RegisterCustomerInput;
use App\Application\Customer\Input\ResetPasswordInput;
use App\Application\Customer\Query\ListCountries;
use App\Application\Validation\ValidationException;
use App\UI\Http\Form\RegistrationType;
use App\UI\Http\Security\RateLimitGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Customer login, registration and password reset: Twig + Bootstrap pages (decision #36).
 */
final class CustomerAuthController extends AbstractController
{
    private const SHOP_HOST = "not (request.getHost() matches '/^admin\\\\./')";

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route('/login', name: 'customer_login', methods: ['GET', 'POST'], condition: self::SHOP_HOST)]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('ROLE_CUSTOMER')) {
            return $this->redirectToRoute('account');
        }

        return $this->render('customer/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'customer_logout', methods: ['POST'], condition: self::SHOP_HOST)]
    public function logout(): never
    {
        throw new \LogicException('Handled by the firewall logout listener.');
    }

    #[Route('/register', name: 'customer_register', methods: ['GET', 'POST'], condition: self::SHOP_HOST)]
    public function register(Request $request, #[Target('registration.limiter')] RateLimiterFactoryInterface $limiter): Response
    {
        if ($this->isGranted('ROLE_CUSTOMER')) {
            return $this->redirectToRoute('account');
        }

        $form = $this->createForm(RegistrationType::class, new RegisterCustomerInput(), ['countries' => $this->queryBus->ask(new ListCountries())]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            RateLimitGuard::consume($limiter, $request->getClientIp() ?? 'unknown');
            try {
                /** @var RegisterCustomerInput $input */
                $input = $form->getData();
                $this->commandBus->dispatch(new RegisterCustomer($input));
                $this->addFlash('success', 'register.welcome');

                return $this->redirectToRoute('account');
            } catch (ValidationException $exception) {
                $this->mapViolations($form, $exception);
            }
        }

        return $this->render('customer/register.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/forgot-password', name: 'customer_forgot_password', methods: ['GET', 'POST'], condition: self::SHOP_HOST)]
    public function forgotPassword(Request $request, #[Target('password_reset.limiter')] RateLimiterFactoryInterface $limiter): Response
    {
        $form = $this->createFormBuilder(['email' => ''], ['csrf_token_id' => 'forgot_password'])
            ->add('email', EmailType::class, [
                'label' => 'register.email',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [new Assert\NotBlank(message: 'Enter your email address.'), new Assert\Email(message: 'Enter a valid email address.')],
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();
            RateLimitGuard::consume($limiter, ($request->getClientIp() ?? 'unknown').'|'.strtolower($email));
            $this->commandBus->dispatch(new RequestPasswordReset($email));
            // Same answer whether or not the address has an account (no account enumeration).
            $this->addFlash('info', 'password_reset.sent');

            return $this->redirectToRoute('customer_login');
        }

        return $this->render('customer/forgot_password.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/reset-password/{token}', name: 'customer_reset_password', requirements: ['token' => '[A-Za-z0-9._-]+'], methods: ['GET', 'POST'], condition: self::SHOP_HOST)]
    public function resetPassword(Request $request, string $token): Response
    {
        if (!$this->queryBus->ask(new CheckPasswordResetToken($token))) {
            return $this->render('customer/reset_password.html.twig', ['form' => null], new Response(status: 410));
        }

        $form = $this->createFormBuilder(new ResetPasswordInput(), ['data_class' => ResetPasswordInput::class, 'csrf_token_id' => 'reset_password'])
            ->add('password', PasswordType::class, ['label' => 'password_reset.new_password', 'empty_data' => '', 'help' => 'register.password_help', 'attr' => ['autocomplete' => 'new-password', 'minlength' => 8]])
            ->add('repeatPassword', PasswordType::class, ['label' => 'password_reset.repeat_password', 'empty_data' => '', 'attr' => ['autocomplete' => 'new-password']])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var ResetPasswordInput $input */
                $input = $form->getData();
                $this->commandBus->dispatch(new ResetPassword($token, $input));
                $this->addFlash('success', 'password_reset.done');

                return $this->redirectToRoute('account');
            } catch (ValidationException) {
                return $this->render('customer/reset_password.html.twig', ['form' => null], new Response(status: 410));
            }
        }

        return $this->render('customer/reset_password.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function mapViolations(FormInterface $form, ValidationException $exception): void
    {
        foreach ($exception->violations as $violation) {
            $field = $form;
            foreach (explode('.', $violation['propertyPath']) as $name) {
                $field = $field->has($name) ? $field->get($name) : $field;
            }
            $field->addError(new FormError($violation['message']));
        }
    }
}
