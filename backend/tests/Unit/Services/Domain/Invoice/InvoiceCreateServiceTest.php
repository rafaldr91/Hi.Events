<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Invoice;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Invoice\InvoiceCreateService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class InvoiceCreateServiceTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;
    private InvoiceRepositoryInterface|MockInterface $invoiceRepository;
    private AccountVatSettingRepositoryInterface|MockInterface $accountVatSettingRepository;
    private InvoiceCreateService $service;

    private const EVENT_ID = 1;
    private const ACCOUNT_ID = 10;
    private const ORDER_ID = 100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $this->accountVatSettingRepository = Mockery::mock(AccountVatSettingRepositoryInterface::class);

        $this->service = new InvoiceCreateService(
            orderRepository: $this->orderRepository,
            invoiceRepository: $this->invoiceRepository,
            accountVatSettingRepository: $this->accountVatSettingRepository,
        );
    }

    private function makeEventSettings(
        ?string $invoiceNumberFormat = null,
        ?string $invoicePrefix = null,
        ?string $invoiceSuffix = null,
        int $invoiceStartNumber = 1,
        ?string $confirmationPrefix = null,
        int $confirmationStartNumber = 1,
    ): EventSettingDomainObject {
        $settings = new EventSettingDomainObject();
        $settings->setInvoiceNumberFormat($invoiceNumberFormat);
        $settings->setInvoicePrefix($invoicePrefix);
        $settings->setInvoiceSuffix($invoiceSuffix);
        $settings->setInvoiceStartNumber($invoiceStartNumber);
        $settings->setConfirmationPrefix($confirmationPrefix);
        $settings->setConfirmationStartNumber($confirmationStartNumber);
        return $settings;
    }

    private function makeAccountVatSetting(
        ?string $invoiceNumberFormat = null,
        ?string $invoicePrefix = null,
        ?string $invoiceSuffix = null,
        int $invoiceStartNumber = 1,
        ?string $confirmationPrefix = null,
        int $confirmationStartNumber = 1,
    ): AccountVatSettingDomainObject {
        $setting = new AccountVatSettingDomainObject();
        $setting->setInvoiceNumberFormat($invoiceNumberFormat);
        $setting->setInvoicePrefix($invoicePrefix);
        $setting->setInvoiceSuffix($invoiceSuffix);
        $setting->setInvoiceStartNumber($invoiceStartNumber);
        $setting->setConfirmationPrefix($confirmationPrefix);
        $setting->setConfirmationStartNumber($confirmationStartNumber);
        return $setting;
    }

    private function makeOrder(string $buyerType = 'company', ?string $companyNip = '1234567890', ?EventSettingDomainObject $eventSettings = null): OrderDomainObject
    {
        $event = (new EventDomainObject())
            ->setId(self::EVENT_ID)
            ->setAccountId(self::ACCOUNT_ID)
            ->setEventSettings($eventSettings ?? $this->makeEventSettings());

        return (new OrderDomainObject())
            ->setId(self::ORDER_ID)
            ->setBuyerType($buyerType)
            ->setCompanyNip($companyNip)
            ->setEvent($event)
            ->setOrderItems(new Collection([]))
            ->setTaxesAndFeesRollup([])
            ->setTotalGross(100.0)
            ->setStatus('completed');
    }

    private function setupOrderRepository(OrderDomainObject $order): void
    {
        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->twice()
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('findById')
            ->once()
            ->with(self::ORDER_ID)
            ->andReturn($order);
    }

    private function setupNoExistingInvoice(): void
    {
        $this->invoiceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);
    }

    private function captureCreatedInvoice(): array
    {
        $captured = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$captured) {
                $captured = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());
        return $captured;
    }

    public function test_invoice_uses_account_level_sequence_when_event_has_no_format(): void
    {
        Carbon::setTestNow('2026-06-15');

        $accountVatSetting = $this->makeAccountVatSetting(
            invoiceNumberFormat: null,
            invoicePrefix: 'FV/',
            invoiceStartNumber: 1,
        );
        $order = $this->makeOrder(eventSettings: $this->makeEventSettings(
            invoiceNumberFormat: null,
        ));

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->with(self::ACCOUNT_ID)
            ->andReturn($accountVatSetting);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForAccount')
            ->with(self::ACCOUNT_ID, 'invoice', null, null)
            ->andReturn(4);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame(5, $invoiceData['sequence_number']);
        $this->assertSame('FV/5', $invoiceData['invoice_number']);

        Carbon::setTestNow();
    }

    public function test_invoice_uses_per_event_sequence_when_event_has_format(): void
    {
        Carbon::setTestNow('2026-06-15');

        $order = $this->makeOrder(eventSettings: $this->makeEventSettings(
            invoiceNumberFormat: '{number}/{year}',
            invoicePrefix: 'EVT/',
            invoiceStartNumber: 1,
        ));

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn(null);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForEvent')
            ->with(self::EVENT_ID, 'invoice', null, 2026)
            ->andReturn(2);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame(3, $invoiceData['sequence_number']);
        $this->assertSame('EVT/3/2026', $invoiceData['invoice_number']);

        Carbon::setTestNow();
    }

    public function test_invoice_sequence_respects_account_start_number(): void
    {
        $accountVatSetting = $this->makeAccountVatSetting(invoiceStartNumber: 100);
        $order = $this->makeOrder(eventSettings: $this->makeEventSettings());

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn($accountVatSetting);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForAccount')
            ->andReturn(0);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame(100, $invoiceData['sequence_number']);
    }

    public function test_invoice_sequence_increments_beyond_start_number(): void
    {
        $accountVatSetting = $this->makeAccountVatSetting(invoiceStartNumber: 1);
        $order = $this->makeOrder(eventSettings: $this->makeEventSettings());

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn($accountVatSetting);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForAccount')
            ->andReturn(50);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame(51, $invoiceData['sequence_number']);
    }

    public function test_confirmation_always_uses_account_level_sequence(): void
    {
        $accountVatSetting = $this->makeAccountVatSetting(
            confirmationPrefix: 'PC/',
            confirmationStartNumber: 1,
        );
        $order = $this->makeOrder(buyerType: 'individual', companyNip: null);

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn($accountVatSetting);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForAccount')
            ->with(self::ACCOUNT_ID, 'confirmation', null, null)
            ->andReturn(9);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame('confirmation', $invoiceData['document_type']);
        $this->assertSame(10, $invoiceData['sequence_number']);
        $this->assertSame('PC/10', $invoiceData['invoice_number']);
    }

    public function test_invoice_with_month_year_format_passes_correct_filters_to_repository(): void
    {
        Carbon::setTestNow('2026-06-15');

        $accountVatSetting = $this->makeAccountVatSetting(
            invoiceNumberFormat: '{number}/{month}/{year}',
        );
        $order = $this->makeOrder(eventSettings: $this->makeEventSettings());

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn($accountVatSetting);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForAccount')
            ->with(self::ACCOUNT_ID, 'invoice', 6, 2026)
            ->once()
            ->andReturn(0);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame('1/06/2026', $invoiceData['invoice_number']);

        Carbon::setTestNow();
    }

    public function test_invoice_fallback_when_no_account_vat_setting(): void
    {
        $order = $this->makeOrder(eventSettings: $this->makeEventSettings(
            invoiceNumberFormat: null,
        ));

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn(null);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForEvent')
            ->with(self::EVENT_ID, 'invoice', null, null)
            ->andReturn(0);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame(1, $invoiceData['sequence_number']);
        $this->assertSame('1', $invoiceData['invoice_number']);
    }

    public function test_invoice_number_format_with_prefix_and_suffix(): void
    {
        Carbon::setTestNow('2026-06-15');

        $accountVatSetting = $this->makeAccountVatSetting(
            invoiceNumberFormat: '{number}/{year}',
            invoicePrefix: 'FV/',
            invoiceSuffix: '/PL',
            invoiceStartNumber: 1,
        );
        $order = $this->makeOrder(eventSettings: $this->makeEventSettings());

        $this->setupOrderRepository($order);
        $this->setupNoExistingInvoice();
        $this->accountVatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn($accountVatSetting);
        $this->invoiceRepository
            ->shouldReceive('findMaxSequenceNumberForAccount')
            ->andReturn(2);

        $invoiceData = [];
        $this->invoiceRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$invoiceData) {
                $invoiceData = $data;
                return true;
            })
            ->andReturn(new InvoiceDomainObject());

        $this->service->createInvoiceForOrder(self::ORDER_ID);

        $this->assertSame('FV/3/2026/PL', $invoiceData['invoice_number']);

        Carbon::setTestNow();
    }
}
