<?php

namespace HiEvents\Services\Domain\Report;

use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

abstract class AbstractOrganizerReportService
{
    private const CACHE_TTL_SECONDS = 30;

    public function __construct(
        private readonly Repository                    $cache,
        private readonly DatabaseManager               $queryBuilder,
        private readonly OrganizerRepositoryInterface  $organizerRepository,
    )
    {
    }

    public function generateReport(
        int     $organizerId,
        ?string $currency = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array  $paymentProviders = null,
        ?array  $buyerTypes = null,
    ): Collection
    {
        $organizer = $this->organizerRepository->findById($organizerId);
        $timezone = $organizer->getTimezone();

        $endDate = $endDate
            ? $endDate->copy()->setTimezone($timezone)->endOfDay()
            : now($timezone)->endOfDay();
        $startDate = $startDate
            ? $startDate->copy()->setTimezone($timezone)->startOfDay()
            : $endDate->copy()->subDays(30)->startOfDay();

        $reportResults = $this->cache->remember(
            key: $this->getCacheKey($organizerId, $currency, $startDate, $endDate, $paymentProviders, $buyerTypes),
            ttl: Carbon::now()->addSeconds(self::CACHE_TTL_SECONDS),
            callback: fn() => $this->queryBuilder->select(
                $this->getSqlQuery($startDate, $endDate, $currency, $paymentProviders, $buyerTypes),
                [
                    'organizer_id' => $organizerId,
                ]
            )
        );

        return collect($reportResults);
    }

    abstract protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?string $currency = null, ?array $paymentProviders = null, ?array $buyerTypes = null): string;

    protected function buildCurrencyFilter(string $column, ?string $currency): string
    {
        if ($currency === null) {
            return '';
        }
        $escapedCurrency = addslashes($currency);
        return "AND $column = '$escapedCurrency'";
    }

    protected function getCacheKey(int $organizerId, ?string $currency, ?Carbon $startDate, ?Carbon $endDate, ?array $paymentProviders = null, ?array $buyerTypes = null): string
    {
        $providerSegment = $paymentProviders ? implode('_', $paymentProviders) : 'all';
        $buyerSegment = $buyerTypes ? implode('_', $buyerTypes) : 'all';
        return static::class . "$organizerId.$currency.{$startDate?->toDateString()}.{$endDate?->toDateString()}.$providerSegment.$buyerSegment";
    }
}
