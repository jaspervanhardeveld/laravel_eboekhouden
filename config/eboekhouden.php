<?php
return [
    'username' => env('EBOEKHOUDEN_USERNAME'),
    'security_code1' => env('EBOEKHOUDEN_SEC_1'),
    'security_code2' => env('EBOEKHOUDEN_SEC_2'),
    'wsdl' => 'https://soap.e-boekhouden.nl/soap.asmx?wsdl',
    'payment_term' => 14,
    'invoice_template' => 'Factuur sjabloon',
    'email_from_address' => env('EBOEKHOUDEN_EMAIL_FROM_ADDRESS', 'invoices@example.com'),
    'email_from_name' => env('EBOEKHOUDEN_EMAIL_FROM_NAME', 'Example'),
];
