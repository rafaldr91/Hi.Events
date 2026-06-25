import {Button, Checkbox, ComboboxItem, Divider, Group, Select, Skeleton, Stack, Table as MantineTable} from '@mantine/core';
import {IconFileSpreadsheet} from "@tabler/icons-react";
import {t} from '@lingui/macro';
import {DatePickerInput} from "@mantine/dates";
import {IconArrowDown, IconArrowsSort, IconArrowUp, IconCalendar} from "@tabler/icons-react";
import React, {useMemo, useState} from "react";
import {PageTitle} from "../PageTitle";
import {DownloadCsvButton} from "../DownloadCsvButton";
import {eventsClient} from "../../../api/event.client.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {showError} from "../../../utilites/notifications.tsx";
import {Table, TableHead} from "../Table";
import '@mantine/dates/styles.css';
import {useGetEventReport} from "../../../queries/useGetEventReport.ts";
import {useParams} from "react-router";
import {Event} from "../../../types.ts";
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import {NoResultsSplash} from "../NoResultsSplash";
import classes from './ReportTable.module.scss';

dayjs.extend(utc);
dayjs.extend(timezone);

interface Column<T> {
    key: keyof T;
    label: string;
    render?: (value: any, row: T) => React.ReactNode;
    sortable?: boolean;
}

interface ReportProps<T> {
    title: string;
    columns: Column<T>[];
    event: Event
    isLoading?: boolean;
    showDateFilter?: boolean;
    defaultStartDate?: Date;
    defaultEndDate?: Date;
    onDateRangeChange?: (range: [Date | null, Date | null]) => void;
    enableDownload?: boolean;
    downloadFileName?: string;
    showCustomDatePicker?: boolean;
    showPaymentProviderFilter?: boolean;
    showTotals?: boolean;
    showExcelExport?: boolean;
    showHideEmptyRows?: boolean;
}

const TIME_PERIODS = [
    {value: '24h', label: t`Last 24 hours`},
    {value: '48h', label: t`Last 48 hours`},
    {value: '7d', label: t`Last 7 days`},
    {value: '14d', label: t`Last 14 days`},
    {value: '30d', label: t`Last 30 days`},
    {value: '90d', label: t`Last 90 days`},
    {value: '6m', label: t`Last 6 months`},
    {value: 'ytd', label: t`Year to date`},
    {value: '12m', label: t`Last 12 months`},
    {value: 'custom', label: t`Custom Range`}
];

const ReportTable = <T extends Record<string, any>>({
                                                        title,
                                                        columns,
                                                        showDateFilter = true,
                                                        defaultStartDate = new Date(new Date().setMonth(new Date().getMonth() - 3)),
                                                        defaultEndDate = new Date(),
                                                        onDateRangeChange,
                                                        enableDownload = true,
                                                        downloadFileName = 'report.csv',
                                                        showCustomDatePicker = false,
                                                        showPaymentProviderFilter = false,
                                                        showTotals = false,
                                                        showExcelExport = false,
                                                        showHideEmptyRows = false,
                                                        event
                                                    }: ReportProps<T>) => {
    const [dateRange, setDateRange] = useState<[Date | null, Date | null]>([
        dayjs(defaultStartDate).tz(event.timezone).toDate(),
        dayjs(defaultEndDate).tz(event.timezone).toDate()
    ]);
    const [selectedPeriod, setSelectedPeriod] = useState('90d');
    const [showDatePickerInput, setShowDatePickerInput] = useState(showCustomDatePicker);
    const [sortField, setSortField] = useState<keyof T | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc' | null>(null);
    const [paymentProviders, setPaymentProviders] = useState<string[]>([]);
    const [isExporting, setIsExporting] = useState(false);
    const [hideEmptyRows, setHideEmptyRows] = useState(true);
    const {reportType, eventId} = useParams();
    const reportQuery = useGetEventReport(eventId, reportType, dateRange[0], dateRange[1], paymentProviders.length ? paymentProviders : undefined);
    const data = (reportQuery.data || []) as T[];

    const handleExcelExport = async () => {
        if (!eventId || !reportType) return;
        setIsExporting(true);
        try {
            const startDate = dateRange[0] ? dateRange[0].toISOString().split('T')[0] : undefined;
            const endDate = dateRange[1] ? dateRange[1].toISOString().split('T')[0] : undefined;
            const blob = await eventsClient.exportEventReport(
                eventId,
                reportType,
                startDate,
                endDate,
                paymentProviders.length ? paymentProviders : undefined,
                showHideEmptyRows && hideEmptyRows,
            );
            downloadBinary(blob, `${reportType}_${startDate}_${endDate}.xlsx`);
        } catch {
            showError(t`Failed to export report`);
        } finally {
            setIsExporting(false);
        }
    };

    const calculateDateRange = (period: string): [Date | null, Date | null] => {
        if (period === 'custom') {
            setShowDatePickerInput(true);
            return dateRange;
        }
        setShowDatePickerInput(false);

        let end = dayjs().tz(event.timezone).endOf('day');
        let start = dayjs().tz(event.timezone);

        switch (period) {
            case '24h':
                start = start.startOf('day');
                end = start.endOf('day');
                break;
            case '48h':
                start = start.subtract(1, 'day').startOf('day');
                end = start.endOf('day').add(1, 'day');
                break;
            case '7d':
                start = start.subtract(6, 'day').startOf('day');
                break;
            case '14d':
                start = start.subtract(13, 'day').startOf('day');
                break;
            case '30d':
                start = start.subtract(29, 'day').startOf('day');
                break;
            case '90d':
                start = start.subtract(89, 'day').startOf('day');
                break;
            case '6m':
                start = start.subtract(6, 'month').startOf('day');
                break;
            case 'ytd':
                start = start.startOf('year');
                break;
            case '12m':
                start = start.subtract(12, 'month').startOf('day');
                break;
            default:
                return [null, null];
        }

        return [start.toDate(), end.toDate()];
    };

    const handlePeriodChange = (value: string | null, _: ComboboxItem) => {
        if (!value) return;
        setSelectedPeriod(value);
        const newRange = calculateDateRange(value);
        setDateRange(newRange);
        onDateRangeChange?.(newRange);
    };

    const handleDateRangeChange = (newRange: [Date | null, Date | null]) => {
        const [start, end] = newRange;
        const tzStart = start ? dayjs(start).tz(event.timezone) : null;
        const tzEnd = end ? dayjs(end).tz(event.timezone) : null;

        const tzRange: [Date | null, Date | null] = [
            tzStart?.toDate() || null,
            tzEnd?.toDate() || null
        ];

        setDateRange(tzRange);
        onDateRangeChange?.(tzRange);
    };

    const handleSort = (field: keyof T) => {
        if (sortField === field) {
            if (sortDirection === 'asc') setSortDirection('desc');
            else if (sortDirection === 'desc') {
                setSortDirection(null);
                setSortField(null);
            } else setSortDirection('asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const getSortIcon = (field: keyof T) => {
        if (sortField !== field) return <IconArrowsSort size={16}/>;
        if (sortDirection === 'asc') return <IconArrowUp size={16}/>;
        if (sortDirection === 'desc') return <IconArrowDown size={16}/>;
        return <IconArrowsSort size={16} className="ml-2 text-gray-400"/>;
    };

    const sortedData = useMemo(() => {
        let rows = [...data];

        if (showHideEmptyRows && hideEmptyRows) {
            rows = rows.filter(row =>
                columns.some((col, i) => {
                    if (i === 0) return false;
                    const n = Number(row[col.key]);
                    return !isNaN(n) && n !== 0;
                })
            );
        }

        return rows.sort((a, b) => {
            if (!sortField || !sortDirection) return 0;
            const aValue = a[sortField];
            const bValue = b[sortField];

            const aNum = Number(aValue);
            const bNum = Number(bValue);

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return sortDirection === 'asc' ? aNum - bNum : bNum - aNum;
            }

            if (typeof aValue === 'string' && typeof bValue === 'string') {
                return sortDirection === 'asc'
                    ? aValue.toLowerCase().localeCompare(bValue.toLowerCase())
                    : bValue.toLowerCase().localeCompare(aValue.toLowerCase());
            }

            return 0;
        });
    }, [data, sortField, sortDirection, showHideEmptyRows, hideEmptyRows, columns]);

    const totalsRow = useMemo(() => {
        if (!showTotals || !data.length) return null;
        return columns.reduce((acc, col, index) => {
            const key = String(col.key);
            if (index === 0) {
                acc[key] = t`Total`;
                return acc;
            }
            const values = data.map(row => Number(row[key]));
            if (values.every(v => !isNaN(v))) {
                acc[key] = values.reduce((sum, v) => sum + v, 0);
            } else {
                acc[key] = '';
            }
            return acc;
        }, {} as Record<string, any>);
    }, [showTotals, data, columns]);

    const csvHeaders = columns.map(col => col.label);
    const csvData = sortedData.map(row =>
        columns.map(col => {
            const value = row[col.key];
            return typeof value === 'number' ? value.toString() : value;
        })
    );

    const loadingMessage = () => {
        const wrapper = (message: React.ReactNode) => (
            <MantineTable.Tr>
                <MantineTable.Td colSpan={columns.length} align="center">
                    {message}
                </MantineTable.Td>
            </MantineTable.Tr>
        );

        if (reportQuery.isLoading) {
            return wrapper(t`Loading...`);
        }

        if (showDateFilter && (!dateRange[0] || !dateRange[1])) {
            return wrapper(t`No data to show. Please select a date range`);
        }

        if (!showDateFilter && dateRange[0] && dateRange[1]) {
            return wrapper(t`No data available`);
        }
    };

    if (reportQuery.isLoading) {
        return (
            <>
                <Group justify="space-between" mb="xl">
                    <Skeleton height={32} width={200}/>
                    <Group gap="sm">
                        <Skeleton height={32} width={200}/>
                        <Skeleton height={32} width={130}/>
                    </Group>
                </Group>
                <Skeleton height={300} radius="md"/>
            </>
        );
    }

    if (reportQuery.isFetched && !reportQuery.isLoading && !data.length) {
        return (
            <NoResultsSplash
                heading={t`Nothing to show yet`}
                imageHref={'/blank-slate/reports.svg'}
                subHeading={(
                    <>
                        <p>
                            {t`Once you start collecting data, you'll see it here.`}
                        </p>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <Stack gap="xs" mb="md">
                <Group justify="space-between" align="center">
                    <PageTitle>{title}</PageTitle>
                    <Group gap="sm" align="center">
                        {enableDownload && (
                            <DownloadCsvButton
                                headers={csvHeaders}
                                data={csvData}
                                filename={downloadFileName}
                                className={classes.downloadButton}
                            />
                        )}
                        {showExcelExport && (
                            <Button
                                variant="light"
                                leftSection={<IconFileSpreadsheet size={16}/>}
                                loading={isExporting}
                                onClick={handleExcelExport}
                            >
                                {t`Export Excel`}
                            </Button>
                        )}
                    </Group>
                </Group>
                {(showDateFilter || showPaymentProviderFilter || showHideEmptyRows) && (
                    <>
                        <Divider/>
                        <Group gap="md" align="center">
                            {showDateFilter && (
                                <>
                                    <Select
                                        style={{minWidth: '200px'}}
                                        placeholder={t`Select time period`}
                                        data={TIME_PERIODS}
                                        value={selectedPeriod}
                                        onChange={handlePeriodChange}
                                        leftSection={<IconCalendar stroke={1.5} size={20}/>}
                                        mb="0"
                                        className={classes.periodSelect}
                                    />
                                    {showDatePickerInput && (
                                        <DatePickerInput
                                            style={{minWidth: '305px', marginBottom: '0'}}
                                            leftSection={<IconCalendar stroke={1.5} size={20}/>}
                                            type="range"
                                            placeholder="Pick dates range"
                                            value={dateRange}
                                            onChange={handleDateRangeChange}
                                            minDate={dayjs().subtract(1, 'year').tz(event.timezone).toDate()}
                                            maxDate={dayjs().tz(event.timezone).toDate()}
                                            className={classes.datePicker}
                                        />
                                    )}
                                </>
                            )}
                            {showDateFilter && (showPaymentProviderFilter || showHideEmptyRows) && (
                                <Divider orientation="vertical" h={20}/>
                            )}
                            {showPaymentProviderFilter && (
                                <>
                                    <Checkbox
                                        label={t`Stripe`}
                                        checked={paymentProviders.includes('STRIPE')}
                                        onChange={e => setPaymentProviders(prev =>
                                            e.currentTarget.checked ? [...prev, 'STRIPE'] : prev.filter(p => p !== 'STRIPE')
                                        )}
                                    />
                                    <Checkbox
                                        label={t`Offline`}
                                        checked={paymentProviders.includes('OFFLINE')}
                                        onChange={e => setPaymentProviders(prev =>
                                            e.currentTarget.checked ? [...prev, 'OFFLINE'] : prev.filter(p => p !== 'OFFLINE')
                                        )}
                                    />
                                    <Checkbox
                                        label={t`Other`}
                                        checked={paymentProviders.includes('OTHER')}
                                        onChange={e => setPaymentProviders(prev =>
                                            e.currentTarget.checked ? [...prev, 'OTHER'] : prev.filter(p => p !== 'OTHER')
                                        )}
                                    />
                                </>
                            )}
                            {showPaymentProviderFilter && showHideEmptyRows && (
                                <Divider orientation="vertical" h={20}/>
                            )}
                            {showHideEmptyRows && (
                                <Checkbox
                                    checked={hideEmptyRows}
                                    onChange={e => setHideEmptyRows(e.currentTarget.checked)}
                                    label={t`Hide days with no sales`}
                                />
                            )}
                        </Group>
                    </>
                )}
            </Stack>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        {columns.map((column) => (
                            <MantineTable.Th
                                key={String(column.key)}
                                onClick={column.sortable ? () => handleSort(column.key) : undefined}
                                style={{cursor: column.sortable ? 'pointer' : 'default', minWidth: '180px'}}
                            >
                                <Group gap="xs" wrap={'nowrap'}>
                                    {column.label}
                                    {column.sortable && getSortIcon(column.key)}
                                </Group>
                            </MantineTable.Th>
                        ))}
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {!sortedData.length && loadingMessage()}
                    {sortedData.map((row, index) => (
                        <MantineTable.Tr key={index}>
                            {columns.map((column) => (
                                <MantineTable.Td key={String(column.key)}>
                                    {column.render
                                        ? column.render(row[column.key], row)
                                        : row[column.key]
                                    }
                                </MantineTable.Td>
                            ))}
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
                {totalsRow && (
                    <MantineTable.Tfoot>
                        <MantineTable.Tr style={{fontWeight: 'bold'}}>
                            {columns.map((column) => {
                                const key = String(column.key);
                                const value = totalsRow![key];
                                return (
                                    <MantineTable.Td key={key}>
                                        {column.render && typeof value === 'number'
                                            ? column.render(value, totalsRow as T)
                                            : value
                                        }
                                    </MantineTable.Td>
                                );
                            })}
                        </MantineTable.Tr>
                    </MantineTable.Tfoot>
                )}
            </Table>
        </>
    );
};

export default ReportTable;
