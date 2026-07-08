<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\KSeF;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\KsefValidationException;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Domain\KSeF\KsefXmlGenerationService;
use Tests\TestCase;

class KsefXmlGenerationServiceTest extends TestCase
{
    private const FA_NS = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    private AccountVatSettingRepositoryInterface $vatSettingRepository;
    private KsefXmlGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vatSettingRepository = $this->createMock(AccountVatSettingRepositoryInterface::class);
        $this->service = new KsefXmlGenerationService($this->vatSettingRepository);
    }

    public function test_generates_valid_fa3_xml_for_single_item_with_23_percent_vat(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Sprzedawca Sp. z o.o.', 'ul. Przykładowa 1, 00-001 Warszawa'));

        $invoice = $this->makeInvoice(
            items: [
                $this->makeItem('Bilet VIP', 2, 200.00, 400.00, 92.00, 23.0),
            ],
            totalAmount: 492.00,
        );

        $order = $this->makeOrder('9876543210', 'Firma ABC Sp. z o.o.', 0.0);

        $xml = $this->service->generate($invoice, $order);

        $xpath = $this->xpath($xml);

        $this->assertEquals('1234567890', $xpath->evaluate('string(//fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:NIP)'));
        $this->assertEquals('9876543210', $xpath->evaluate('string(//fa:Podmiot2/fa:DaneIdentyfikacyjne/fa:NIP)'));
        $this->assertEquals('FV/2024/1', $xpath->evaluate('string(//fa:Fa/fa:P_2)'));
        $this->assertEquals('1', $xpath->evaluate('string(//fa:FaWiersz/fa:NrWierszaFa)'));
        $this->assertEquals('Bilet VIP', $xpath->evaluate('string(//fa:FaWiersz/fa:P_7)'));
        $this->assertEquals('23', $xpath->evaluate('string(//fa:FaWiersz/fa:P_12)'));
        $this->assertEquals('400.00', $xpath->evaluate('string(//fa:Fa/fa:P_13_1)'));
        $this->assertEquals('92.00', $xpath->evaluate('string(//fa:Fa/fa:P_14_1)'));
        $this->assertEquals('492.00', $xpath->evaluate('string(//fa:Fa/fa:P_15)'));
    }

    public function test_aggregates_vat_for_multiple_rates(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Sprzedawca Sp. z o.o.', 'ul. Przykładowa 1'));

        $invoice = $this->makeInvoice(
            items: [
                $this->makeItem('Bilet Standard', 1, 100.00, 100.00, 23.00, 23.0),
                $this->makeItem('Bilet Ulgowy', 1, 50.00, 50.00, 4.00, 8.0),
            ],
            totalAmount: 177.00,
        );

        $order = $this->makeOrder('9876543210', 'Firma ABC', 0.0);

        $xpath = $this->xpath($this->service->generate($invoice, $order));

        $this->assertEquals('100.00', $xpath->evaluate('string(//fa:Fa/fa:P_13_1)'));
        $this->assertEquals('23.00', $xpath->evaluate('string(//fa:Fa/fa:P_14_1)'));
        $this->assertEquals('50.00', $xpath->evaluate('string(//fa:Fa/fa:P_13_2)'));
        $this->assertEquals('4.00', $xpath->evaluate('string(//fa:Fa/fa:P_14_2)'));
    }

    public function test_adds_fee_line_item_at_23_percent(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Sprzedawca Sp. z o.o.', 'ul. Przykładowa 1'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Bilet', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 129.15,
        );

        $order = $this->makeOrder('9876543210', 'Firma ABC', 5.00);

        $xpath = $this->xpath($this->service->generate($invoice, $order));

        $feeWiersz = $xpath->query('//fa:FaWiersz[fa:P_12="23"]');
        $this->assertGreaterThan(0, $feeWiersz->length);

        $this->assertEquals('105.00', $xpath->evaluate('string(//fa:Fa/fa:P_13_1)'));
        $this->assertEquals('24.15', $xpath->evaluate('string(//fa:Fa/fa:P_14_1)'));
    }

    public function test_normalizes_seller_nip_with_pl_prefix(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('PL1234567890', 'Sprzedawca Sp. z o.o.', 'ul. Przykładowa 1'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Bilet', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 123.00,
        );

        $order = $this->makeOrder('9876543210', 'Firma ABC', 0.0);

        $xpath = $this->xpath($this->service->generate($invoice, $order));

        $this->assertEquals('1234567890', $xpath->evaluate('string(//fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:NIP)'));
    }

    public function test_throws_when_seller_nip_has_wrong_length(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('12345', 'Sprzedawca', 'ul. Przykładowa 1'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Bilet', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 123.00,
        );

        $this->expectException(KsefValidationException::class);

        $this->service->generate($invoice, $this->makeOrder('9876543210', 'Firma ABC', 0.0));
    }

    public function test_throws_when_buyer_nip_has_wrong_length(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Sprzedawca', 'ul. Przykładowa 1'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Bilet', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 123.00,
        );

        $this->expectException(KsefValidationException::class);

        $this->service->generate($invoice, $this->makeOrder('123', 'Firma ABC', 0.0));
    }

    public function test_throws_when_account_vat_settings_missing(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn(null);

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Bilet', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 123.00,
        );

        $this->expectException(KsefValidationException::class);

        $this->service->generate($invoice, $this->makeOrder('9876543210', 'Firma ABC', 0.0));
    }

    public function test_throws_when_invoice_has_no_items(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Sprzedawca', 'ul. Przykładowa 1'));

        $invoice = $this->makeInvoice(items: [], totalAmount: 0.0);

        $this->expectException(KsefValidationException::class);

        $this->service->generate($invoice, $this->makeOrder('9876543210', 'Firma ABC', 0.0));
    }

    public function test_omits_termin_platnosci_when_due_date_is_null(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Sprzedawca Sp. z o.o.', 'ul. Przykładowa 1'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Bilet', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 123.00,
            dueDate: null,
        );

        $xpath = $this->xpath($this->service->generate($invoice, $this->makeOrder('9876543210', 'Firma ABC', 0.0)));

        $this->assertEquals(0, $xpath->query('//fa:TerminPlatnosci')->length);
        $this->assertEquals(1, $xpath->query('//fa:Zaplacono')->length);
    }

    public function test_xml_passes_xsd_validation(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Sprzedawca Sp. z o.o.', 'ul. Przykładowa 1, Warszawa'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Bilet VIP', 1, 199.00, 199.00, 45.77, 23.0)],
            totalAmount: 244.77,
        );

        $order = $this->makeOrder('9876543210', 'Firma ABC Sp. z o.o.', 0.0);

        $xml = $this->service->generate($invoice, $order);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        libxml_use_internal_errors(true);

        $xsdPath = base_path('vendor/n1ebieski/ksef-php-client/resources/xsd/faktura/schemat.xsd');
        $valid = $dom->schemaValidate($xsdPath);

        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertTrue($valid, 'XSD validation failed: ' . implode('; ', array_map(fn($e) => trim($e->message), $errors)));
    }

    public function test_uses_new_2025_namespace(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Seller', 'Address 1'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Ticket', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 123.00,
        );

        $xml = $this->service->generate($invoice, $this->makeOrder('9876543210', 'Buyer', 0.0));

        $this->assertStringContainsString('http://crd.gov.pl/wzor/2025/06/25/13775/', $xml);
    }

    public function test_contains_required_adnotacje_block(): void
    {
        $this->vatSettingRepository
            ->method('findByAccountId')
            ->willReturn($this->makeVatSettings('1234567890', 'Seller', 'Address 1'));

        $invoice = $this->makeInvoice(
            items: [$this->makeItem('Ticket', 1, 100.00, 100.00, 23.00, 23.0)],
            totalAmount: 123.00,
        );

        $xpath = $this->xpath($this->service->generate($invoice, $this->makeOrder('9876543210', 'Buyer', 0.0)));

        $this->assertEquals(1, $xpath->query('//fa:Adnotacje')->length);
        $this->assertEquals('2', $xpath->evaluate('string(//fa:Adnotacje/fa:P_16)'));
        $this->assertEquals('VAT', $xpath->evaluate('string(//fa:Fa/fa:RodzajFaktury)'));
    }

    private function xpath(string $xml): \DOMXPath
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('fa', self::FA_NS);

        return $xpath;
    }

    private function makeVatSettings(string $nip, string $name, string $address): AccountVatSettingDomainObject
    {
        return (new AccountVatSettingDomainObject())
            ->setVatNumber($nip)
            ->setBusinessName($name)
            ->setBusinessAddress($address);
    }

    private function makeInvoice(array $items, float $totalAmount, ?string $dueDate = '2024-02-15'): InvoiceDomainObject
    {
        return (new InvoiceDomainObject())
            ->setId(1)
            ->setAccountId(1)
            ->setOrderId(1)
            ->setInvoiceNumber('FV/2024/1')
            ->setIssueDate('2024-01-15')
            ->setDueDate($dueDate)
            ->setTotalAmount($totalAmount)
            ->setDocumentType('invoice')
            ->setItems($items);
    }

    private function makeOrder(string $nip, string $companyName, float $totalFee): OrderDomainObject
    {
        return (new OrderDomainObject())
            ->setId(1)
            ->setBuyerType('company')
            ->setCompanyNip($nip)
            ->setCompanyName($companyName)
            ->setAddress(['address_line_1' => 'ul. Nabywcy 5', 'city' => 'Kraków', 'zip_or_postal_code' => '30-001', 'country' => 'PL'])
            ->setTotalFee($totalFee);
    }

    private function makeItem(
        string $name,
        int $quantity,
        float $price,
        float $totalBeforeAdditions,
        float $totalTax,
        float $vatRate
    ): array {
        return [
            'item_name'               => $name,
            'quantity'                => $quantity,
            'price'                   => $price,
            'total_before_additions'  => $totalBeforeAdditions,
            'total_tax'               => $totalTax,
            'taxes_and_fees_rollup'   => [
                'taxes' => [
                    ['name' => 'VAT ' . $vatRate . '%', 'rate' => $vatRate, 'type' => 'PERCENTAGE'],
                ],
                'fees'  => [],
            ],
        ];
    }
}
