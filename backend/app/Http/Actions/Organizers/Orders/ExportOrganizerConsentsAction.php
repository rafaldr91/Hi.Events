<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Organizers\Orders;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOrganizerConsentsAction extends BaseAction
{
    public function __construct(private readonly OrderRepositoryInterface $orderRepository)
    {
    }

    public function __invoke(Request $request, int $organizerId): StreamedResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $orders = $this->orderRepository->findCompletedByOrganizerId(
            organizerId: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
        );

        $filename = 'consents_' . date('Y-m-d_H-i-s') . '.csv';

        return new StreamedResponse(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Event ID',
                'First Name',
                'Last Name',
                'Email',
                'Order Date',
                'Data Processing Consent',
                'Marketing Consent',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->getEventId(),
                    $order->getFirstName(),
                    $order->getLastName(),
                    $order->getEmail(),
                    $order->getCreatedAt(),
                    $order->getDataProcessingAcceptedAt() ?? '',
                    $order->getOptedIntoMarketingAt() ?? '',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
