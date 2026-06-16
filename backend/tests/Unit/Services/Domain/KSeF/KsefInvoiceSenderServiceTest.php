<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\KSeF;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\KsefValidationException;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Domain\KSeF\KsefInvoiceSenderService;
use HiEvents\Services\Domain\KSeF\KsefXmlGenerationService;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class KsefInvoiceSenderServiceTest extends TestCase
{
    private KsefXmlGenerationService|MockInterface $xmlGenerationService;
    private AccountVatSettingRepositoryInterface|MockInterface $vatSettingRepository;
    private LoggerInterface|MockInterface $logger;
    private KsefInvoiceSenderService $service;

    private const SELLER_NIP = '1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $this->xmlGenerationService = Mockery::mock(KsefXmlGenerationService::class);
        $this->vatSettingRepository = Mockery::mock(AccountVatSettingRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();

        $vatSetting = (new AccountVatSettingDomainObject())->setVatNumber(self::SELLER_NIP);
        $this->vatSettingRepository->shouldReceive('findByAccountId')->andReturn($vatSetting)->byDefault();

        $this->service = new KsefInvoiceSenderService(
            $this->xmlGenerationService,
            $this->vatSettingRepository,
            $this->logger,
        );
    }

    private function makeInvoice(): InvoiceDomainObject
    {
        return (new InvoiceDomainObject())->setId(42)->setOrderId(10)->setAccountId(1);
    }

    private function makeOrder(): OrderDomainObject
    {
        return (new OrderDomainObject())->setId(10)->setCompanyNip(self::SELLER_NIP);
    }

    public function test_returns_failure_for_xml_validation_exception(): void
    {
        $this->xmlGenerationService
            ->shouldReceive('generate')
            ->once()
            ->andThrow(new KsefValidationException('Seller NIP is not configured'));

        $result = $this->service->send($this->makeInvoice(), $this->makeOrder());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Seller NIP', $result->errorMessage);
        $this->assertFalse($result->isRetryable);
    }

    public function test_returns_failure_when_seller_nip_missing(): void
    {
        $this->vatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn((new AccountVatSettingDomainObject())->setVatNumber(''));

        $result = $this->service->send($this->makeInvoice(), $this->makeOrder());

        $this->assertFalse($result->success);
        $this->assertFalse($result->isRetryable);
    }

    public function test_returns_failure_when_account_vat_settings_missing(): void
    {
        $this->vatSettingRepository
            ->shouldReceive('findByAccountId')
            ->andReturn(null);

        $result = $this->service->send($this->makeInvoice(), $this->makeOrder());

        $this->assertFalse($result->success);
        $this->assertFalse($result->isRetryable);
    }

    public function test_ksef_validation_failure_is_never_retryable(): void
    {
        $this->xmlGenerationService
            ->shouldReceive('generate')
            ->andThrow(new KsefValidationException('Invoice has no line items'));

        $result = $this->service->send($this->makeInvoice(), $this->makeOrder());

        $this->assertFalse($result->isRetryable);
    }
}
