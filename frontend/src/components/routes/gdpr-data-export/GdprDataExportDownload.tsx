import {t} from "@lingui/macro";
import {Alert, Button, Stack, Text, Title} from "@mantine/core";
import {useEffect, useState} from "react";
import {useParams} from "react-router";
import {CheckoutContent} from "../../layouts/Checkout/CheckoutContent";
import {Card} from "../../common/Card";
import {getConfig} from "../../../utilites/config.ts";

export default function GdprDataExportDownload() {
    const {token} = useParams<{ token: string }>();
    const [error, setError] = useState(false);

    const handleDownload = () => {
        if (!token) return;
        const apiUrl = getConfig('VITE_API_URL_CLIENT');
        window.location.href = `${apiUrl}/public/gdpr/export/${token}`;
    };

    useEffect(() => {
        if (token) {
            handleDownload();
        } else {
            setError(true);
        }
    }, [token]);

    return (
        <CheckoutContent>
            <Card>
                <Stack gap="md">
                    <Title order={3}>{t`Download Your Personal Data`}</Title>

                    {error ? (
                        <Alert color="red" variant="light">
                            {t`Invalid or expired link. Please request a new one.`}
                        </Alert>
                    ) : (
                        <>
                            <Text c="dimmed" fz="sm">
                                {t`Your download should start automatically. If it does not, click the button below.`}
                            </Text>
                            <Button onClick={handleDownload} variant="outline">
                                {t`Download My Data`}
                            </Button>
                        </>
                    )}
                </Stack>
            </Card>
        </CheckoutContent>
    );
}
