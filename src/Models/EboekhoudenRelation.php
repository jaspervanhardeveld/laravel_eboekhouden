<?php
namespace Dvb\Eboekhouden\Models;

use DateTime;
use Dvb\Accounting\AccountingRelation;
use Exception;

class EboekhoudenRelation extends AccountingRelation {
    /**
     * EboekhoudenRelation constructor.
     * @param array|null $item
     * @throws Exception
     */
    public function __construct(array $item = null)
    {
        if (!empty($item)) {
            $this
                ->setId($item['ID'])
                ->setAddDate(new DateTime($item['AddDatum']))
                ->setCode((int) $item['Code'])
                ->setCompany($item['Bedrijf'])
                ->setContact($item['Contactpersoon'])
                ->setGender($item['Geslacht'])
                ->setAddress($item['Adres'])
                ->setZipcode($item['Postcode'])
                ->setCity($item['Plaats'])
                ->setCountry($item['Land'])
                ->setPhone($item['Telefoon'])
                ->setCellPhone($item['GSM'])
                ->setEmail($item['Email'])
                ->setSite($item['Site'])
                ->setNotes($item['Notitie'])
                ->setVatNumber($item['BTWNummer']);
        }
    }
}
