<?php
namespace FriendsOfRedaxo\PdfOut;

/**
 * Helper-Klasse für ZUGFeRD/Factur-X Demo-Daten
 * 
 * Diese Klasse stellt Beispieldaten für ZUGFeRD-konforme Rechnungen zur Verfügung
 * und ist aus der Haupt-PdfOut-Klasse ausgelagert worden, um eine bessere
 * Trennung von Kern-Funktionalität und Demo-Daten zu erreichen.
 */
class ZugferdDataHelper
{
    /**
     * Erstellt Beispiel-Rechnungsdaten für ZUGFeRD-Demo
     *
     * @return array Beispiel-Rechnungsdaten
     */
    public static function getExampleZugferdData(): array
    {
        return [
            'invoice_number' => 'RE-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'type_code' => '380', // Standard Rechnung
            'issue_date' => date('Y-m-d'),
            'currency' => 'EUR',
            'default_tax_rate' => 19.0,
            
            'seller' => [
                'name' => 'DIE DEMO GmbH',
                'id' => 'DEMO-001',
                'tax_number' => 'DE123456789',
                'vat_id' => 'DE987654321',
                'company_register' => 'HRB 12345 AG München',
                'address' => [
                    'line1' => 'Innovationsstraße 42',
                    'line2' => 'Tech-Campus, Gebäude A',
                    'line3' => '',
                    'postcode' => '80331',
                    'city' => 'München',
                    'country' => 'DE'
                ],
                'contact' => [
                    'phone' => '+49 89 1234567',
                    'email' => 'rechnung@die-demo.de',
                    'web' => 'https://www.die-demo.de'
                ],
                'bank' => [
                    'name' => 'Bayerische Landesbank',
                    'iban' => 'DE89 3705 0198 1234 5678 90',
                    'bic' => 'BYLADEMM'
                ],
                'management' => 'Geschäftsführer: Max Mustermann, Dr. Anna Schmidt'
            ],
            
            'buyer' => [
                'name' => 'REDAXO Solutions AG',
                'id' => 'KUNDE-2024-042',
                'address' => [
                    'line1' => 'REDAXO-Platz 1',
                    'line2' => 'Abteilung: Digitale Innovation',
                    'line3' => '',
                    'postcode' => '10115',
                    'city' => 'Berlin',
                    'country' => 'DE'
                ]
            ],
            
            'payment_terms' => [
                'description' => 'Zahlbar innerhalb 14 Tagen ohne Abzug. Bei Zahlung nach 14 Tagen werden 2% Verzugszinsen berechnet.',
                'due_date' => date('Y-m-d', strtotime('+14 days'))
            ],
            
            'line_items' => [
                [
                    'name' => 'REDAXO WordPress-Integration AddOn',
                    'description' => 'Entwicklung eines revolutionären AddOns zur nahtlosen Integration von WordPress in REDAXO CMS',
                    'seller_assigned_id' => 'DEMO-WP-001',
                    'quantity' => 1.0,
                    'unit' => 'STK',
                    'unit_price' => 11900.00,
                    'net_unit_price' => 10000.00,
                    'tax_rate' => 19.0
                ]
            ],
            
            'totals' => [
                'net_amount' => 10000.00,
                'tax_amount' => 1900.00,
                'gross_amount' => 11900.00,
                'tax_breakdown' => [
                    [
                        'tax_rate' => 19.0,
                        'taxable_amount' => 10000.00,
                        'tax_amount' => 1900.00
                    ]
                ]
            ],
            
            'project_details' => [
                'project_name' => 'REDAXO-WordPress Integration Suite',
                'project_number' => 'PROJ-WP-2024-042',
                'delivery_time' => '8-12 Wochen ab Auftragserteilung',
                'warranty' => '24 Monate Gewährleistung auf alle Entwicklungsarbeiten'
            ]
        ];
    }
}