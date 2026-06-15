<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\DTO\SendOrderKsefInvoiceDTO;
use HiEvents\Services\Application\Handlers\Order\SendOrderKsefInvoiceHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SendOrderKsefInvoiceAction extends BaseAction
{
    public function __construct(
        private readonly SendOrderKsefInvoiceHandler $handler,
    ) {
    }

    public function __invoke(Request $request, int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->handler->handle(new SendOrderKsefInvoiceDTO(
                orderId: $orderId,
                eventId: $eventId,
            ));
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['ksef' => $e->getMessage()]);
        }

        return $this->jsonResponse(['message' => __('Invoice queued for KSeF submission')]);
    }
}
