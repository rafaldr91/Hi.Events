<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Report;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Report\OrganizerReports\RevenueSummaryReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\Assert;
use Tests\TestCase;

class RevenueSummaryReportTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private int $organizerId;
    private int $eventId;
    private int $accountId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $this->organizerId = DB::table('organizers')->insertGetId([
            'name' => 'Test Org',
            'email' => 'org@test.com',
            'account_id' => $this->accountId,
            'timezone' => 'UTC',
            'currency' => 'PLN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->eventId = $this->insertEvent('PLN');
    }

    public function test_aggregates_gross_sales_tax_fee_and_order_count(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'total_tax' => 10.00, 'total_fee' => 5.00]);
        $this->insertOrder(['total_gross' => 200.00, 'total_tax' => 20.00, 'total_fee' => 8.00]);

        $results = $this->runReport();
        $today = $this->findRowForDate($results, Carbon::today());

        $this->assertNotNull($today);
        $this->assertEquals(300.00, (float) $today->gross_sales);
        $this->assertEquals(30.00, (float) $today->total_tax);
        $this->assertEquals(13.00, (float) $today->total_fee);
        $this->assertEquals(2, (int) $today->order_count);
    }

    public function test_calculates_net_revenue_correctly(): void
    {
        // gross=200, tax=10 → net = 200 - 10 = 190
        $orderId = $this->insertOrder(['total_gross' => 200.00, 'total_tax' => 10.00, 'total_fee' => 5.00]);
        $this->insertRefund($orderId, 50.00);

        $results = $this->runReport();
        $today = $this->findRowForDate($results, Carbon::today());

        $this->assertNotNull($today);
        $this->assertEquals(200.00, (float) $today->gross_sales);
        $this->assertEquals(50.00, (float) $today->total_refunded);
        $this->assertEquals(190.00, (float) $today->net_revenue);
    }

    public function test_excludes_non_completed_orders(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'status' => 'COMPLETED']);
        $this->insertOrder(['total_gross' => 500.00, 'status' => 'PENDING']);

        $results = $this->runReport();
        $today = $this->findRowForDate($results, Carbon::today());

        $this->assertNotNull($today);
        $this->assertEquals(100.00, (float) $today->gross_sales);
        $this->assertEquals(1, (int) $today->order_count);
    }

    public function test_fills_zero_for_days_with_no_orders(): void
    {
        $results = $this->runReport();

        $this->assertGreaterThan(1, $results->count());

        foreach ($results as $row) {
            $this->assertNotNull($row->date);
            $this->assertEquals(0.00, (float) $row->gross_sales);
            $this->assertEquals(0, (int) $row->order_count);
        }
    }

    public function test_returns_results_ordered_by_date_descending(): void
    {
        $yesterday = Carbon::yesterday();
        $twoDaysAgo = Carbon::today()->subDays(2);

        $this->insertOrder(['created_at' => $yesterday, 'updated_at' => $yesterday]);
        $this->insertOrder(['created_at' => $twoDaysAgo, 'updated_at' => $twoDaysAgo]);

        $results = $this->runReport();
        $dates = $results->pluck('date')->map(fn($d) => Carbon::parse($d))->values();

        for ($i = 0; $i < $dates->count() - 1; $i++) {
            $this->assertTrue(
                $dates[$i]->gte($dates[$i + 1]),
                "Expected results to be ordered by date descending"
            );
        }
    }

    public function test_excludes_orders_outside_date_range(): void
    {
        $tenDaysAgo = Carbon::today()->subDays(10);
        $this->insertOrder(['total_gross' => 999.00, 'created_at' => $tenDaysAgo, 'updated_at' => $tenDaysAgo]);
        $this->insertOrder(['total_gross' => 100.00]);

        $results = $this->runReport(startDate: Carbon::today()->subDays(3));
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(100.00, $total);
    }

    public function test_excludes_orders_from_other_organizers(): void
    {
        $otherOrganizerId = DB::table('organizers')->insertGetId([
            'name' => 'Other Org', 'email' => 'other@test.com',
            'account_id' => $this->accountId, 'timezone' => 'UTC',
            'currency' => 'PLN', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $otherEventId = DB::table('events')->insertGetId([
            'title' => 'Other Event', 'organizer_id' => $otherOrganizerId,
            'account_id' => $this->accountId, 'user_id' => $this->userId,
            'currency' => 'PLN', 'timezone' => 'UTC', 'status' => 'published',
            'short_id' => Str::random(8), 'start_date' => now(), 'end_date' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->insertOrder(['total_gross' => 500.00, 'event_id' => $otherEventId]);
        $this->insertOrder(['total_gross' => 100.00]);

        $results = $this->runReport();
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(100.00, $total);
    }

    public function test_filters_company_orders_only(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'buyer_type' => 'company']);
        $this->insertOrder(['total_gross' => 200.00, 'buyer_type' => 'individual']);

        $results = $this->runReport(buyerTypes: ['company']);
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(100.00, $total);
    }

    public function test_filters_individual_orders_only(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'buyer_type' => 'company']);
        $this->insertOrder(['total_gross' => 200.00, 'buyer_type' => 'individual']);

        $results = $this->runReport(buyerTypes: ['individual']);
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(200.00, $total);
    }

    public function test_returns_all_orders_when_buyer_types_is_null(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'buyer_type' => 'company']);
        $this->insertOrder(['total_gross' => 200.00, 'buyer_type' => 'individual']);

        $results = $this->runReport(buyerTypes: null);
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(300.00, $total);
    }

    public function test_filters_stripe_orders_only(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'payment_provider' => 'STRIPE']);
        $this->insertOrder(['total_gross' => 200.00, 'payment_provider' => 'OFFLINE']);

        $results = $this->runReport(paymentProviders: ['STRIPE']);
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(100.00, $total);
    }

    public function test_filters_offline_orders_only(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'payment_provider' => 'STRIPE']);
        $this->insertOrder(['total_gross' => 200.00, 'payment_provider' => 'OFFLINE']);

        $results = $this->runReport(paymentProviders: ['OFFLINE']);
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(200.00, $total);
    }

    public function test_filters_other_payment_provider_includes_null_payment_provider(): void
    {
        $this->insertOrder(['total_gross' => 75.00, 'payment_provider' => null]);
        $this->insertOrder(['total_gross' => 100.00, 'payment_provider' => 'STRIPE']);

        $otherResults = $this->runReport(paymentProviders: ['OTHER']);
        $this->assertEquals(75.00, (float) $otherResults->sum(fn($r) => (float) $r->gross_sales));

        $stripeResults = $this->runReport(paymentProviders: ['STRIPE']);
        $this->assertEquals(100.00, (float) $stripeResults->sum(fn($r) => (float) $r->gross_sales));
    }

    public function test_filters_multiple_payment_providers(): void
    {
        $this->insertOrder(['total_gross' => 100.00, 'payment_provider' => 'STRIPE']);
        $this->insertOrder(['total_gross' => 200.00, 'payment_provider' => 'OFFLINE']);
        $this->insertOrder(['total_gross' => 50.00, 'payment_provider' => null]);

        $results = $this->runReport(paymentProviders: ['STRIPE', 'OFFLINE']);
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(300.00, $total);
    }

    public function test_filters_by_event_currency(): void
    {
        $eurEventId = $this->insertEvent('EUR');
        $this->insertOrder(['total_gross' => 100.00, 'event_id' => $this->eventId]);
        $this->insertOrder(['total_gross' => 999.00, 'event_id' => $eurEventId]);

        $results = $this->runReport(currency: 'PLN');
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(100.00, $total);
    }

    public function test_excludes_soft_deleted_orders(): void
    {
        $this->insertOrder(['total_gross' => 500.00, 'deleted_at' => now()]);
        $this->insertOrder(['total_gross' => 100.00]);

        $results = $this->runReport();
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(100.00, $total);
    }

    public function test_excludes_orders_on_soft_deleted_events(): void
    {
        $deletedEventId = $this->insertEvent('PLN', deletedAt: now());
        $this->insertOrder(['total_gross' => 500.00, 'event_id' => $deletedEventId]);
        $this->insertOrder(['total_gross' => 100.00]);

        $results = $this->runReport();
        $total = $results->sum(fn($r) => (float) $r->gross_sales);

        $this->assertEquals(100.00, $total);
    }

    public function test_refunds_excluded_when_order_is_soft_deleted(): void
    {
        $orderId = $this->insertOrder(['total_gross' => 200.00, 'deleted_at' => now()]);
        $this->insertRefund($orderId, 50.00);

        $results = $this->runReport();
        $total = $results->sum(fn($r) => (float) $r->total_refunded);

        $this->assertEquals(0.00, $total);
    }

    private function runReport(
        ?array  $paymentProviders = null,
        ?array  $buyerTypes = null,
        ?string $currency = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): \Illuminate\Support\Collection {
        return app()->make(RevenueSummaryReport::class)->generateReport(
            organizerId: $this->organizerId,
            currency: $currency,
            startDate: $startDate ?? Carbon::now()->subDays(5),
            endDate: $endDate ?? Carbon::now()->addDay(),
            paymentProviders: $paymentProviders,
            buyerTypes: $buyerTypes,
        );
    }

    private function insertOrder(array $overrides = []): int
    {
        return DB::table('orders')->insertGetId(array_merge([
            'event_id' => $this->eventId,
            'status' => 'COMPLETED',
            'total_gross' => 100.00,
            'total_tax' => 10.00,
            'total_fee' => 5.00,
            'total_refunded' => 0.00,
            'total_before_additions' => 85.00,
            'buyer_type' => 'individual',
            'payment_provider' => 'STRIPE',
            'short_id' => Str::random(8),
            'public_id' => Str::uuid()->toString(),
            'session_id' => Str::random(40),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@test.com',
            'currency' => 'PLN',
            'point_in_time_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function insertRefund(int $orderId, float $amount, string $paymentProvider = 'STRIPE'): void
    {
        DB::table('order_refunds')->insert([
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => 'PLN',
            'payment_provider' => $paymentProvider,
            'refund_id' => Str::random(20),
            'status' => 'succeeded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function findRowForDate(\Illuminate\Support\Collection $results, Carbon $date): ?object
    {
        return $results->first(fn($r) => Carbon::parse($r->date)->toDateString() === $date->toDateString());
    }

    private function insertEvent(string $currency, ?\DateTimeInterface $deletedAt = null): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Test Event',
            'organizer_id' => $this->organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'currency' => $currency,
            'timezone' => 'UTC',
            'status' => 'published',
            'short_id' => Str::random(8),
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deletedAt,
        ]);
    }
}
