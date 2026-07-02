import {t} from "@lingui/macro";
import {Badge, Button, Group, Table, Text} from "@mantine/core";
import {IconDownload} from "@tabler/icons-react";
import {useState} from "react";
import {useParams} from "react-router";
import {useGetOrganizerOrders} from "../../../../queries/useGetOrganizerOrders.ts";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {IdParam, QueryFilters} from "../../../../types.ts";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {Pagination} from "../../../common/Pagination";
import {organizerClient} from "../../../../api/organizer.client.ts";
import {downloadBinary} from "../../../../utilites/download.ts";
import {withLoadingNotification} from "../../../../utilites/withLoadingNotification.tsx";

const ConsentBadge = ({date}: { date?: string | null }) => {
    if (date) {
        return (
            <Badge color="green" variant="light" size="sm">
                {new Date(date).toLocaleString()}
            </Badge>
        );
    }
    return (
        <Badge color="gray" variant="light" size="sm">
            {t`Not given`}
        </Badge>
    );
};

const OrganizerGdprConsents = () => {
    const {organizerId} = useParams<{ organizerId: string }>();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [exportPending, setExportPending] = useState(false);

    const ordersQuery = useGetOrganizerOrders(organizerId, searchParams as QueryFilters);
    const orders = ordersQuery?.data?.data;
    const pagination = ordersQuery?.data?.meta;

    const handleExport = async (organizerId: IdParam) => {
        await withLoadingNotification(async () => {
                setExportPending(true);
                const blob = await organizerClient.exportOrganizerConsents(organizerId);
                downloadBinary(blob, 'consents.csv');
            },
            {
                loading: {title: t`Exporting Consents`, message: t`Please wait...`},
                success: {
                    title: t`Consents Exported`,
                    message: t`Your consent data has been exported successfully.`,
                    onRun: () => setExportPending(false),
                },
                error: {
                    title: t`Export Failed`,
                    message: t`Please try again.`,
                    onRun: () => setExportPending(false),
                },
            });
    };

    return (
        <PageBody>
            <PageTitle>
                <Group justify="space-between">
                    <span>{t`GDPR Consents`}</span>
                    <Button
                        leftSection={<IconDownload size={16}/>}
                        variant="light"
                        size="sm"
                        loading={exportPending}
                        onClick={() => handleExport(organizerId)}
                    >
                        {t`Export CSV`}
                    </Button>
                </Group>
            </PageTitle>

            <ToolBar>
                <SearchBarWrapper
                    placeholder={t`Search by name or email...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                />
            </ToolBar>

            {<TableSkeleton isVisible={!orders || ordersQuery.isFetching}/>}
            {!ordersQuery.isFetching && (
                <Table striped highlightOnHover>
                    <Table.Thead>
                        <Table.Tr>
                            <Table.Th>{t`Buyer`}</Table.Th>
                            <Table.Th>{t`Email`}</Table.Th>
                            <Table.Th>{t`Order Date`}</Table.Th>
                            <Table.Th>{t`Data Processing Consent`}</Table.Th>
                            <Table.Th>{t`Marketing Consent`}</Table.Th>
                        </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {orders?.length === 0 && (
                            <Table.Tr>
                                <Table.Td colSpan={5}>
                                    <Text ta="center" c="dimmed" py="md">{t`No orders found`}</Text>
                                </Table.Td>
                            </Table.Tr>
                        )}
                        {orders?.map(order => (
                            <Table.Tr key={order.id}>
                                <Table.Td>
                                    {order.first_name} {order.last_name}
                                </Table.Td>
                                <Table.Td>{order.email}</Table.Td>
                                <Table.Td>
                                    {new Date(order.created_at).toLocaleDateString()}
                                </Table.Td>
                                <Table.Td>
                                    <ConsentBadge date={order.data_processing_accepted_at}/>
                                </Table.Td>
                                <Table.Td>
                                    <ConsentBadge date={order.opted_into_marketing_at}/>
                                </Table.Td>
                            </Table.Tr>
                        ))}
                    </Table.Tbody>
                </Table>
            )}

            <Pagination
                value={searchParams.pageNumber}
                onChange={(value) => setSearchParams({pageNumber: value})}
                total={Number(pagination?.last_page)}
            />
        </PageBody>
    );
};

export default OrganizerGdprConsents;
