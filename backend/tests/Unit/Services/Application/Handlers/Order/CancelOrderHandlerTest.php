<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Application\Handlers\Order\CancelOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CancelOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\SendOrderKsefCorrectionDTO;
use HiEvents\Services\Application\Handlers\Order\SendOrderKsefCorrectionHandler;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler;
use HiEvents\Services\Domain\Order\OrderCancelService;
use Illuminate\Database\DatabaseManager;
use Mockery as m;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class CancelOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;
    private OrderCancelService $orderCancelService;
    private DatabaseManager $databaseManager;
    private RefundOrderHandler $refundOrderHandler;
    private SendOrderKsefCorrectionHandler $ksefCorrectionHandler;
    private LoggerInterface $logger;
    private CancelOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository        = m::mock(OrderRepositoryInterface::class);
        $this->orderCancelService     = m::mock(OrderCancelService::class);
        $this->refundOrderHandler     = m::mock(RefundOrderHandler::class);
        $this->ksefCorrectionHandler  = m::mock(SendOrderKsefCorrectionHandler::class);
        $this->logger                 = m::mock(LoggerInterface::class);

        $this->databaseManager = m::mock(DatabaseManager::class);
        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->handler = new CancelOrderHandler(
            $this->orderCancelService,
            $this->orderRepository,
            $this->databaseManager,
            $this->refundOrderHandler,
            $this->ksefCorrectionHandler,
            $this->logger,
        );
    }

    public function test_calls_ksef_correction_handler_when_flag_is_true(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(40);
        $order->shouldReceive('isOrderCancelled')->andReturn(false);
        $order->shouldReceive('isRefundable')->andReturn(false);

        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->orderCancelService->shouldReceive('cancelOrder')->once();

        $this->ksefCorrectionHandler->shouldReceive('handle')
            ->once()
            ->with(m::on(fn(SendOrderKsefCorrectionDTO $dto) => $dto->orderId === 40 && $dto->eventId === 1));

        $this->handler->handle(new CancelOrderDTO(
            eventId: 1,
            orderId: 40,
            refund: false,
            sendKsefCorrection: true,
        ));
    }

    public function test_does_not_call_ksef_correction_handler_when_flag_is_false(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(40);
        $order->shouldReceive('isOrderCancelled')->andReturn(false);
        $order->shouldReceive('isRefundable')->andReturn(false);

        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->orderCancelService->shouldReceive('cancelOrder')->once();

        $this->ksefCorrectionHandler->shouldNotReceive('handle');

        $this->handler->handle(new CancelOrderDTO(
            eventId: 1,
            orderId: 40,
            refund: false,
            sendKsefCorrection: false,
        ));
    }

    public function test_cancellation_succeeds_even_when_ksef_correction_throws(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(40);
        $order->shouldReceive('isOrderCancelled')->andReturn(false);
        $order->shouldReceive('isRefundable')->andReturn(false);

        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->orderCancelService->shouldReceive('cancelOrder')->once();

        $this->ksefCorrectionHandler->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('KSeF error'));

        $this->logger->shouldReceive('warning')->once();

        $result = $this->handler->handle(new CancelOrderDTO(
            eventId: 1,
            orderId: 40,
            refund: false,
            sendKsefCorrection: true,
        ));

        $this->assertNotNull($result);
    }
}
