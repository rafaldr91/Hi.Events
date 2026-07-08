<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\KSeF;

use DOMDocument;
use DOMElement;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\KsefValidationException;
use HiEvents\Helper\AddressHelper;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;

class KsefXmlGenerationService
{
    private const FA_NAMESPACE = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    private const VAT_RATE_MAP = [
        23.0 => '23',
        8.0  => '8',
        5.0  => '5',
        0.0  => '0 KR',
    ];

    private const VAT_SUMMARY_FIELDS = [
        '23'   => ['net' => 'P_13_1', 'vat' => 'P_14_1'],
        '8'    => ['net' => 'P_13_2', 'vat' => 'P_14_2'],
        '5'    => ['net' => 'P_13_3', 'vat' => 'P_14_3'],
        '0 KR' => ['net' => 'P_13_6_1', 'vat' => null],
        'zw'   => ['net' => 'P_13_7', 'vat' => null],
        'np I' => ['net' => 'P_13_8', 'vat' => null],
    ];

    public function __construct(
        private readonly AccountVatSettingRepositoryInterface $accountVatSettingRepository,
    ) {
    }

    /**
     * @throws KsefValidationException
     */
    public function generateCorrection(
        InvoiceDomainObject $correction,
        InvoiceDomainObject $originalInvoice,
        OrderDomainObject $order,
    ): string {
        $vatSettings = $this->accountVatSettingRepository->findByAccountId($correction->getAccountId());

        $this->validate($correction, $order, $vatSettings);

        $sellerNip = $this->normalizeNip($vatSettings->getVatNumber());
        $buyerNip  = $this->normalizeNip($order->getCompanyNip());

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::FA_NAMESPACE, 'Faktura');
        $dom->appendChild($root);

        $root->appendChild($this->buildNaglowek($dom));
        $root->appendChild($this->buildPodmiot1($dom, $vatSettings, $sellerNip));
        $root->appendChild($this->buildPodmiot2($dom, $order, $buyerNip));
        $root->appendChild($this->buildFaCorrection($dom, $correction, $originalInvoice, $order));

        return $dom->saveXML();
    }

    /**
     * @throws KsefValidationException
     */
    public function generate(InvoiceDomainObject $invoice, OrderDomainObject $order): string
    {
        $vatSettings = $this->accountVatSettingRepository->findByAccountId($invoice->getAccountId());

        $this->validate($invoice, $order, $vatSettings);

        $sellerNip = $this->normalizeNip($vatSettings->getVatNumber());
        $buyerNip  = $this->normalizeNip($order->getCompanyNip());

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::FA_NAMESPACE, 'Faktura');
        $dom->appendChild($root);

        $root->appendChild($this->buildNaglowek($dom));
        $root->appendChild($this->buildPodmiot1($dom, $vatSettings, $sellerNip));
        $root->appendChild($this->buildPodmiot2($dom, $order, $buyerNip));
        $root->appendChild($this->buildFa($dom, $invoice, $order));

        return $dom->saveXML();
    }

    /**
     * @throws KsefValidationException
     */
    private function validate(
        InvoiceDomainObject $invoice,
        OrderDomainObject $order,
        ?AccountVatSettingDomainObject $vatSettings,
    ): void {
        if ($vatSettings === null || empty($vatSettings->getVatNumber())) {
            throw new KsefValidationException(__('Seller NIP is not configured'));
        }

        if (empty($vatSettings->getBusinessName())) {
            throw new KsefValidationException(__('Seller business name is not configured'));
        }

        $this->normalizeNip($vatSettings->getVatNumber());
        $this->normalizeNip($order->getCompanyNip() ?? '');

        if (empty($invoice->getItems())) {
            throw new KsefValidationException(__('Invoice has no line items'));
        }

        foreach (is_array($invoice->getItems()) ? $invoice->getItems() : [] as $item) {
            $taxes = $item['taxes_and_fees_rollup']['taxes'] ?? [];
            if (empty($taxes)) {
                throw new KsefValidationException(
                    __('Item ":name" has no tax rate configured. Please add a tax to the ticket before sending to KSeF.', [
                        'name' => $item['item_name'] ?? 'unknown',
                    ])
                );
            }
        }
    }

    /**
     * @throws KsefValidationException
     */
    private function normalizeNip(string $nip): string
    {
        $normalized = preg_replace('/^PL/i', '', $nip);
        $normalized = preg_replace('/[\s\-.]/', '', $normalized);

        if (!preg_match('/^\d{10}$/', $normalized)) {
            throw new KsefValidationException(__('Invalid NIP number: must be exactly 10 digits'));
        }

        return $normalized;
    }

    private function buildNaglowek(DOMDocument $dom): DOMElement
    {
        $naglowek = $dom->createElement('Naglowek');

        $kodFormularza = $dom->createElement('KodFormularza', 'FA');
        $kodFormularza->setAttribute('kodSystemowy', 'FA (3)');
        $kodFormularza->setAttribute('wersjaSchemy', '1-0E');
        $naglowek->appendChild($kodFormularza);

        $naglowek->appendChild($dom->createElement('WariantFormularza', '3'));
        $naglowek->appendChild($dom->createElement('DataWytworzeniaFa', now()->utc()->toIso8601ZuluString()));
        $naglowek->appendChild($dom->createElement('SystemInfo', 'hi.events'));

        return $naglowek;
    }

    private function buildPodmiot1(
        DOMDocument $dom,
        AccountVatSettingDomainObject $vatSettings,
        string $sellerNip,
    ): DOMElement {
        $podmiot = $dom->createElement('Podmiot1');

        $daneId = $dom->createElement('DaneIdentyfikacyjne');
        $daneId->appendChild($dom->createElement('NIP', $sellerNip));
        $daneId->appendChild($dom->createElement('Nazwa', $vatSettings->getBusinessName()));
        $podmiot->appendChild($daneId);

        $adres = $dom->createElement('Adres');
        $adres->appendChild($dom->createElement('KodKraju', 'PL'));
        $adres->appendChild($dom->createElement('AdresL1', $vatSettings->getBusinessAddress() ?? ''));
        $podmiot->appendChild($adres);

        return $podmiot;
    }

    private function buildPodmiot2(
        DOMDocument $dom,
        OrderDomainObject $order,
        string $buyerNip,
    ): DOMElement {
        $podmiot = $dom->createElement('Podmiot2');

        $daneId = $dom->createElement('DaneIdentyfikacyjne');
        $daneId->appendChild($dom->createElement('NIP', $buyerNip));
        $daneId->appendChild($dom->createElement('Nazwa', $order->getCompanyName() ?? ''));
        $podmiot->appendChild($daneId);

        $address = $order->getAddress();
        $adresL1 = AddressHelper::formatAddress(is_array($address) ? $address : []);

        $adres = $dom->createElement('Adres');
        $adres->appendChild($dom->createElement('KodKraju', 'PL'));
        $adres->appendChild($dom->createElement('AdresL1', $adresL1 ?: '-'));
        $podmiot->appendChild($adres);

        $podmiot->appendChild($dom->createElement('JST', '2'));
        $podmiot->appendChild($dom->createElement('GV', '2'));

        return $podmiot;
    }

    private function buildFa(
        DOMDocument $dom,
        InvoiceDomainObject $invoice,
        OrderDomainObject $order,
    ): DOMElement {
        $fa = $dom->createElement('Fa');

        $fa->appendChild($dom->createElement('KodWaluty', 'PLN'));
        $fa->appendChild($dom->createElement('P_1', substr($invoice->getIssueDate(), 0, 10)));
        $fa->appendChild($dom->createElement('P_2', $invoice->getInvoiceNumber()));

        $items    = is_array($invoice->getItems()) ? $invoice->getItems() : [];
        $totalFee = (float)($order->getTotalFee() ?? 0);

        $vatSummary = $this->buildVatSummary($items, $totalFee);
        foreach (self::VAT_SUMMARY_FIELDS as $rateCode => $fields) {
            if (!isset($vatSummary[$rateCode])) {
                continue;
            }
            $amounts = $vatSummary[$rateCode];
            $fa->appendChild($dom->createElement($fields['net'], $this->formatAmount($amounts['net'])));
            if ($fields['vat'] !== null) {
                $fa->appendChild($dom->createElement($fields['vat'], $this->formatAmount($amounts['vat'])));
            }
        }

        $fa->appendChild($dom->createElement('P_15', $this->formatAmount((float)$invoice->getTotalAmount())));

        $fa->appendChild($this->buildAdnotacje($dom));
        $fa->appendChild($dom->createElement('RodzajFaktury', 'VAT'));

        $lineNo = 1;
        foreach ($items as $item) {
            $fa->appendChild($this->buildFaWiersz($dom, $item, $lineNo++));
        }

        if ($totalFee > 0.0) {
            $fa->appendChild($this->buildFeeWiersz($dom, $totalFee, $lineNo));
        }

        $fa->appendChild($this->buildPlatnosc($dom, $invoice));

        return $fa;
    }

    private function buildAdnotacje(DOMDocument $dom): DOMElement
    {
        $adnotacje = $dom->createElement('Adnotacje');

        $adnotacje->appendChild($dom->createElement('P_16', '2'));
        $adnotacje->appendChild($dom->createElement('P_17', '2'));
        $adnotacje->appendChild($dom->createElement('P_18', '2'));
        $adnotacje->appendChild($dom->createElement('P_18A', '2'));

        $zwolnienie = $dom->createElement('Zwolnienie');
        $zwolnienie->appendChild($dom->createElement('P_19N', '1'));
        $adnotacje->appendChild($zwolnienie);

        $nst = $dom->createElement('NoweSrodkiTransportu');
        $nst->appendChild($dom->createElement('P_22N', '1'));
        $adnotacje->appendChild($nst);

        $adnotacje->appendChild($dom->createElement('P_23', '2'));

        $pmarzy = $dom->createElement('PMarzy');
        $pmarzy->appendChild($dom->createElement('P_PMarzyN', '1'));
        $adnotacje->appendChild($pmarzy);

        return $adnotacje;
    }

    private function buildFaWiersz(DOMDocument $dom, array $item, int $lineNo): DOMElement
    {
        $wiersz = $dom->createElement('FaWiersz');

        $wiersz->appendChild($dom->createElement('NrWierszaFa', (string)$lineNo));
        $wiersz->appendChild($dom->createElement('P_7', $item['item_name'] ?? ''));
        $wiersz->appendChild($dom->createElement('P_8A', 'szt'));
        $wiersz->appendChild($dom->createElement('P_8B', (string)($item['quantity'] ?? 1)));
        $wiersz->appendChild($dom->createElement('P_9A', $this->formatAmount((float)($item['price'] ?? 0))));
        $wiersz->appendChild($dom->createElement('P_11', $this->formatAmount((float)($item['total_before_additions'] ?? 0))));
        $wiersz->appendChild($dom->createElement('P_12', $this->mapVatRate($item)));

        return $wiersz;
    }

    private function buildFeeWiersz(DOMDocument $dom, float $totalFee, int $lineNo): DOMElement
    {
        $wiersz = $dom->createElement('FaWiersz');

        $wiersz->appendChild($dom->createElement('NrWierszaFa', (string)$lineNo));
        $wiersz->appendChild($dom->createElement('P_7', __('Service Fee')));
        $wiersz->appendChild($dom->createElement('P_8A', 'szt'));
        $wiersz->appendChild($dom->createElement('P_8B', '1'));
        $wiersz->appendChild($dom->createElement('P_9A', $this->formatAmount($totalFee)));
        $wiersz->appendChild($dom->createElement('P_11', $this->formatAmount($totalFee)));
        $wiersz->appendChild($dom->createElement('P_12', '23'));

        return $wiersz;
    }

    private function buildPlatnosc(DOMDocument $dom, InvoiceDomainObject $invoice): DOMElement
    {
        $platnosc = $dom->createElement('Platnosc');

        $dueDate = $invoice->getDueDate();
        if ($dueDate !== null) {
            $terminPlatnosci = $dom->createElement('TerminPlatnosci');
            $terminPlatnosci->appendChild($dom->createElement('Termin', substr($dueDate, 0, 10)));
            $platnosc->appendChild($terminPlatnosci);
        } else {
            $platnosc->appendChild($dom->createElement('Zaplacono', '1'));
            $platnosc->appendChild($dom->createElement('DataZaplaty', substr($invoice->getIssueDate(), 0, 10)));
        }

        $platnosc->appendChild($dom->createElement('FormaPlatnosci', '6'));

        return $platnosc;
    }

    private function buildVatSummary(array $items, float $totalFee): array
    {
        $summary = [];

        foreach ($items as $item) {
            $rateCode = $this->mapVatRate($item);
            if (!isset($summary[$rateCode])) {
                $summary[$rateCode] = ['net' => 0.0, 'vat' => 0.0];
            }
            $summary[$rateCode]['net'] += (float)($item['total_before_additions'] ?? 0);
            $summary[$rateCode]['vat'] += (float)($item['total_tax'] ?? 0);
        }

        if ($totalFee > 0.0) {
            if (!isset($summary['23'])) {
                $summary['23'] = ['net' => 0.0, 'vat' => 0.0];
            }
            $summary['23']['net'] += $totalFee;
            $summary['23']['vat'] += round($totalFee * 0.23, 2);
        }

        return $summary;
    }

    private function mapVatRate(array $item): string
    {
        $taxes = $item['taxes_and_fees_rollup']['taxes'] ?? [];

        if (empty($taxes)) {
            return 'zw';
        }

        $tax = $taxes[0];
        $type = $tax['type'] ?? 'PERCENTAGE';
        $rate = (float)($tax['rate'] ?? -1);

        if ($type === 'FIXED') {
            if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $tax['name'] ?? '', $matches)) {
                $rate = (float)$matches[1];
            } else {
                $net = (float)($item['total_before_additions'] ?? 0);
                if ($net > 0) {
                    $calculated = $rate / $net * 100;
                    $knownRates = array_keys(self::VAT_RATE_MAP);
                    usort($knownRates, fn($a, $b) => abs($a - $calculated) <=> abs($b - $calculated));
                    $nearest = $knownRates[0];
                    $rate = abs($nearest - $calculated) <= 1.5 ? $nearest : -1;
                } else {
                    $rate = -1;
                }
            }
        }

        if (!array_key_exists($rate, self::VAT_RATE_MAP)) {
            throw new KsefValidationException(
                __('Item ":name" has an unrecognized tax rate (:rate%). Only 23%, 8%, 5% and 0% are supported.', [
                    'name' => $item['item_name'] ?? 'unknown',
                    'rate' => $rate,
                ])
            );
        }

        return self::VAT_RATE_MAP[$rate];
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function buildFaCorrection(
        DOMDocument $dom,
        InvoiceDomainObject $correction,
        InvoiceDomainObject $originalInvoice,
        OrderDomainObject $order,
    ): DOMElement {
        $fa = $dom->createElement('Fa');

        $fa->appendChild($dom->createElement('KodWaluty', 'PLN'));
        $fa->appendChild($dom->createElement('P_1', substr($correction->getIssueDate(), 0, 10)));
        $fa->appendChild($dom->createElement('P_2', $correction->getInvoiceNumber()));

        $items    = is_array($correction->getItems()) ? $correction->getItems() : [];
        $totalFee = (float)($order->getTotalFee() ?? 0);

        $vatSummary = $this->buildVatSummary($items, 0.0);
        foreach (self::VAT_SUMMARY_FIELDS as $rateCode => $fields) {
            if (!isset($vatSummary[$rateCode])) {
                continue;
            }
            $amounts = $vatSummary[$rateCode];
            $fa->appendChild($dom->createElement($fields['net'], $this->formatAmount($amounts['net'])));
            if ($fields['vat'] !== null) {
                $fa->appendChild($dom->createElement($fields['vat'], $this->formatAmount($amounts['vat'])));
            }
        }

        $fa->appendChild($dom->createElement('P_15', $this->formatAmount((float)$correction->getTotalAmount())));

        $fa->appendChild($this->buildAdnotacje($dom));
        $fa->appendChild($dom->createElement('RodzajFaktury', 'KOR'));

        $daneFaKorygowanej = $dom->createElement('DaneFaKorygowanej');
        $daneFaKorygowanej->appendChild($dom->createElement(
            'DataWystFaKorygowanej',
            substr($originalInvoice->getIssueDate(), 0, 10),
        ));
        $daneFaKorygowanej->appendChild($dom->createElement(
            'NrFaKorygowanej',
            $originalInvoice->getInvoiceNumber(),
        ));
        if ($originalInvoice->getKsefNumber() !== null) {
            $daneFaKorygowanej->appendChild($dom->createElement('NrKSeF', '1'));
            $daneFaKorygowanej->appendChild($dom->createElement(
                'NrKSeFFaKorygowanej',
                $originalInvoice->getKsefNumber(),
            ));
        } else {
            $daneFaKorygowanej->appendChild($dom->createElement('NrKSeFN', '1'));
        }
        $fa->appendChild($daneFaKorygowanej);

        $lineNo = 1;
        foreach ($items as $item) {
            $fa->appendChild($this->buildFaWiersz($dom, $item, $lineNo++));
        }

        if ($totalFee > 0.0) {
            $correctionFee = -$totalFee;
            $fa->appendChild($this->buildFeeWiersz($dom, $correctionFee, $lineNo));
        }

        $fa->appendChild($this->buildPlatnosc($dom, $correction));

        return $fa;
    }
}
