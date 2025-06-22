```php
  use horstoeko\zugferd\codelists\ZugferdCountryCodes;
  use horstoeko\zugferd\codelists\ZugferdCurrencyCodes;
  use horstoeko\zugferd\codelists\ZugferdElectronicAddressScheme;
  use horstoeko\zugferd\codelists\ZugferdInvoiceType;
  use horstoeko\zugferd\codelists\ZugferdReferenceCodeQualifiers;
  use horstoeko\zugferd\codelists\ZugferdUnitCodes;
  use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
  use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
  use horstoeko\zugferd\ZugferdDocumentBuilder;
  use horstoeko\zugferd\ZugferdProfiles;

  // Create an empty invoice document in the EN16931 profile
  $document = ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_EN16931);

  // Add invoice and position information
  $document
    ->setDocumentInformation('R-2024/00001', ZugferdInvoiceType::INVOICE, DateTime::createFromFormat("Ymd", "20241231"), ZugferdCurrencyCodes::EURO)
    ->addDocumentNote('Lieferant GmbH' . PHP_EOL . 'Lieferantenstraße 20' . PHP_EOL . '80333 München' . PHP_EOL . 'Deutschland' . PHP_EOL . 'Geschäftsführer: Hans Muster' . PHP_EOL . 'Handelsregisternummer: H A 123' . PHP_EOL . PHP_EOL, null, 'REG')
    ->setDocumentBillingPeriod(DateTime::createFromFormat("Ymd", "20250101"), DateTime::createFromFormat("Ymd", "20250131"), "01.01.2025 - 31.01.2025")
    ->setDocumentSeller("Lieferant GmbH", "549910")
    ->addDocumentSellerGlobalId("4000001123452", "0088")
    ->addDocumentSellerTaxNumber("201/113/40209")
    ->addDocumentSellerVATRegistrationNumber("DE123456789")
    ->setDocumentSellerAddress("Lieferantenstraße 20", "", "", "80333", "München", ZugferdCountryCodes::GERMANY)
    ->setDocumentSellerContact("H. Müller", "Verkauf", "+49-111-2222222", "+49-111-3333333", "hm@lieferant.de")
    ->setDocumentSellerCommunication(ZugferdElectronicAddressScheme::UNECE3155_EM, 'sales@lieferant.de')
    ->setDocumentBuyer("Kunden AG Mitte", "GE2020211")
    ->setDocumentBuyerAddress("Kundenstraße 15", "", "", "69876", "Frankfurt", ZugferdCountryCodes::GERMANY)
    ->setDocumentBuyerContact("H. Meier", "Einkauf", "+49-333-4444444", "+49-333-5555555", "hm@kunde.de")
    ->setDocumentBuyerCommunication(ZugferdElectronicAddressScheme::UNECE3155_EM, 'purchase@kunde.de')
    ->setDocumentShipTo("Kunden AG Ost")
    ->setDocumentShipToAddress("Lieferstraße 1", "", "", "04109", "Leipzig", ZugferdCountryCodes::GERMANY)
    ->setDocumentSupplyChainEvent(DateTime::createFromFormat("Ymd", "20250115"))
    ->addDocumentPaymentMeanToDirectDebit("DE12500105170648489890", "R-2024/00001")
    ->addDocumentPaymentTerm('Wird von Konto DE12500105170648489890 abgebucht', DateTime::createFromFormat("Ymd", "20250131"), 'MANDATE-2024/000001')
    ->addNewPosition("1")
    ->setDocumentPositionProductDetails("Trennblätter A4", "50er Pack", "TB100A4")
    ->setDocumentPositionNetPrice(9.9000)
    ->setDocumentPositionQuantity(20, ZugferdUnitCodes::REC20_PIECE)
    ->addDocumentPositionTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 19)
    ->setDocumentPositionLineSummation(198.0)
    ->addNewPosition("2")
    ->setDocumentPositionProductDetails("Joghurt Banane", "B-Ware", "ARNR2")
    ->setDocumentPositionNetPrice(5.5000)
    ->setDocumentPositionQuantity(50, ZugferdUnitCodes::REC20_PIECE)
    ->addDocumentPositionTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 7)
    ->setDocumentPositionLineSummation(275.0)
    ->addNewPosition("3")
    ->setDocumentPositionProductDetails("Joghurt Erdbeer", "", "ARNR3")
    ->setDocumentPositionNetPrice(4.0000)
    ->setDocumentPositionQuantity(100, ZugferdUnitCodes::REC20_PIECE)
    ->addDocumentPositionTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 7)
    ->setDocumentPositionLineSummation(400.0)
    ->addDocumentTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 198.0, 37.62, 19.0)
    ->addDocumentTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 675.0, 47.25, 7.0)
    ->setDocumentSummation(957.87, 957.87, 873.00, 0.0, 0.0, 873.00, 84.87)
    ->writeFile(dirname(__FILE__) . "/factur-x.xml");
```
