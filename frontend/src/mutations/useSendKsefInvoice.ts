import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {orderClient} from "../api/order.client.ts";

export const useSendKsefInvoice = () => {
    return useMutation({
        mutationFn: ({eventId, orderId}: {
            eventId: IdParam,
            orderId: IdParam,
        }) => orderClient.sendKsefInvoice(eventId, orderId)
    });
}
