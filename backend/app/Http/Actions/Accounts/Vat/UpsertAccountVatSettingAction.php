<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\Vat;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountVatSettingResource;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\UpsertAccountVatSettingDTO;
use HiEvents\Services\Application\Handlers\Account\Vat\UpsertAccountVatSettingHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpsertAccountVatSettingAction extends BaseAction
{
    public function __construct(
        private readonly UpsertAccountVatSettingHandler $handler,
    ) {
    }

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        if ($accountId !== $this->getAuthenticatedAccountId()) {
            return $this->errorResponse(__('Unauthorized'));
        }

        $validated = $request->validate([
            'vat_registered'             => 'required|boolean',
            'vat_number'                 => 'nullable|string|max:20',
            'business_name'              => 'nullable|string|max:200',
            'business_address'           => 'nullable|string|max:500',
            'invoice_number_format'      => 'nullable|string|max:100',
            'invoice_prefix'             => 'nullable|string|max:50',
            'invoice_suffix'             => 'nullable|string|max:50',
            'invoice_start_number'       => 'nullable|integer|min:1',
            'confirmation_prefix'        => 'nullable|string|max:50',
            'confirmation_start_number'  => 'nullable|integer|min:1',
        ]);

        $vatSetting = $this->handler->handle(new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: $validated['vat_registered'],
            vatNumber: $validated['vat_number'] ?? null,
            businessName: $validated['business_name'] ?? null,
            businessAddress: $validated['business_address'] ?? null,
            invoiceNumberFormat: $validated['invoice_number_format'] ?? null,
            invoicePrefix: $validated['invoice_prefix'] ?? null,
            invoiceSuffix: $validated['invoice_suffix'] ?? null,
            invoiceStartNumber: $validated['invoice_start_number'] ?? 1,
            confirmationPrefix: $validated['confirmation_prefix'] ?? null,
            confirmationStartNumber: $validated['confirmation_start_number'] ?? 1,
        ));

        return $this->resourceResponse(AccountVatSettingResource::class, $vatSetting);
    }
}
