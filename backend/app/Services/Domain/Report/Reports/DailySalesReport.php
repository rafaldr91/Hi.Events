<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class DailySalesReport extends AbstractReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?array $paymentProviders = null, ?array $buyerTypes = null): string
    {
        return $this->getOrdersQuery($startDate, $endDate, $paymentProviders ?? [], $buyerTypes);
    }

    private function getOrdersQuery(Carbon $startDate, Carbon $endDate, array $paymentProviders, ?array $buyerTypes): string
    {
        $startDateStr = $startDate->format('Y-m-d H:i:s');
        $endDateStr = $endDate->format('Y-m-d H:i:s');
        $startDateOnly = $startDate->toDateString();
        $endDateOnly = $endDate->toDateString();
        $completedStatus = OrderStatus::COMPLETED->name;

        $providerCondition = $this->buildProviderCondition($paymentProviders);
        $buyerTypeCondition = $this->buildBuyerTypeCondition($buyerTypes);

        return <<<SQL
            WITH date_range AS (
                SELECT generate_series('$startDateOnly'::date, '$endDateOnly'::date, '1 day'::interval) AS date
            ),
            daily_orders AS (
                SELECT
                    (o.created_at AT TIME ZONE 'UTC')::date AS order_date,
                    SUM(o.total_gross)             AS sales_total_gross,
                    SUM(o.total_tax)               AS total_tax,
                    SUM(o.total_before_additions)  AS sales_total_before_additions,
                    COUNT(o.id)                    AS orders_created,
                    SUM(o.total_fee)               AS total_fee
                FROM orders o
                WHERE o.event_id = :event_id
                    AND o.status = '$completedStatus'
                    AND $providerCondition
                    AND $buyerTypeCondition
                    AND o.deleted_at IS NULL
                    AND o.created_at >= '$startDateStr'
                    AND o.created_at <= '$endDateStr'
                GROUP BY (o.created_at AT TIME ZONE 'UTC')::date
            ),
            daily_items AS (
                SELECT
                    (o.created_at AT TIME ZONE 'UTC')::date AS order_date,
                    SUM(oi.quantity)                        AS products_sold
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.event_id = :event_id
                    AND o.status = '$completedStatus'
                    AND $providerCondition
                    AND $buyerTypeCondition
                    AND o.deleted_at IS NULL
                    AND oi.deleted_at IS NULL
                    AND o.created_at >= '$startDateStr'
                    AND o.created_at <= '$endDateStr'
                GROUP BY (o.created_at AT TIME ZONE 'UTC')::date
            ),
            daily_refunds AS (
                SELECT
                    (o.created_at AT TIME ZONE 'UTC')::date AS order_date,
                    SUM(orr.amount)                         AS total_refunded
                FROM order_refunds orr
                JOIN orders o ON orr.order_id = o.id
                WHERE o.event_id = :event_id
                    AND $providerCondition
                    AND $buyerTypeCondition
                    AND o.deleted_at IS NULL
                    AND orr.deleted_at IS NULL
                    AND o.created_at >= '$startDateStr'
                    AND o.created_at <= '$endDateStr'
                GROUP BY (o.created_at AT TIME ZONE 'UTC')::date
            )
            SELECT
                d.date,
                COALESCE(do_.sales_total_gross, 0.00)            AS sales_total_gross,
                COALESCE(do_.total_tax, 0.00)                    AS total_tax,
                COALESCE(do_.sales_total_before_additions, 0.00) AS sales_total_before_additions,
                COALESCE(di.products_sold, 0)                    AS products_sold,
                COALESCE(do_.orders_created, 0)                  AS orders_created,
                COALESCE(do_.total_fee, 0.00)                    AS total_fee,
                COALESCE(dr.total_refunded, 0.00)                AS total_refunded,
                0                                                AS total_views
            FROM date_range d
            LEFT JOIN daily_orders do_ ON d.date = do_.order_date
            LEFT JOIN daily_items di   ON d.date = di.order_date
            LEFT JOIN daily_refunds dr ON d.date = dr.order_date
            ORDER BY d.date DESC;
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
