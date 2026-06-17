<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Resources\Attendee\AttendeeResourcePublic;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetAttendeeActionPublic extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface  $attendeeRepository,
        private readonly TaxAndFeeCalculationService  $taxCalculationService,
    ) {
    }

    /**
     * @todo move to handler
     */
    public function __invoke(int $eventId, string $attendeeShortId): JsonResponse|Response
    {
        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(
                domainObject: ProductDomainObject::class,
                nested: [
                    new Relationship(domainObject: ProductPriceDomainObject::class),
                    new Relationship(domainObject: TaxAndFeesDomainObject::class),
                ],
                name: 'product',
            ))
            ->findFirstWhere([
                AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId
            ]);

        if (!$attendee) {
            return $this->notFoundResponse();
        }

        $product = $attendee->getProduct();
        if ($product) {
            foreach ($product->getProductPrices() ?? [] as $price) {
                if (!$price->isFree()) {
                    $taxAndFees = $this->taxCalculationService->calculateTaxAndFeesForProductPrice($product, $price);
                    $price
                        ->setTaxTotal(Currency::round($taxAndFees->taxTotal))
                        ->setFeeTotal(Currency::round($taxAndFees->feeTotal));
                }
            }
        }

        return $this->resourceResponse(AttendeeResourcePublic::class, $attendee);
    }
}
