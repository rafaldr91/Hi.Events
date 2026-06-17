import {useEffect, useState} from 'react';
import {t} from '@lingui/macro';
import {Button, Divider, Group, NumberInput, Stack, Text, TextInput} from '@mantine/core';
import {useGetAccountVatSetting} from '../../../../../../queries/useGetAccountVatSetting.ts';
import {useUpsertAccountVatSetting} from '../../../../../../mutations/useUpsertAccountVatSetting.ts';
import {showError, showSuccess} from '../../../../../../utilites/notifications.tsx';
import {Account} from '../../../../../../types.ts';
import {HeadingCard} from '../../../../../common/HeadingCard';
import {Card} from '../../../../../common/Card';
import accountClasses from '../../ManageAccount.module.scss';

interface SellerSettingsProps {
    account: Account;
}

export const SellerSettings = ({account}: SellerSettingsProps) => {
    const [nip, setNip] = useState('');
    const [businessName, setBusinessName] = useState('');
    const [businessAddress, setBusinessAddress] = useState('');
    const [invoiceNumberFormat, setInvoiceNumberFormat] = useState('');
    const [invoicePrefix, setInvoicePrefix] = useState('');
    const [invoiceSuffix, setInvoiceSuffix] = useState('');
    const [invoiceStartNumber, setInvoiceStartNumber] = useState<number>(1);
    const [confirmationPrefix, setConfirmationPrefix] = useState('');
    const [confirmationStartNumber, setConfirmationStartNumber] = useState<number>(1);

    const vatSettingQuery = useGetAccountVatSetting(account.id);
    const upsertMutation = useUpsertAccountVatSetting(account.id);

    useEffect(() => {
        const data = vatSettingQuery.data;
        if (data) {
            setNip(data.vat_number || '');
            setBusinessName(data.business_name || '');
            setBusinessAddress(data.business_address || '');
            setInvoiceNumberFormat(data.invoice_number_format || '');
            setInvoicePrefix(data.invoice_prefix || '');
            setInvoiceSuffix(data.invoice_suffix || '');
            setInvoiceStartNumber(data.invoice_start_number ?? 1);
            setConfirmationPrefix(data.confirmation_prefix || '');
            setConfirmationStartNumber(data.confirmation_start_number ?? 1);
        }
    }, [vatSettingQuery.data]);

    const handleSave = async () => {
        const trimmedNip = nip.replace(/[\s\-]/g, '').replace(/^PL/i, '');

        if (trimmedNip && !/^\d{10}$/.test(trimmedNip)) {
            showError(t`NIP must be exactly 10 digits`);
            return;
        }

        try {
            await upsertMutation.mutateAsync({
                vat_registered: trimmedNip.length > 0,
                vat_number: trimmedNip || null,
                business_name: businessName.trim() || null,
                business_address: businessAddress.trim() || null,
                invoice_number_format: invoiceNumberFormat.trim() || null,
                invoice_prefix: invoicePrefix.trim() || null,
                invoice_suffix: invoiceSuffix.trim() || null,
                invoice_start_number: invoiceStartNumber,
                confirmation_prefix: confirmationPrefix.trim() || null,
                confirmation_start_number: confirmationStartNumber,
            });
            showSuccess(t`Seller settings saved successfully`);
        } catch {
            showError(t`Failed to save seller settings. Please try again.`);
        }
    };

    return (
        <>
            <HeadingCard
                heading={t`Seller Details (KSeF)`}
                subHeading={t`These details are used when generating KSeF FA(3) XML invoices for B2B orders`}
            />
            <Card className={accountClasses.tabContent}>
                <Stack gap="md">
                    <TextInput
                        label={t`NIP (Tax Identification Number)`}
                        description={t`10-digit Polish tax number, without spaces or dashes (e.g. 1234567890)`}
                        placeholder="1234567890"
                        value={nip}
                        onChange={(e) => setNip(e.target.value)}
                        maxLength={15}
                    />
                    <TextInput
                        label={t`Company Name`}
                        placeholder={t`Acme Sp. z o.o.`}
                        value={businessName}
                        onChange={(e) => setBusinessName(e.target.value)}
                        maxLength={200}
                    />
                    <TextInput
                        label={t`Business Address`}
                        placeholder={t`ul. Przykładowa 1, 00-001 Warszawa`}
                        value={businessAddress}
                        onChange={(e) => setBusinessAddress(e.target.value)}
                        maxLength={500}
                    />

                    <Divider />

                    <Text fw={500} size="sm">{t`Invoice Numbering`}</Text>

                    <TextInput
                        label={t`Invoice number format`}
                        description={t`Use {number}, {month}, {year} as placeholders. Leave empty to use the default {number} format.`}
                        placeholder="{number}/{month}/{year}"
                        value={invoiceNumberFormat}
                        onChange={(e) => setInvoiceNumberFormat(e.target.value)}
                        maxLength={100}
                    />
                    <Group grow>
                        <TextInput
                            label={t`Invoice prefix`}
                            placeholder="FV/"
                            value={invoicePrefix}
                            onChange={(e) => setInvoicePrefix(e.target.value)}
                            maxLength={50}
                        />
                        <TextInput
                            label={t`Invoice suffix`}
                            placeholder="/2026"
                            value={invoiceSuffix}
                            onChange={(e) => setInvoiceSuffix(e.target.value)}
                            maxLength={50}
                        />
                    </Group>
                    <NumberInput
                        label={t`Invoice start number`}
                        description={t`First sequence number for new invoices`}
                        min={1}
                        value={invoiceStartNumber}
                        onChange={(v) => setInvoiceStartNumber(Number(v) || 1)}
                    />

                    <TextInput
                        label={t`Confirmation prefix`}
                        description={t`Prefix for purchase confirmation documents`}
                        placeholder="PC/"
                        value={confirmationPrefix}
                        onChange={(e) => setConfirmationPrefix(e.target.value)}
                        maxLength={50}
                    />
                    <NumberInput
                        label={t`Confirmation start number`}
                        description={t`First sequence number for new confirmations`}
                        min={1}
                        value={confirmationStartNumber}
                        onChange={(v) => setConfirmationStartNumber(Number(v) || 1)}
                    />

                    <Group>
                        <Button onClick={handleSave} loading={upsertMutation.isPending}>
                            {t`Save Seller Settings`}
                        </Button>
                    </Group>
                </Stack>
            </Card>
        </>
    );
};
