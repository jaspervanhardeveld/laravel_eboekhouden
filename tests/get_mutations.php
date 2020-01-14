<?php

use Dotenv\Dotenv;
use Dvb\Eboekhouden\EboekhoudenProvider;

require(__DIR__ . '/../vendor/autoload.php');

$dotenv = Dotenv::create(__DIR__ . '/../');
$dotenv->load();

$config = [
    'username' => getenv('EBOEKHOUDEN_USERNAME'),
    'sec_code_1' => getenv('EBOEKHOUDEN_SEC_1'),
    'sec_code_2' => getenv('EBOEKHOUDEN_SEC_2'),
    'wsdl' => 'https://soap.e-boekhouden.nl/soap.asmx?wsdl',
    'payment_term' => 14,
    'invoice_template' => 'Factuur sjabloon',
    'email_from_address' => 'invoices@example.com',
    'email_from_name' => 'Example'
];

$provider = new EboekhoudenProvider($config);

$filter = new \Dvb\Accounting\MutationFilter();
$filter->setDateFrom(\Carbon\Carbon::now()->subDays(10));
$filter->setDateTo(\Carbon\Carbon::now()->subDays(2));

print_r($provider->getMutations($filter));
