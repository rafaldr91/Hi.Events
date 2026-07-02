<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\Enums\OrderStatus;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportConsentsAction extends BaseAction
{
    public function __construct(private readonly OrderRepositoryInterface $orderRepository)
    {
    }

    public function __invoke(Request $request, int $eventId): StreamedResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $orders = $this->orderRepository
            ->setMaxPerPage(50000)
            ->findByEventId($eventId, new QueryParamsDTO(
                page: 1,
                per_page: 50000,
                filter_fields: [
                    'status' => OrderStatus::COMPLETED->name,
                ],
            ));

        $filename = 'consents_' . date('Y-m-d_H-i-s') . '.csv';

        return new StreamedResponse(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'First Name',
                'Last Name',
                'Email',
                'Order Date',
                'Data Processing Consent',
                'Marketing Consent',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
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
