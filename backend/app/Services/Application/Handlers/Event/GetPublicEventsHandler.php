<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicOrganizerEventsDTO;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPublicEventsHandler
{
    public function __construct(
        private readonly EventRepositoryInterface     $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly TaxAndFeeCalculationService  $taxCalculationService,
    )
    {
    }

    public function handle(GetPublicOrganizerEventsDTO $dto): LengthAwarePaginator
    {
        $organizer = $this->organizerRepository->findById($dto->organizerId);

        $query = $this->eventRepository
            ->loadRelation(
                new Relationship(ProductCategoryDomainObject::class, [
                    new Relationship(ProductDomainObject::class,
                        nested: [
                            new Relationship(ProductPriceDomainObject::class),
                            new Relationship(TaxAndFeesDomainObject::class),
                        ],
                        orderAndDirections: [
                            new OrderAndDirection('order', 'asc'),
                        ]
                    ),
                ])
            )
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(ImageDomainObject::class));

        // If the organizer is viewing their own profile, we show all events, even those in draft
        if ($dto->authenticatedAccountId && $organizer->getAccountId() === $dto->authenticatedAccountId) {
            $events = $query->findEventsForOrganizer(
                organizerId: $dto->organizerId,
                accountId: $dto->authenticatedAccountId,
                params: $dto->queryParams
            );
        } else {
            $events = $query->findEvents(
                where: [
                    'organizer_id' => $dto->organizerId,
                    'status' => EventStatus::LIVE->name,
                ],
                params: $dto->queryParams
            );
        }

        $this->calculateProductPriceTotals($events);

        return $events;
    }

    private function calculateProductPriceTotals(LengthAwarePaginator $events): void
    {
        $events->each(function (EventDomainObject $event) {
            $event->getProductCategories()?->each(function (ProductCategoryDomainObject $category) {
                $category->getProducts()?->each(function (ProductDomainObject $product) {
                    $product->getProductPrices()?->each(function (ProductPriceDomainObject $price) use ($product) {
                        if (!$price->isFree()) {
                            $taxAndFees = $this->taxCalculationService->calculateTaxAndFeesForProductPrice($product, $price);
                            $price
                                ->setTaxTotal(Currency::round($taxAndFees->taxTotal))
                                ->setFeeTotal(Currency::round($taxAndFees->feeTotal));
                        }
                    });
                });
            });
        });
    }
}
