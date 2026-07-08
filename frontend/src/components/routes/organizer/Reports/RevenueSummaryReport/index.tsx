import {useParams} from "react-router";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerStats} from "../../../../../queries/useGetOrganizerStats.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import OrganizerReportTable, {ActiveFilters} from "../../../../common/OrganizerReportTable";
import {t} from "@lingui/macro";
import {ActionIcon, Tooltip} from "@mantine/core";
import {IconDownload} from "@tabler/icons-react";
import {useState} from "react";
import {organizerClient} from "../../../../../api/organizer.client.ts";
import {downloadBinary} from "../../../../../utilites/download.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import dayjs from "dayjs";

interface RevenueSummaryRow {
    date: string;
    gross_sales: string;
    net_revenue: string;
    total_refunded: string;
    total_tax: string;
    total_fee: string;
    order_count: number;
}

const RevenueSummaryReport = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const organizer = organizerQuery.data;
    const [exportingDate, setExportingDate] = useState<string | null>(null);

    const statsQuery = useGetOrganizerStats(organizerId, organizer?.currency);
    const allCurrencies = statsQuery.data?.all_organizers_currencies || [];

    if (!organizer) {
        return null;
    }

    const handleRowDownload = async (row: RevenueSummaryRow, filters: ActiveFilters) => {
        const date = dayjs(row.date).format('YYYY-MM-DD');
        setExportingDate(date);
        try {
            const blob = await organizerClient.exportOrganizerDailyOrders(
                organizerId,
                date,
                filters.currency,
                filters.paymentProviders,
                filters.buyerTypes,
            );
            downloadBinary(blob, `orders_${date}.csv`);
            showSuccess(t`Export successful`);
        } catch {
            showError(t`Failed to export orders. Please try again.`);
        } finally {
            setExportingDate(null);
        }
    };

    const columns = [
        {
            key: 'date' as const,
            label: t`Date`,
            sortable: true,
            render: (value: string) => formatDateWithLocale(value, 'shortDate', organizer?.timezone || 'UTC')
        },
        {
            key: 'gross_sales' as const,
            label: t`Gross Sales`,
            sortable: true,
            render: (value: string, _row: any, context: { currency: string }) => formatCurrency(value, context.currency)
        },
        {
            key: 'net_revenue' as const,
            label: t`Net Revenue`,
            sortable: true,
            render: (value: string, _row: any, context: { currency: string }) => formatCurrency(value, context.currency)
        },
        {
            key: 'total_refunded' as const,
            label: t`Refunds`,
            sortable: true,
            render: (value: string, _row: any, context: { currency: string }) => formatCurrency(value, context.currency)
        },
        {
            key: 'total_tax' as const,
            label: t`Taxes`,
            sortable: true,
            render: (value: string, _row: any, context: { currency: string }) => formatCurrency(value, context.currency)
        },
        {
            key: 'total_fee' as const,
            label: t`Fees`,
            sortable: true,
            render: (value: string, _row: any, context: { currency: string }) => formatCurrency(value, context.currency)
        },
        {
            key: 'order_count' as const,
            label: t`Orders`,
            sortable: true
        }
    ];

    return (
        <OrganizerReportTable
            title={t`Revenue Summary`}
            columns={columns}
            isLoading={organizerQuery.isLoading}
            downloadFileName="revenue_summary_report.csv"
            showDateFilter={true}
            organizer={organizer}
            showCurrencyFilter={true}
            availableCurrencies={allCurrencies}
            showPaymentProviderFilter={true}
            showBuyerTypeFilter={true}
            rowActions={(row: RevenueSummaryRow, filters: ActiveFilters) => (
                <Tooltip label={t`Download orders for this day`} withArrow>
                    <ActionIcon
                        variant="subtle"
                        size="sm"
                        loading={exportingDate === dayjs(row.date).format('YYYY-MM-DD')}
                        onClick={() => handleRowDownload(row, filters)}
                        disabled={Number(row.order_count) === 0}
                    >
                        <IconDownload size={16}/>
                    </ActionIcon>
                </Tooltip>
            )}
        />
    );
};

export default RevenueSummaryReport;
