import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {orderClient} from "../api/order.client.ts";
import {GET_ORDER_QUERY_KEY} from "../queries/useGetOrder.ts";

export const useAnonymizeOrder = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, orderId}: { eventId: IdParam, orderId: IdParam }) =>
            orderClient.anonymizeOrder(eventId, orderId),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_ORDER_QUERY_KEY, variables.orderId]
            });
        }
    });
}
