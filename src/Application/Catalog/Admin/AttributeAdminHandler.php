<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

use App\Application\Catalog\Port\AttributeRepositoryInterface;
use App\Application\Exception\NotFoundException;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Domain\Catalog\AttributeType;
use App\Entity\Attribute;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Admin: specification attributes (SAE viscosity, ISO VG, approvals…) and their options.
 */
final readonly class AttributeAdminHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private AttributeRepositoryInterface $attributes,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListAttributes $query): array
    {
        $this->tenantContext->requireStore();

        return array_map(fn (Attribute $attribute) => [
            'id' => $attribute->getId(),
            'code' => $attribute->getCode(),
            'name' => $attribute->getName(),
            'type' => $attribute->getType()->value,
            'unit' => $attribute->getUnit(),
            'isFilterable' => $attribute->isFilterable(),
            'position' => $attribute->getPosition(),
            'options' => array_values(array_map(static fn ($o) => $o->getValue(), $attribute->getOptions()->toArray())),
            'productCount' => $this->attributes->countProductsUsing($attribute),
        ], $this->attributes->findAllOrdered());
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveAttribute $command): int
    {
        $this->tenantContext->requireStore();
        $input = $command->input;
        $type = AttributeType::from($input->type);

        $attribute = null;
        if (null !== $command->id) {
            $attribute = $this->attributes->findById($command->id) ?? throw NotFoundException::of('Attribute', (string) $command->id);
        }
        if ($this->attributes->codeExists($input->code, $attribute?->getId())) {
            throw ValidationException::forField('code', 'Another attribute already uses this code.');
        }
        if ($type->usesOptions() && [] === $input->options) {
            throw ValidationException::forField('options', 'Add at least one option for a select attribute.');
        }

        $attribute ??= new Attribute($input->code, $input->name, $type);
        $attribute->update($input->code, $input->name, $type, '' === $input->unit ? null : $input->unit, $input->isFilterable, $input->position);
        $attribute->replaceOptions($type->usesOptions() ? $input->options : []);
        $this->attributes->save($attribute);

        return (int) $attribute->getId();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteAttribute $command): void
    {
        $this->tenantContext->requireStore();
        $attribute = $this->attributes->findById($command->id) ?? throw NotFoundException::of('Attribute', (string) $command->id);

        $products = $this->attributes->countProductsUsing($attribute);
        if ($products > 0) {
            throw new \DomainException(sprintf('"%s" is used by %d product(s). Remove it from those products first.', $attribute->getName(), $products));
        }
        $this->attributes->remove($attribute);
    }
}
