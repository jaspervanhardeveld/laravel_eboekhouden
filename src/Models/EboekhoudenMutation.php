<?php
namespace Dvb\Eboekhouden\Models;

use DateTime;
use Dvb\Accounting\AccountingMutation;
use Exception;

class EboekhoudenMutation extends AccountingMutation {
    /**
     * EboekhoudenMutation constructor.
     * @param array|null $item
     * @throws Exception
     */
    public function __construct(array $item = null)
    {
        if (!empty($item)) {
            $this
                ->setNumber($item['MutatieNr'])
                ->setKind($item['Soort'])
                ->setDate(new DateTime($item['Datum']))
                ->setLedgerCode($item['Rekening'])
                ->setRelationCode($item['RelatieCode'])
                ->setInvoiceNumber($item['Factuurnummer'])
                ->setDescription($item['Omschrijving'])
                ->setPaymentTerm($item['Betalingstermijn']);

            $lines = $item['MutatieRegels']->cMutatieListRegel;

            if (is_object($lines)) {
                $this->addLine(new EboekhoudenMutationLine((array) $lines));
            } else {
                $this->setLines(array_map(fn($line) => new EboekhoudenMutationLine((array) $line), $item['MutatieRegels']->cMutatieListRegel));
            }
        }
    }
}
