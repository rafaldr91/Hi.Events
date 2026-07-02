<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Gdpr;

use Carbon\Carbon;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Gdpr\GdprDataExportEmail;
use HiEvents\Repository\Interfaces\GdprExportTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Gdpr\DTO\RequestGdprExportDTO;
use HiEvents\Services\Infrastructure\TokenGenerator\TokenGeneratorService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class RequestGdprDataExportHandler
{
    private const TOKEN_EXPIRY_HOURS = 24;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly GdprExportTokenRepositoryInterface $gdprExportTokenRepository,
        private readonly TokenGeneratorService $tokenGeneratorService,
        private readonly Mailer $mailer,
        private readonly LoggerInterface $logger,
        private readonly DatabaseManager $databaseManager,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(RequestGdprExportDTO $dto): void
    {
        $email = strtolower($dto->email);

        $orders = $this->orderRepository->findWhere([
            [OrderDomainObjectAbstract::EMAIL, '=', $email],
            [OrderDomainObjectAbstract::STATUS, '=', OrderStatus::COMPLETED->name],
        ]);

        if ($orders->isEmpty()) {
            $this->logger->info('GDPR export requested for email with no completed orders', [
                'email' => $email,
            ]);
            return;
        }

        $this->databaseManager->transaction(function () use ($email) {
            $token = $this->tokenGeneratorService->generateToken(prefix: 'gdpr');

            $this->gdprExportTokenRepository->deleteWhere(['email' => $email]);
            $this->gdprExportTokenRepository->create([
                'email' => $email,
                'token' => $token,
                'expires_at' => Carbon::now()->addHours(self::TOKEN_EXPIRY_HOURS)->toDateTimeString(),
            ]);

            $this->mailer
                ->to($email)
                ->queue(new GdprDataExportEmail(email: $email, token: $token));
        });
    }
}
