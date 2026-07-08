<?php

namespace Tests\Unit\Services\Application\Handlers\Reports;

use HiEvents\DomainObjects\Enums\ReportTypes;
use HiEvents\Services\Application\Handlers\Reports\DTO\GetReportDTO;
use HiEvents\Services\Application\Handlers\Reports\GetReportHandler;
use HiEvents\Services\Domain\Report\AbstractReportService;
use HiEvents\Services\Domain\Report\Factory\ReportServiceFactory;
use Mockery as m;
use Tests\TestCase;

class GetReportHandlerTest extends TestCase
{
    private ReportServiceFactory $reportServiceFactory;
    private GetReportHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportServiceFactory = m::mock(ReportServiceFactory::class);
        $this->handler = new GetReportHandler($this->reportServiceFactory);
    }

    public function testHandleForwardsNullPaymentProviders(): void
    {
        $dto = new GetReportDTO(
            eventId: 1,
            reportType: ReportTypes::DAILY_SALES_REPORT,
            startDate: '2024-01-01',
            endDate: '2024-01-31',
            paymentProviders: null,
        );

        $mockService = m::mock(AbstractReportService::class);
        $mockService
            ->shouldReceive('generateReport')
            ->once()
            ->withArgs(function ($eventId, $start, $end, $providers) {
                return $eventId === 1
                    && $start->toDateString() === '2024-01-01'
                    && $end->toDateString() === '2024-01-31'
                    && $providers === null;
            })
            ->andReturn(collect([]));

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->with(ReportTypes::DAILY_SALES_REPORT)
            ->andReturn($mockService);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testHandleForwardsSingleProvider(): void
    {
        $dto = new GetReportDTO(
            eventId: 2,
            reportType: ReportTypes::DAILY_SALES_REPORT,
            startDate: '2024-02-01',
            endDate: '2024-02-28',
            paymentProviders: ['STRIPE'],
        );

        $mockService = m::mock(AbstractReportService::class);
        $mockService
            ->shouldReceive('generateReport')
            ->once()
            ->withArgs(function ($eventId, $start, $end, $providers) {
                return $eventId === 2 && $providers === ['STRIPE'];
            })
            ->andReturn(collect([]));

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockService);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testHandleForwardsMultipleProviders(): void
    {
        $dto = new GetReportDTO(
            eventId: 3,
            reportType: ReportTypes::DAILY_SALES_REPORT,
            startDate: '2024-03-01',
            endDate: '2024-03-31',
            paymentProviders: ['STRIPE', 'OFFLINE'],
        );

        $mockService = m::mock(AbstractReportService::class);
        $mockService
            ->shouldReceive('generateReport')
            ->once()
            ->withArgs(function ($eventId, $start, $end, $providers) {
                return $eventId === 3 && $providers === ['STRIPE', 'OFFLINE'];
            })
            ->andReturn(collect([]));

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockService);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testHandleForwardsOtherProvider(): void
    {
        $dto = new GetReportDTO(
            eventId: 4,
            reportType: ReportTypes::DAILY_SALES_REPORT,
            startDate: '2024-04-01',
            endDate: '2024-04-30',
            paymentProviders: ['OTHER'],
        );

        $mockService = m::mock(AbstractReportService::class);
        $mockService
            ->shouldReceive('generateReport')
            ->once()
            ->withArgs(function ($eventId, $start, $end, $providers) {
                return $eventId === 4 && $providers === ['OTHER'];
            })
            ->andReturn(collect([]));

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockService);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testHandleWorksWithNullDates(): void
    {
        $dto = new GetReportDTO(
            eventId: 5,
            reportType: ReportTypes::DAILY_SALES_REPORT,
            startDate: null,
            endDate: null,
            paymentProviders: null,
        );

        $mockService = m::mock(AbstractReportService::class);
        $mockService
            ->shouldReceive('generateReport')
            ->once()
            ->withArgs(function ($eventId, $start, $end, $providers) {
                return $eventId === 5
                    && $start === null
                    && $end === null
                    && $providers === null;
            })
            ->andReturn(collect([]));

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockService);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
