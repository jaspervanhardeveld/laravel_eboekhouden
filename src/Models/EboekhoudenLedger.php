<?php
namespace Models;

use Dvb\Accounting\AccountingLedger;

class EboekhoudenLedger extends AccountingLedger {
    public function __construct(array $item = null)
    {
        if (!empty($item)) {
            $this
                ->setId($item['ID'])
                ->setCode($item['Code'])
                ->setDescription($item['Omschrijving'])
                ->setCategory($item['Categorie'])
                ->setGroup($item['Groep']);
        }
    }
}
