<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use HiEvents\Exceptions\OrderAlreadyAnonymizedException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class AnonymizeOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface        $orderRepository,
        private readonly AttendeeRepositoryInterface     $attendeeRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswersRepository,
    )
    {
    }

    /**
     * @throws OrderAlreadyAnonymizedException
     */
    public function handle(int $orderId, int $eventId): void
    {
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::ID => $orderId,
            OrderDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        if ($order->getAnonymizedAt() !== null) {
            throw new OrderAlreadyAnonymizedException(__('This order has already been anonymized'));
        }

        DB::transaction(function () use ($orderId) {
            $anonymizedEmail = 'deleted-' . Str::uuid() . '@anonymized.invalid';

            $this->orderRepository->updateFromArray($orderId, [
                OrderDomainObjectAbstract::FIRST_NAME => 'DELETED',
                OrderDomainObjectAbstract::LAST_NAME => 'DELETED',
                OrderDomainObjectAbstract::EMAIL => $anonymizedEmail,
                OrderDomainObjectAbstract::ADDRESS => null,
                OrderDomainObjectAbstract::OPTED_INTO_MARKETING_AT => null,
                OrderDomainObjectAbstract::DATA_PROCESSING_ACCEPTED_AT => null,
                OrderDomainObjectAbstract::ANONYMIZED_AT => Carbon::now(),
            ]);

            $this->attendeeRepository->updateWhere(
                attributes: [
                    AttendeeDomainObjectAbstract::FIRST_NAME => 'DELETED',
                    AttendeeDomainObjectAbstract::LAST_NAME => 'DELETED',
                    AttendeeDomainObjectAbstract::EMAIL => 'deleted-' . Str::uuid() . '@anonymized.invalid',
                    AttendeeDomainObjectAbstract::ANONYMIZED_AT => Carbon::now(),
                ],
                where: [
                    AttendeeDomainObjectAbstract::ORDER_ID => $orderId,
                ],
            );

            $this->questionAnswersRepository->deleteWhere([
                QuestionAnswerDomainObjectAbstract::ORDER_ID => $orderId,
            ]);
        });
    }
}
