<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Gdpr;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use HiEvents\DomainObjects\GdprExportTokenDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\InvalidGdprExportTokenException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\GdprExportTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use Illuminate\Support\Collection;

class DownloadGdprDataExportHandler
{
    public function __construct(
        private readonly GdprExportTokenRepositoryInterface $gdprExportTokenRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswerRepository,
    ) {
    }

    /**
     * @throws InvalidGdprExportTokenException
     */
    public function handle(string $token): array
    {
        $tokenRecord = $this->validateAndFetchToken($token);

        $email = $tokenRecord->getEmail();

        $orders = $this->orderRepository
            ->loadRelation(AttendeeDomainObject::class)
            ->findWhere([
                [OrderDomainObjectAbstract::EMAIL, '=', $email],
                [OrderDomainObjectAbstract::STATUS, '=', OrderStatus::COMPLETED->name],
            ]);

        return [
            'exported_at' => Carbon::now()->toIso8601String(),
            'email' => $email,
            'orders' => $orders->map(fn(OrderDomainObject $order) => $this->formatOrder($order))->values()->toArray(),
        ];
    }

    /**
     * @throws InvalidGdprExportTokenException
     */
    private function validateAndFetchToken(string $token): GdprExportTokenDomainObject
    {
        $tokenRecord = $this->gdprExportTokenRepository->findFirstWhere(['token' => $token]);

        if (!$tokenRecord) {
            throw new InvalidGdprExportTokenException(__('Invalid or expired link. Please request a new one.'));
        }

        if ((new Carbon($tokenRecord->getExpiresAt()))->isPast()) {
            throw new InvalidGdprExportTokenException(__('This link has expired. Please request a new one.'));
        }

        return $tokenRecord;
    }

    private function formatOrder(OrderDomainObject $order): array
    {
        $questionAnswers = $this->questionAnswerRepository->findWhere([
            QuestionAnswerDomainObjectAbstract::ORDER_ID => $order->getId(),
        ]);

        return [
            'id' => $order->getPublicId(),
            'created_at' => $order->getCreatedAt(),
            'first_name' => $order->getFirstName(),
            'last_name' => $order->getLastName(),
            'email' => $order->getEmail(),
            'address' => $order->getAddress(),
            'buyer_type' => $order->getBuyerType(),
            'company_name' => $order->getCompanyName(),
            'company_nip' => $order->getCompanyNip(),
            'consent' => [
                'opted_into_marketing_at' => $order->getOptedIntoMarketingAt(),
                'data_processing_accepted_at' => $order->getDataProcessingAcceptedAt(),
            ],
            'attendees' => $order->getAttendees()?->map(fn(AttendeeDomainObject $a) => [
                'first_name' => $a->getFirstName(),
                'last_name' => $a->getLastName(),
                'email' => $a->getEmail(),
            ])->values()->toArray() ?? [],
            'question_answers' => $questionAnswers->map(fn(QuestionAnswerDomainObject $qa) => [
                'question_id' => $qa->getQuestionId(),
                'answer' => $qa->getAnswer(),
            ])->values()->toArray(),
        ];
    }
}
