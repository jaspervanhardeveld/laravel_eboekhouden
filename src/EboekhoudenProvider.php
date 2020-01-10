<?php
namespace Dvb\Eboekhouden;

use Carbon\Carbon;
use DateTime;
use Dvb\Accounting\AccountingException;
use Dvb\Accounting\AccountingProvider;
use Dvb\Accounting\MutationFilter;
use SoapClient;

class EboekhoudenProvider implements AccountingProvider {

    /**
     * @var SoapClient
     */
    private $soapClient;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $sec_code_1;

    /**
     * @var string
     */
    private $sec_code_2;

    /**
     * @var string
     */
    private $session_id;

    public function __construct()
    {
        $this->username = config('eboekhouden.username');
        $this->sec_code_1 = config('eboekhouden.security_code1');
        $this->sec_code_2 = config('eboekhouden.security_code2');
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
            $this->soapClient = new SoapClient(config('eboekhouden.wsdl'));
        } catch (\SoapFault $exception) {
            throw new AccountingException($exception->getMessage());
        }

        $result = $this->soapClient->__soapCall('OpenSession', [
            "OpenSession" => [
                "Username" => $this->username,
                "SecurityCode1" => $this->sec_code_1,
                "SecurityCode2" => $this->sec_code_2
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
     * @inheritDoc
     */
    public function getRelations(): array
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('GetRelaties', [
            'GetRelaties' => [
                'SessionID' => $this->session_id,
                'SecurityCode2' => $this->sec_code_2,
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

        return $relations;
    }

    /**
     * @inheritDoc
     */
    public function getLedgers(): array
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('GetGrootboekrekeningen', [
            "GetGrootboekrekeningen" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->sec_code_2,
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

        return $ledgers;
    }

    /**
     * @inheritDoc
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
                'SecurityCode2' => $this->sec_code_2,
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

        if (!is_array($result->GetMutatiesResult->Mutaties->cMutatieList)) {
            return [$result->GetMutatiesResult->Mutaties->cMutatieList];
        }

        return $result->GetMutatiesResult->Mutaties->cMutatieList;
    }

    /**
     * @inheritDoc
     */
    public function addInvoice(array $work): string
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('AddFactuur', [
            "AddFactuur" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->sec_code_2,
                "oFact" => $this->getOFact($work)
            ]
        ]);

        $this->checkError('AddFactuur', $result);

        return (string) $result->AddFactuurResult->Factuurnummer;
    }

    /**
     * @inheritDoc
     */
    public function addRelation(array $relation): array
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('AddRelatie', [
            "AddRelatie" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->sec_code_2,
                "oRel" => $this->getORel($relation)
            ]
        ]);

        $this->checkError('AddRelatie', $result);

        $relation['id_eboekhouden'] = (int) $result->AddRelatieResult->Rel_ID;

        return $relation;
    }

    /**
     * @inheritDoc
     */
    public function updateRelation(array $relation): array
    {
        $this->createSoapClient();

        $result = $this->soapClient->__soapCall('UpdateRelatie', [
            "UpdateRelatie" => [
                "SessionID" => $this->session_id,
                "SecurityCode2" => $this->sec_code_2,
                "oRel" => $this->getORel($relation)
            ]
        ]);

        $this->checkError('UpdateRelatie', $result);

        return $relation;
    }

    private function getOFact(array $work): array
    {
        $hours = collect($work['hours'] ?? []);
        $products = collect($work['products'] ?? []);

        $lines = array_merge(
            $hours->map(function ($hour) use ($work) {
                return [
                    "Aantal" => $hour['hours'],
                    "Eenheid" => "Uur",
                    "Code" => "1",
                    "Omschrijving" => "Gewerkte uren, " . Carbon::make($hour['work_date'])->format('d-m-Y'),
                    "PrijsPerEenheid" => $hour['price_per_hour'],
                    "BTWCode" => $work['tax_code'],
                    "TegenrekeningCode" => (string) $work['ledger_code'],
                    "KostenplaatsID" => 0
                ];
            })->toArray(),
            $products->map(function ($product) use ($work) {
                return [
                    "Aantal" => $product['amount'],
                    "Eenheid" => "Stuk",
                    "Code" => $product['code'],
                    "Omschrijving" => $product['description'],
                    "PrijsPerEenheid" => (float) number_format($product['sell_price_per_one'], 2, '.', ''),
                    "BTWCode" => $work['tax_code'],
                    "TegenrekeningCode" => (string) $work['ledger_code'],
                    "KostenplaatsID" => 0
                ];
            })->toArray()
        );

        return [
            "Factuurnummer" => (string) $work['invoice_number'],
            "Relatiecode" => (string) $work['relation_code'],
            "Datum" => (new DateTime())->format('c'),
            "Betalingstermijn" => config('eboekhouden.payment_term'),
            "Factuursjabloon" => config('eboekhouden.invoice_template'),
            "PerEmailVerzenden" => 0,
            "EmailOnderwerp" => "",
            "EmailBericht" => "",
            "EmailVanAdres" => config('eboekhouden.email_from_address'),
            "EmailVanNaam" => config('eboekhouden.email_from_name'),
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
            "BoekhoudmutatieOmschrijving" => $work['description'],
            "Regels" => $lines
        ];
    }

    private function getORel(array $relation): array
    {
        $relation['id_eboekhouden'] = $relation['id_eboekhouden'] ?? 0;

        $id = $relation['id_eboekhouden'] <= 1 ? 0 : $relation['id_eboekhouden'];

        return [
            "ID" => $id,
            "AddDatum" => Carbon::make($relation['add_datum'])->format('c'),
            "Code" => $relation['code'],
            "Bedrijf" => $relation['bedrijf'],
            "Contactpersoon" => $relation['contactpersoon'] ?? '',
            "Geslacht" => $relation['geslacht'] ?? '',
            "Adres" => $relation['adres'] ?? '',
            "Postcode" => $relation['postcode'] ?? '',
            "Plaats" => $relation['plaats'] ?? '',
            "Land" => $relation['land'] ?? '',
            "Adres2" => "",
            "Postcode2" => "",
            "Plaats2" => "",
            "Land2" => "",
            "Telefoon" => $relation['telefoon'] ?? '',
            "GSM" => $relation['gsm'] ?? '',
            "FAX" => "",
            "Email" => $relation['email'] ?? '',
            "Site" => $relation['site'] ?? '',
            "Notitie" => $relation['notitie'] ?? '',
            "Bankrekening" => "",
            "Girorekening" => "",
            "BTWNummer" => $relation['btw_nummer'] ?? '',
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