<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Organizers\Orders;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOrganizerDailyOrdersCsvAction extends BaseAction
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $organizerId): StreamedResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'currency' => ['nullable', 'string', 'max:10'],
            'buyer_types' => ['nullable', 'array'],
            'buyer_types.*' => ['in:company,individual'],
            'payment_providers' => ['nullable', 'array'],
            'payment_providers.*' => ['in:STRIPE,OFFLINE,OTHER'],
        ]);

        $date = $validated['date'];
        $currency = $validated['currency'] ?? null;
        $buyerTypes = $validated['buyer_types'] ?? null;
        $paymentProviders = $validated['payment_providers'] ?? null;

        $startOfDay = Carbon::parse($date)->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = Carbon::parse($date)->endOfDay()->format('Y-m-d H:i:s');

        $currencyFilter = $currency ? "AND e.currency = '" . addslashes($currency) . "'" : '';
        $buyerTypeCondition = $this->buildBuyerTypeCondition($buyerTypes);
        $providerCondition = $this->buildProviderCondition($paymentProviders ?? []);

        $sql = <<<SQL
            SELECT
                o.id,
                o.short_id,
                o.first_name,
                o.last_name,
                o.email,
                o.company_name,
                o.company_nip,
                o.buyer_type,
                o.total_gross,
                o.total_tax,
                o.total_fee,
                o.total_refunded,
                o.total_gross - o.total_refunded AS net_amount,
                o.payment_provider,
                o.status,
                o.currency,
                o.created_at,
                e.title AS event_name,
                e.currency AS event_currency
            FROM orders o
            INNER JOIN events e ON o.event_id = e.id
            WHERE e.organizer_id = :organizer_id
                AND o.status = 'COMPLETED'
                AND $providerCondition
                AND $buyerTypeCondition
                AND o.deleted_at IS NULL
                AND e.deleted_at IS NULL
                AND o.created_at >= '$startOfDay'
                AND o.created_at <= '$endOfDay'
                $currencyFilter
            ORDER BY o.created_at ASC
        SQL;

        $orders = $this->db->select($sql, ['organizer_id' => $organizerId]);

        $filename = 'orders_' . $date . '.csv';

        return new StreamedResponse(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                __('ID'),
                __('First Name'),
                __('Last Name'),
                __('Email'),
                __('Company Name'),
                __('Company NIP'),
                __('Buyer Type'),
                __('Gross Amount'),
                __('Tax'),
                __('Fee'),
                __('Refunded'),
                __('Net Amount'),
                __('Payment Provider'),
                __('Status'),
                __('Currency'),
                __('Created At'),
                __('Event'),
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->short_id,
                    $order->first_name,
                    $order->last_name,
                    $order->email,
                    $order->company_name ?? '',
                    $order->company_nip ?? '',
                    $order->buyer_type ?? '',
                    $order->total_gross,
                    $order->total_tax,
                    $order->total_fee,
                    $order->total_refunded,
                    $order->net_amount,
                    $order->payment_provider ?? '',
                    $order->status,
                    $order->currency,
                    Carbon::parse($order->created_at)->format('Y-m-d H:i:s'),
                    $order->event_name,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function buildBuyerTypeCondition(?array $buyerTypes): string
    {
        if (empty($buyerTypes)) {
            return '1=1';
        }

        $conditions = [];
        if (in_array('company', $buyerTypes, true)) {
            $conditions[] = "o.buyer_type = 'company'";
        }
        if (in_array('individual', $buyerTypes, true)) {
            $conditions[] = "o.buyer_type != 'company'";
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function buildProviderCondition(array $paymentProviders): string
    {
        if (empty($paymentProviders)) {
            return '1=1';
        }

        $namedProviders = array_values(array_filter($paymentProviders, fn($p) => $p !== 'OTHER'));
        $includeNull = in_array('OTHER', $paymentProviders, true);

        $conditions = [];
        if (!empty($namedProviders)) {
            $inList = implode("','", $namedProviders);
            $conditions[] = "o.payment_provider IN ('$inList')";
        }
        if ($includeNull) {
            $conditions[] = 'o.payment_provider IS NULL';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }
}
