import {useMutation} from "@tanstack/react-query";
import {gdprClient} from "../api/gdpr.client.ts";

export const useRequestGdprDataExport = () => {
    return useMutation({
        mutationFn: (email: string) => gdprClient.requestDataExport(email),
    });
}
