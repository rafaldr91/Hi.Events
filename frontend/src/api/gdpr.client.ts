import {publicApi} from "./public-client.ts";
import {GenericDataResponse} from "../types.ts";

export const gdprClient = {
    requestDataExport: async (email: string) => {
        const response = await publicApi.post<GenericDataResponse<{ message: string }>>('gdpr/export/request', {email});
        return response.data;
    },
}
