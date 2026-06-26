<?php

namespace HiEvents\Services\Domain\Report\OrganizerReports;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Report\AbstractOrganizerReportService;
use Illuminate\Support\Carbon;

class RevenueSummaryReport extends AbstractOrganizerReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?string $currency = null, ?array $paymentProviders = null, ?array $buyerTypes = null): string
    {
        $startDateStr = $startDate->format('Y-m-d H:i:s');
        $endDateStr = $endDate->format('Y-m-d H:i:s');
        $startDateOnly = $startDate->toDateString();
        $endDateOnly = $endDate->toDateString();
        $completedStatus = OrderStatus::COMPLETED->name;

        $currencyFilter = $this->buildCurrencyFilter('e.currency', $currency);
        $providerCondition = $this->buildProviderCondition($paymentProviders ?? []);
        $buyerTypeCondition = $this->buildBuyerTypeCondition($buyerTypes);

        return <<<SQL
            WITH date_range AS (
                SELECT generate_series('$startDateOnly'::date, '$endDateOnly'::date, '1 day'::interval) AS date
            ),
            daily_orders AS (
                SELECT
                    (o.created_at AT TIME ZONE 'UTC')::date AS order_date,
                    SUM(o.total_gross)            AS gross_sales,
                    SUM(o.total_tax)              AS total_tax,
                    SUM(o.total_fee)              AS total_fee,
                    COUNT(o.id)                   AS order_count
                FROM orders o
                INNER JOIN events e ON o.event_id = e.id
                WHERE e.organizer_id = :organizer_id
                    AND o.status = '$completedStatus'
                    AND $providerCondition
                    AND $buyerTypeCondition
                    AND o.deleted_at IS NULL
                    AND e.deleted_at IS NULL
                    AND o.created_at >= '$startDateStr'
                    AND o.created_at <= '$endDateStr'
                    $currencyFilter
                GROUP BY (o.created_at AT TIME ZONE 'UTC')::date
            ),
            daily_refunds AS (
                SELECT
                    (o.created_at AT TIME ZONE 'UTC')::date AS order_date,
                    SUM(orr.amount)                         AS total_refunded
                FROM order_refunds orr
                INNER JOIN orders o ON orr.order_id = o.id
                INNER JOIN events e ON o.event_id = e.id
                WHERE e.organizer_id = :organizer_id
                    AND $providerCondition
                    AND $buyerTypeCondition
                    AND o.deleted_at IS NULL
                    AND e.deleted_at IS NULL
                    AND orr.deleted_at IS NULL
                    AND o.created_at >= '$startDateStr'
                    AND o.created_at <= '$endDateStr'
                    $currencyFilter
                GROUP BY (o.created_at AT TIME ZONE 'UTC')::date
            )
            SELECT
                d.date,
                COALESCE(do_.gross_sales, 0.00)                            AS gross_sales,
                COALESCE(do_.gross_sales, 0.00) - COALESCE(do_.total_tax, 0.00) AS net_revenue,
                COALESCE(dr.total_refunded, 0.00)                          AS total_refunded,
                COALESCE(do_.total_tax, 0.00)                              AS total_tax,
                COALESCE(do_.total_fee, 0.00)                              AS total_fee,
                COALESCE(do_.order_count, 0)                               AS order_count
            FROM date_range d
            LEFT JOIN daily_orders do_ ON d.date = do_.order_date
            LEFT JOIN daily_refunds dr  ON d.date = dr.order_date
            ORDER BY d.date DESC
        SQL;
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
