<?php
namespace Dvb\Eboekhouden\Models;

use Dvb\Accounting\AccountingMutationLine;

class EboekhoudenMutationLine extends AccountingMutationLine {
    public function __construct(array $item = null)
    {
        if (!empty($item)) {
            $this
                ->setAmount($item['BedragExclBTW'])
                ->setVatCode($item['BTWCode'])
                ->setVatPercentage($item['BTWPercentage'])
                ->setLedgerCode($item['TegenrekeningCode']);
        }
    }
}
