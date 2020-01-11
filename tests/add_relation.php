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

$relation = new \Dvb\Accounting\AccountingRelation();

$relation
    ->setCode(1000)
    ->setCompany('Test')
    ->setAddress('Teststraat 1')
    ->setZipcode('1234aa')
    ->setCity('Hier');

print_r($provider->addRelation($relation));
