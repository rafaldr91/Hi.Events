<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\KsefValidationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderKsefXmlDTO;
use HiEvents\Services\Application\Handlers\Order\GetOrderKsefXmlHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class DownloadOrderKsefXmlAction extends BaseAction
{
    public function __construct(
        private readonly GetOrderKsefXmlHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $orderId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $xml = $this->handler->handle(new GetOrderKsefXmlDTO(
                orderId: $orderId,
                eventId: $eventId,
            ));
        } catch (KsefValidationException $e) {
            throw ValidationException::withMessages(['ksef' => $e->getMessage()]);
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fa3-' . $orderId . '.xml"',
        ]);
    }
}
