<?php
namespace Dvb\Eboekhouden;


use DateTime;
use Dvb\Accounting\AccountingException;
use Dvb\Accounting\AccountingInvoice;
use Dvb\Accounting\AccountingLedger;
use Dvb\Accounting\AccountingMutation;
use Dvb\Accounting\AccountingProvider;
use Dvb\Accounting\AccountingRelation;
use Dvb\Accounting\MutationFilter;
use Dvb\Eboekhouden\Models\EboekhoudenLedger;
use Dvb\Eboekhouden\Models\EboekhoudenMutation;
use Dvb\Eboekhouden\Models\EboekhoudenRelation;
use SoapClient;

class EboekhoudenProvider implements AccountingProvider {

    private SoapClient $soapClient;
    private string $session_id;
    private array $config;

    /**
     * EboekhoudenProvider constructor.
     * @param array $config
     * @throws AccountingException
     */
    public function __construct(array $config)
    {
        $this->validateConfig($config);

        $this->config = $config;
    }

    /**
     * Validate given config array
     *
     * @param array $config
     * @throws AccountingException
     */
    private function validateConfig(array $config) {
        $required = [
            'username',
            'sec_code_1',
            'sec_code_2',
            'wsdl',
            'payment_term',
            'invoice_template',
            'email_from_name',
            'email_from_address'
        ];

        foreach($required as $item) {
            if (!isset($config[$item]) || empty($config[$item])) {
                throw new AccountingException("Config item $item is missing.");
            }
        }
    }

    /**
     * Create SoapClient to connect to eboekhouden
     *
     * @throws AccountingException
     */
    private function createSoapClient(): void
    {
        if (!empty($this->soapClient) && !empty($this->sessionId)) {
            return;
        }

        try {
            $this->soapClient = new SoapClient($this->config['wsdl']);
        } catch (\SoapFault $exception) {
            throw new AccountingException($exception->getMessage());
        }

        $result = $this->soapClient->__soapCall('OpenSession', [
            "OpenSession" => [
                "Username" => $this->config['username'],
                "SecurityCode1" => $this->config['sec_code_1'],
                "SecurityCode2" => $this->config['sec_code_2']
            ]
        ]);

        $this->checkError('OpenSession', $result);

        $this->session_id = $result->OpenSessionResult->SessionID;
    }

    /**
     * Check Eboekhouden response for errors
     *
     * @param $methodName
     * @param $response
     * @throws AccountingException
     */
    private function checkError($methodName, $response): void
    {
        if (!empty($response->{$methodName . 'Result'}->ErrorMsg->LastErrorCode)) {
            throw new AccountingException($response->{$methodName . 'Result'}->ErrorMsg->LastErrorDescription);
        }
    }

    /**
     * Get all relations from eboekhouden
     *
     * @return AccountingRelation[]
     * @throws AccountingException
     */
    public function getRelations(): array
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('GetRelaties', [
            'GetRelaties' => [
                'SessionID' => $this->session_id,
                'SecurityCode2' => $this->config['sec_code_2'],
                'cFilter' => [
                    'Trefwoord' => '',
                    'Code' => '',
                    'ID' => 0
                ]
            ]
        ]);

        $this->checkError('GetRelaties', $result);

        $relations = $result->GetRelatiesResult->Relaties->cRelatie;

        if (!is_array($relations)) {
            $relations = [$relations];
        }

        return array_map(fn($item) => new EboekhoudenRelation((array) $item), $relations);
    }

    /**
     * Get all ledgers from eboekhouden
     *
     * @return AccountingLedger[]
     * @throws AccountingException
     */
    public function getLedgers(): array
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('GetGrootboekrekeningen', [
            "GetGrootboekrekeningen" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->config['sec_code_2'],
                "cFilter" => [
                    "ID" => "",
                    "Code" => "",
                    "Categorie" => ""
                ]
            ]
        ]);

        $this->checkError('GetGrootboekrekeningenResult', $result);

        $ledgers = $result->GetGrootboekrekeningenResult->Rekeningen->cGrootboekrekening;

        if (!is_array($ledgers)) {
            $ledgers = [$ledgers];
        }

        return array_map(fn($item) => new EboekhoudenLedger((array) $item), $ledgers);
    }

    /**
     * Get all mutations from eboekhouden
     *
     * @param MutationFilter|null $filter
     * @return AccountingMutation[]
     * @throws AccountingException
     */
    public function getMutations(MutationFilter $filter = null): array
    {
        if (is_null($filter)) {
            $filter = new MutationFilter();
        }

        $this->createSoapClient();

        $dateFrom = $filter->getDateFrom() ?? new DateTime('1970-01-01 00:00:00');
        $dateTo = $filter->getDateTo() ?? new DateTime('2050-12-31 23:59:59');

        $result = $this->soapClient->__soapCall('GetMutaties', [
            'GetMutaties' => [
                'SessionID' => $this->session_id,
                'SecurityCode2' => $this->config['sec_code_2'],
                'cFilter' => [
                    'MutatieNr' => $filter->getMutationNumber(),
                    'MutatieNrVan' => 0,
                    'MutatieNrTm' => 0,
                    'Factuurnummer' => '',
                    'DatumVan' => $dateFrom->format('c'),
                    'DatumTm' => $dateTo->format('c')
                ]
            ]
        ]);

        $this->checkError('GetMutaties', $result);

        if (!isset($result->GetMutatiesResult->Mutaties->cMutatieList)) {
            return [];
        }

        $mutations = $result->GetMutatiesResult->Mutaties->cMutatieList;

        if (!is_array($mutations)) {
            $mutations = [$mutations];
        }

        return array_map(fn($item) => new EboekhoudenMutation((array) $item), $mutations);
    }

    /**
     * Add a new invoice to eboekhouden
     *
     * @param AccountingInvoice $invoice
     * @return string       New invoice number
     * @throws AccountingException
     */
    public function addInvoice(AccountingInvoice $invoice): string
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('AddFactuur', [
            "AddFactuur" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->config['sec_code_2'],
                "oFact" => $this->getOFact($invoice)
            ]
        ]);

        $this->checkError('AddFactuur', $result);

        return (string) $result->AddFactuurResult->Factuurnummer;
    }

    /**
     * Add new relation to eboekhouden
     *
     * @param AccountingRelation $relation
     * @return AccountingRelation
     * @throws AccountingException
     */
    public function addRelation(AccountingRelation $relation): AccountingRelation
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('AddRelatie', [
            "AddRelatie" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->config['sec_code_2'],
                "oRel" => $this->getORel($relation)
            ]
        ]);

        $this->checkError('AddRelatie', $result);

        $relation->setId((int) $result->AddRelatieResult->Rel_ID);

        return $relation;
    }

    /**
     * Update relation
     *
     * @param AccountingRelation $relation
     * @return AccountingRelation
     * @throws AccountingException
     */
    public function updateRelation(AccountingRelation $relation): AccountingRelation
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('UpdateRelatie', [
            "UpdateRelatie" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->config['sec_code_2'],
                "oRel" => $this->getORel($relation)
            ]
        ]);

        $this->checkError('UpdateRelatie', $result);

        return $relation;
    }

    private function getOFact(AccountingInvoice $invoice): array
    {
        $lines = array_map(fn($line) => [
            'Aantal' => $line->getAmount(),
            'Eenheid' => $line->getUnit(),
            'Code' => $line->getCode(),
            'Omschrijving' => $line->getDescription(),
            'PrijsPerEenheid' => $line->getPrice(),
            'BTWCode' => $line->getTaxCode(),
            'TegenrekeningCode' => $line->getLedgerCode(),
            'KostenplaatsID' => 0
        ], $invoice->getLines());

        return [
            "Factuurnummer" => $invoice->getInvoiceNumber(),
            "Relatiecode" => $invoice->getRelationCode(),
            "Datum" => (new DateTime())->format('c'),
            "Betalingstermijn" => $this->config['payment_term'],
            "Factuursjabloon" => $this->config['invoice_template'],
            "PerEmailVerzenden" => 0,
            "EmailOnderwerp" => "",
            "EmailBericht" => "",
            "EmailVanAdres" => $this->config['email_from_address'],
            "EmailVanNaam" => $this->config['email_from_name'],
            "AutomatischeIncasso" => 0,
            "IncassoIBAN" => "",
            "IncassoMachtigingSoort" => "",
            "IncassoMachtigingID" => "",
            "IncassoMachtigingDatumOndertekening" => (new DateTime("1970-01-01 00:00:00"))->format('c'),
            "IncassoMachtigingFirst" => 0,
            "IncassoRekeningNummer" => "",
            "IncassoTnv" => "",
            "IncassoPlaats" => "",
            "IncassoOmschrijvingRegel1" => "",
            "IncassoOmschrijvingRegel2" => "",
            "IncassoOmschrijvingRegel3" => "",
            "InBoekhoudingPlaatsen" => 1,
            "BoekhoudmutatieOmschrijving" => $invoice->getDescription(),
            "Regels" => $lines
        ];
    }

    private function getORel(AccountingRelation $relation): array
    {
        $id = $relation->getId();

        if (empty($id) || $id == 1) {
            $id = 0;
        }

        return [
            "ID" => $id,
            "AddDatum" => ($relation->getAddDate() ?? new DateTime())->format('c'),
            "Code" => (string) $relation->getCode() ?? '',
            "Bedrijf" => $relation->getCompany() ?? '',
            "Contactpersoon" => $relation->getContact() ?? '',
            "Geslacht" => $relation->getGender() ?? '',
            "Adres" => $relation->getAddress() ?? '',
            "Postcode" => $relation->getZipcode() ?? '',
            "Plaats" => $relation->getCity() ?? '',
            "Land" => $relation->getCountry() ?? '',
            "Adres2" => "",
            "Postcode2" => "",
            "Plaats2" => "",
            "Land2" => "",
            "Telefoon" => $relation->getPhone() ?? '',
            "GSM" => $relation->getCellPhone() ?? '',
            "FAX" => "",
            "Email" => $relation->getEmail() ?? '',
            "Site" => $relation->getSite() ?? '',
            "Notitie" => $relation->getNotes() ?? '',
            "Bankrekening" => "",
            "Girorekening" => "",
            "BTWNummer" => $relation->getVatNumber() ?? '',
            "Aanhef" => "",
            "IBAN" => "",
            "BIC" => "",
            "BP" => "",
            "Def1" => "",
            "Def2" => "",
            "Def3" => "",
            "Def4" => "",
            "Def5" => "",
            "Def6" => "",
            "Def7" => "",
            "Def8" => "",
            "Def9" => "",
            "Def10" => "",
            "LA" => "",
            "Gb_ID" => 0,
            "GeenEmail" => 0,
            "NieuwsbriefgroepenCount" => 0
        ];
    }
}