<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\DTO\SendOrderKsefCorrectionDTO;
use HiEvents\Services\Application\Handlers\Order\SendOrderKsefCorrectionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SendOrderKsefCorrectionAction extends BaseAction
{
    public function __construct(
        private readonly SendOrderKsefCorrectionHandler $handler,
    ) {
    }

    public function __invoke(Request $request, int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->handler->handle(new SendOrderKsefCorrectionDTO(
                orderId: $orderId,
                eventId: $eventId,
            ));
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['ksef' => $e->getMessage()]);
        }

        return $this->jsonResponse(['message' => __('Correction queued for KSeF submission')]);
    }
}
