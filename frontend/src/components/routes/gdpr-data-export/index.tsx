import {t} from "@lingui/macro";
import {Alert, Button, Stack, Text, TextInput, Title} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useState} from "react";
import {CheckoutContent} from "../../layouts/Checkout/CheckoutContent";
import {Card} from "../../common/Card";
import {useRequestGdprDataExport} from "../../../mutations/useRequestGdprDataExport.ts";
import {showError} from "../../../utilites/notifications.tsx";

export default function GdprDataExport() {
    const form = useForm({
        initialValues: {email: ''},
        validate: {
            email: (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : t`Please enter a valid email address`),
        },
    });
    const mutation = useRequestGdprDataExport();
    const [submitted, setSubmitted] = useState(false);

    const handleSubmit = ({email}: { email: string }) => {
        mutation.mutate(email, {
            onSuccess: () => setSubmitted(true),
            onError: () => showError(t`Something went wrong. Please try again.`),
        });
    };

    return (
        <CheckoutContent>
            <Card>
                <Stack gap="md">
                    <div>
                        <Title order={3}>{t`Request Your Personal Data`}</Title>
                        <Text c="dimmed" fz="sm" mt={4}>
                            {t`Enter your email address and we will send you a link to download all personal data we hold for you.`}
                        </Text>
                    </div>

                    {submitted ? (
                        <Alert color="green" variant="light">
                            {t`If we have data associated with this email, you will receive a download link shortly.`}
                        </Alert>
                    ) : (
                        <form onSubmit={form.onSubmit(handleSubmit)}>
                            <Stack gap="sm">
                                <TextInput
                                    label={t`Email address`}
                                    type="email"
                                    placeholder="you@example.com"
                                    required
                                    {...form.getInputProps('email')}
                                />
                                <Button
                                    type="submit"
                                    loading={mutation.isPending}
                                >
                                    {t`Send Data Export Link`}
                                </Button>
                            </Stack>
                        </form>
                    )}
                </Stack>
            </Card>
        </CheckoutContent>
    );
}
