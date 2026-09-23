<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Content\Port\ContactMessageRepositoryInterface;
use App\Application\Tenancy\TenantContextInterface;
use App\Entity\ContactMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SubmitContactMessageHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private ContactMessageRepositoryInterface $messages,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(SubmitContactMessage $command): void
    {
        $input = $command->input;
        if (null !== $input->website && '' !== $input->website) {
            // A bot filled in the hidden field: answer as usual, store nothing.
            return;
        }

        $store = $this->tenantContext->requireStore();
        $orderNumber = null !== $input->orderNumber && '' !== trim($input->orderNumber) ? strtoupper(trim($input->orderNumber)) : null;
        $message = new ContactMessage(trim($input->name), strtolower(trim($input->email)), $input->subject, trim($input->message), $orderNumber);
        $this->messages->save($message);

        if (null !== $store->getContactEmail() && $store->wantsNotification('contact_message')) {
            $this->mailer->send((new Email())
                ->from(new Address('noreply@'.$store->getCode().'.test', $store->getName()))
                ->to($store->getContactEmail())
                ->replyTo(new Address($message->getEmail(), $message->getName()))
                ->subject(sprintf('[%s] Contact form: %s%s', $store->getName(), $message->getSubject(), null !== $orderNumber ? ' ('.$orderNumber.')' : ''))
                ->text($message->getMessage()));
        }
    }
}
