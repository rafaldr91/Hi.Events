<?php

namespace HiEvents\Http\Actions\Reports;

use HiEvents\DomainObjects\Enums\ReportTypes;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exports\EventReportExport;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Report\GetReportRequest;
use HiEvents\Services\Application\Handlers\Reports\DTO\GetReportDTO;
use HiEvents\Services\Application\Handlers\Reports\GetReportHandler;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ExportEventReportAction extends BaseAction
{
    public function __construct(
        private readonly GetReportHandler $reportHandler,
        private readonly EventReportExport $export,
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(GetReportRequest $request, int $eventId, string $reportType): BinaryFileResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->validateDateRange($request);

        if (!in_array($reportType, ReportTypes::valuesArray(), true)) {
            throw new BadRequestHttpException(__('Invalid report type.'));
        }

        $type = ReportTypes::from($reportType);

        $reportData = $this->reportHandler->handle(
            reportData: new GetReportDTO(
                eventId: $eventId,
                reportType: $type,
                startDate: $request->validated('start_date'),
                endDate: $request->validated('end_date'),
                paymentProviders: $request->validated('payment_providers') ?: null,
                buyerTypes: $request->validated('buyer_types') ?: null,
            ),
        );

        $data = $reportData->toArray();

        [$headings, $rows] = $this->mapReportData($type, $data);

        if ($request->boolean('hide_empty_rows')) {
            $rows = array_values(array_filter($rows, fn(array $row) => $this->rowHasData($row)));
        }

        $filename = $reportType . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(
            $this->export->withData($headings, $rows),
            $filename,
        );
    }

    private function mapReportData(ReportTypes $type, array $data): array
    {
        return match ($type) {
            ReportTypes::DAILY_SALES_REPORT => [
                [
                    __('Date'),
                    __('Sales Total Gross'),
                    __('Total Tax'),
                    __('Net Sales'),
                    __('Products Sold'),
                    __('Completed Orders'),
                    __('Total Fee'),
                    __('Total Refunded'),
                    __('Total Views'),
                ],
                array_map(fn($row) => [
                    $row->date ?? '',
                    $row->sales_total_gross ?? 0,
                    $row->total_tax ?? 0,
                    $row->sales_total_before_additions ?? 0,
                    $row->products_sold ?? 0,
                    $row->orders_created ?? 0,
                    $row->total_fee ?? 0,
                    $row->total_refunded ?? 0,
                    $row->total_views ?? 0,
                ], $data),
            ],
            ReportTypes::PRODUCT_SALES => [
                [
                    __('Product Title'),
                    __('Units Sold'),
                    __('Gross Sales'),
                    __('Tax'),
                    __('Service Fees'),
                ],
                array_map(fn($row) => [
                    $row->product_title ?? '',
                    $row->number_sold ?? 0,
                    $row->total_gross ?? 0,
                    $row->total_tax ?? 0,
                    $row->total_service_fees ?? 0,
                ], $data),
            ],
            ReportTypes::PROMO_CODES_REPORT => [
                [
                    __('Promo Code'),
                    __('Times Used'),
                    __('Unique Customers'),
                    __('Configured Discount'),
                    __('Total Gross Sales'),
                    __('Total Before Discounts'),
                    __('Total Discount Amount'),
                    __('First Used'),
                    __('Last Used'),
                    __('Usage Limit'),
                    __('Remaining Uses'),
                    __('Status'),
                ],
                array_map(fn($row) => [
                    $row->promo_code ?? '',
                    $row->times_used ?? 0,
                    $row->unique_customers ?? 0,
                    $row->configured_discount ?? '',
                    $row->total_gross_sales ?? 0,
                    $row->total_before_discounts ?? 0,
                    $row->total_discount_amount ?? 0,
                    $row->first_used_at ?? '',
                    $row->last_used_at ?? '',
                    $row->max_allowed_usages ?? '',
                    $row->remaining_uses ?? '',
                    $row->status ?? '',
                ], $data),
            ],
        };
    }

    private function rowHasData(array $row): bool
    {
        $values = array_slice($row, 1);
        foreach ($values as $value) {
            if (is_numeric($value) && (float) $value !== 0.0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws ValidationException
     */
    private function validateDateRange(GetReportRequest $request): void
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        if (!$startDate || !$endDate) {
            return;
        }

        $diffInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));

        if ($diffInDays > 370) {
            throw ValidationException::withMessages(['start_date' => __('Date range must be less than 370 days.')]);
        }
    }
}
