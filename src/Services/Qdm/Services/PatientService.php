<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Ken Chapple <ken@mi-squared.com>
 * @copyright Copyright (c) 2021 Ken Chapple <ken@mi-squared.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU GeneralPublic License 3
 */

namespace OpenEMR\Services\Qdm\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Cqm\Qdm\BaseTypes\Address;
use OpenEMR\Cqm\Qdm\BaseTypes\Code;
use OpenEMR\Cqm\Qdm\BaseTypes\DateTime;
use OpenEMR\Cqm\Qdm\BaseTypes\Interval;
use OpenEMR\Cqm\Qdm\BaseTypes\Telcom;
use OpenEMR\Cqm\Qdm\Identifier;
use OpenEMR\Cqm\Qdm\Patient;
use OpenEMR\Cqm\Qdm\PatientCharacteristicBirthdate;
use OpenEMR\Cqm\Qdm\PatientCharacteristicEthnicity;
use OpenEMR\Cqm\Qdm\PatientCharacteristicExpired;
use OpenEMR\Cqm\Qdm\PatientCharacteristicPayer;
use OpenEMR\Cqm\Qdm\PatientCharacteristicRace;
use OpenEMR\Cqm\Qdm\PatientCharacteristicSex;
use OpenEMR\Services\Qdm\Interfaces\QdmServiceInterface;
use OpenEMR\Services\Qdm\QdmRecord;

class PatientService extends AbstractQdmService implements QdmServiceInterface
{
    public function getPatientIdColumn(): string
    {
        return 'P.pid';
    }

    public function getSqlStatement(): string
    {
        $sql = "SELECT
                    P.pid,
                    fname,
                    lname,
                    DOB,
                    P.race,
                    RACE.notes AS race_code,
                    P.ethnicity,
                    ETHN.notes AS ethnicity_code,
                    P.sex,
                    SEX.codes AS sex_code,
                    P.street,
                    P.postal_code,
                    P.city,
                    P.state,
                    P.phone_home,
                    P.phone_cell,
                    P.country_code,
                    P.deceased_date,
                    P.deceased_reason
            FROM patient_data P
            LEFT JOIN list_options RACE ON RACE.list_id = 'race' AND P.race = RACE.option_id
            LEFT JOIN list_options ETHN ON ETHN.list_id = 'ethnicity' AND P.ethnicity = ETHN.option_id
            LEFT JOIN list_options SEX ON SEX.list_id = 'sex' AND P.sex = SEX.option_id
            ";
        return $sql;
    }

    private const ALWAYS = '1000-01-01';
    private const OPEN = '9999-12-31';

    /** @var array<int, ?string> the Source of Payment Typology code by company id */
    private static array $payerCodes = [];

    private static function payerCode(int $companyId): ?string
    {
        if (!array_key_exists($companyId, self::$payerCodes)) {
            $sop = QueryUtils::fetchSingleValue('SELECT cqm_sop FROM insurance_companies WHERE id = ?', 'cqm_sop', [$companyId]);
            self::$payerCodes[$companyId] = is_scalar($sop) && (string)$sop !== '' ? (string)$sop : null;
        }
        return self::$payerCodes[$companyId];
    }

    public static function makeQdmIdentifier($namingSystem, $value): Identifier
    {
        return new Identifier(
            [
                'namingSystem' => $namingSystem,
                'value' => $value
            ]
        );
    }

    public function makeQdmModel(QdmRecord $recordObj): Patient
    {
        $record = $recordObj->getData();
        // Make a BSON-formatted ID that the CQM-execution service will preserve when results returned
        // so we can associate results with patients.
        // This 'underscore' id is not part of QDM, but special for the cqm-execution calculator.
        $_id = self::convertToObjectIdBSONFormat($record['pid']);

        // Make a formatted BSON that can be used as an index in a PHP associative array,
        // this will be used for aggregating results
        $formatted_id = self::convertIdFromBSONObjectIdFormat($_id);
        $id = self::makeQdmIdentifier('OpenEMR BSON', $formatted_id);

        $qdmPatient = new Patient([
            'patientName' => [
                'given' => $record['fname'],
                'middle' => $record['mname'] ?? '',
                'family' => $record['lname']
            ],
            'birthDatetime' => $record['DOB'],
            'id' => $id, // From PatientExtension trait
            '_id' => $_id //
        ]);

        $qdmPatient->extendedData = [
            'pid' => $record['pid']
        ];

        // Create the address and add to model (for QRDA Cat 1 Export)
        $address = new Address(
            [
                'street' => [
                    $record['street']
                ],
                'city' => $record['city'],
                'state' => $record['state'],
                'zip' => $record['postal_code'],
                'country' => $record['country_code']
            ]
        );
        $qdmPatient->addAddress($address);

        // Create the telcom and add to model (for QRDA Cat 1 Export)
        $telcom = new Telcom(
            [
                'value' => $record['phone_home']
            ]
        );
        $qdmPatient->addTelcom($telcom);

        $qdmPatient->extendedData = [
            'pid' => $record['pid']
        ];

        $pcdob = new PatientCharacteristicBirthdate(
            [
                'birthDatetime' => new DateTime(
                    [
                        'date' => $record['DOB']
                    ]
                ),
                'dataElementCodes' => [
                    new Code(
                        [
                            'code' => '21112-8',
                            'system' => '2.16.840.1.113883.6.1'
                        ]
                    )
                ]
            ]
        );
        $qdmPatient->add_data_element($pcdob);

        // If th patient is deceased/expired, then add the Patient Characteristic Expired
        if (!empty($record['deceased_date'])) {
            $pcexp = new PatientCharacteristicExpired([
                'expiredDatetime' => $record['deceased_date'],
                'cause' => $this->makeQdmCode($record['deceased_reason'])
            ]);
            $qdmPatient->add_data_element($pcexp);
        }

        // Twin (insurance-reference): one payer per run of days a primary policy heads its level
        // (DEFAULT 4, #15038: every primary, each with its own start and stop; the CQL keeps those that
        // overlap the measurement period). Master joined every primary row and kept the last fetched,
        // with no stop. A patient with no primary keeps master's single payer with no code.
        // Every day the rule can answer: a run that reaches the range's first day began "always" (a blank
        // start), one that reaches its last day is open.
        $always = new \DateTimeImmutable(self::ALWAYS);
        $open = new \DateTimeImmutable(self::OPEN);
        $segments = (new \OpenEMR\Services\InsuranceService())->getCoverageSegments(
            (int)$record['pid'],
            \OpenEMR\Services\Insurance\InsuranceLevel::Primary,
            $always,
            $open
        );
        $periods = [];
        foreach ($segments as $segment) {
            $periods[] = [
                $segment->from == $always ? null : $segment->from->format('Y-m-d'),
                $segment->to == $open ? null : $segment->to->format('Y-m-d'),
                self::payerCode($segment->policy->companyId),
            ];
        }
        if ($periods === []) {
            $periods[] = [null, null, null];
        }
        foreach ($periods as [$low, $high, $code]) {
            $payer = new PatientCharacteristicPayer([
                'relevantPeriod' => new Interval([
                    'low' => new DateTime([
                        'date' => $low
                    ]),
                    'high' => $high === null ? null : new DateTime(['date' => $high]),
                    'lowClosed' => $low !== null,
                    'highClosed' => $high !== null
                ]),
                'dataElementCodes' => [
                    new Code(
                        [
                            'code' => $code,
                            'system' => '2.16.840.1.113883.3.221.5'
                        ]
                    )
                ]
            ]);
            $qdmPatient->add_data_element($payer);
        }

        // Reference: https://phinvads.cdc.gov/vads/ViewCodeSystem.action?id=2.16.840.1.113883.6.238
        $pcr = new PatientCharacteristicRace(
            [
                'dataElementCodes' => [
                    new Code(
                        [
                            'code' => $record['race_code'],
                            'system' => '2.16.840.1.113883.6.238'
                        ]
                    )
                ]
            ]
        );
        $qdmPatient->add_data_element($pcr);

        $pce = new PatientCharacteristicEthnicity(
            [
                'dataElementCodes' => [
                    new Code(
                        [
                            'code' => $record['ethnicity_code'],
                            'system' => '2.16.840.1.113883.6.238'
                        ]
                    )
                ]
            ]
        );
        $qdmPatient->add_data_element($pce);

        $pcs = new PatientCharacteristicSex();
        // Get the code from the database and use our parent function to split by ':'
        // Because in DB it looks like 'HL7:M'
        // These HL7 code types aren't in our code types service, so we hard-code it.
        // TODO parse the cdavocabmap.xml into a service
        $code = $this->makeQdmCode($record['sex_code']);
        if ($code instanceof Code) {
            // http://www.hl7.org/documentcenter/public/standards/vocabulary/vocabulary_tables/infrastructure/vocabulary/vs_AdministrativeGender.html
            $code->system = '2.16.840.1.113883.5.1';
            $pcs->addCode($code);
        }
        $qdmPatient->add_data_element($pcs);

        return $qdmPatient;
    }
}
