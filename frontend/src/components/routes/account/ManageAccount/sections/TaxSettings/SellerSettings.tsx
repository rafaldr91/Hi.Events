import {useEffect, useState} from 'react';
import {t} from '@lingui/macro';
import {Button, Group, Stack, TextInput} from '@mantine/core';
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

    const vatSettingQuery = useGetAccountVatSetting(account.id);
    const upsertMutation = useUpsertAccountVatSetting(account.id);

    useEffect(() => {
        const data = vatSettingQuery.data;
        if (data) {
            setNip(data.vat_number || '');
            setBusinessName(data.business_name || '');
            setBusinessAddress(data.business_address || '');
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
