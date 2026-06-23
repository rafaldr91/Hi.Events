<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\KsefStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\KSeF\SendInvoiceToKsefJob;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\SendOrderKsefCorrectionDTO;
use HiEvents\Services\Application\Handlers\Order\SendOrderKsefCorrectionHandler;
use HiEvents\Services\Domain\Invoice\CreateCorrectionInvoiceService;
use Illuminate\Support\Facades\Bus;
use Mockery as m;
use Tests\TestCase;

class SendOrderKsefCorrectionHandlerTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;
    private InvoiceRepositoryInterface $invoiceRepository;
    private CreateCorrectionInvoiceService $createCorrectionService;
    private SendOrderKsefCorrectionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->invoiceRepository = m::mock(InvoiceRepositoryInterface::class);
        $this->createCorrectionService = m::mock(CreateCorrectionInvoiceService::class);

        $this->handler = new SendOrderKsefCorrectionHandler(
            $this->orderRepository,
            $this->invoiceRepository,
            $this->createCorrectionService,
        );
    }

    public function test_dispatches_job_when_original_invoice_is_sent(): void
    {
        Bus::fake();

        $order = m::mock(OrderDomainObject::class);

        $originalInvoice = m::mock(InvoiceDomainObject::class);
        $originalInvoice->shouldReceive('getKsefStatus')->andReturn(KsefStatus::SENT->value);

        $correction = m::mock(InvoiceDomainObject::class);
        $correction->shouldReceive('getId')->andReturn(99);

        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'event_id' => 10])
            ->andReturn($order);

        $this->invoiceRepository
            ->shouldReceive('findLatestByDocumentTypeForOrder')
            ->with(1, 'invoice')
            ->andReturn($originalInvoice);

        $this->createCorrectionService
            ->shouldReceive('create')
            ->with($originalInvoice, $order)
            ->andReturn($correction);

        $this->invoiceRepository
            ->shouldReceive('updateFromArray')
            ->with(99, m::subset([
                'ksef_status'      => KsefStatus::PENDING->value,
                'ksef_retry_count' => 0,
            ]))
            ->once();

        $this->handler->handle(new SendOrderKsefCorrectionDTO(orderId: 1, eventId: 10));

        Bus::assertDispatched(SendInvoiceToKsefJob::class);
    }

    public function test_throws_when_order_not_found(): void
    {
        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(new SendOrderKsefCorrectionDTO(orderId: 999, eventId: 10));
    }

    public function test_throws_when_invoice_not_found(): void
    {
        $order = m::mock(OrderDomainObject::class);

        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($order);

        $this->invoiceRepository
            ->shouldReceive('findLatestByDocumentTypeForOrder')
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(new SendOrderKsefCorrectionDTO(orderId: 1, eventId: 10));
    }

    public function test_throws_when_original_invoice_not_sent_to_ksef(): void
    {
        $order = m::mock(OrderDomainObject::class);

        $originalInvoice = m::mock(InvoiceDomainObject::class);
        $originalInvoice->shouldReceive('getKsefStatus')->andReturn(KsefStatus::PENDING->value);

        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($order);

        $this->invoiceRepository
            ->shouldReceive('findLatestByDocumentTypeForOrder')
            ->andReturn($originalInvoice);

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->handle(new SendOrderKsefCorrectionDTO(orderId: 1, eventId: 10));
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
