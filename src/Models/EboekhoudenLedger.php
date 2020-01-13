<?php
namespace Dvb\Eboekhouden\Models;

use Dvb\Accounting\AccountingLedger;

class EboekhoudenLedger extends AccountingLedger {
    public function __construct(array $item = null)
    {
        if (!empty($item)) {
            $this
                ->setId($item['ID'])
                ->setCode($item['Code'])
                ->setDescription($item['Omschrijving'])
                ->setCategory($this->parseCategory($item['Categorie']))
                ->setGroup($item['Groep']);
        }
    }

    protected function parseCategory(string $category) {
        switch ($category) {
            case 'VW':
                return 'PROFIT_LOSS';

            case 'BAL':
                return 'BALANCE';

            case 'FIN':
                return 'FINANCE';

            case 'DEB':
                return 'DEBTORS';

            case 'CRED':
                return 'CREDITORS';

            case 'VOOR':
                return 'PAID_TAXES';
        }

        return $category;
    }
}
