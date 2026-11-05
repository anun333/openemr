<?php

/**
 * InsuranceService - Service class for patient insurance policy (coverage) data
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Matthew Vita <matthewvita48@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2018 Matthew Vita <matthewvita48@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2024 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2025 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Services;

use DateTimeImmutable;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Events\Services\ServiceSaveEvent;
use OpenEMR\Services\Insurance\CoverageDay;
use OpenEMR\Services\Insurance\CoverageSegment;
use OpenEMR\Services\Insurance\EffectiveInsurance;
use OpenEMR\Services\Insurance\EffectiveInsuranceRow;
use OpenEMR\Services\Insurance\InsuranceLevel;
use OpenEMR\Services\Insurance\InsuranceTables;
use OpenEMR\Services\Search\{
    CompositeSearchField,
    DateSearchField,
    FhirSearchWhereClauseBuilder,
    ISearchField,
    SearchModifier,
    TokenSearchField,
    TokenSearchValue,
};
use OpenEMR\Validators\{
    CoverageValidator,
    ProcessingResult,
};

class InsuranceService extends BaseService
{
    private const COVERAGE_TABLE = InsuranceTables::POLICIES;
    private const PATIENT_TABLE = "patient_data";
    private const INSURANCE_TABLE = InsuranceTables::PAYERS;
    /**
     * @var CoverageValidator $coverageValidator
     */
    private $coverageValidator;


    /**
     * Default constructor.
     */
    public function __construct()
    {
        parent::__construct(self::COVERAGE_TABLE);
        // TODO: we need to migrate the addresses in these tables into the Address table
        UuidRegistry::createMissingUuidsForTables([self::COVERAGE_TABLE, self::PATIENT_TABLE, self::INSURANCE_TABLE]);
        $this->coverageValidator = new CoverageValidator();
    }

    public function getUuidFields(): array
    {
        return ['uuid', 'puuid', 'insureruuid'];
    }

    public function validate($data)
    {
        return $this->coverageValidator->validate($data);
    }

    public function getOneByPid($id, $type)
    {
        return ServiceContainer::getInsuranceCoverage()->rowByPidAndType($id, $type);
    }

    public function search(array $search, $isAndCondition = true): ProcessingResult
    {
        $whereClause = FhirSearchWhereClauseBuilder::build($search, $isAndCondition);
        $statementResults = ServiceContainer::getInsuranceCoverage()->searchStatement($whereClause->getFragment(), $whereClause->getBoundValues());

        $processingResult = new ProcessingResult();
        while ($row = sqlFetchArray($statementResults)) {
            $resultRecord = $this->createResultRecordFromDatabaseResult($row);
            $processingResult->addData($resultRecord);
        }
        return $processingResult;
    }

    public function getOne($uuid): ProcessingResult
    {

        $processingResult = new ProcessingResult();
        $isValid = $this->coverageValidator->validateId('uuid', self::COVERAGE_TABLE, $uuid, true);
        if ($isValid !== true) {
            return $isValid;
        }
        $uuidBytes = UuidRegistry::uuidToBytes($uuid);
        $sqlResult = ServiceContainer::getInsuranceCoverage()->rowByUuid($uuidBytes);
        if ($sqlResult) {
            $sqlResult['uuid'] = UuidRegistry::uuidToString($sqlResult['uuid']);
            $processingResult->addData($sqlResult);
        } else {
            $processingResult->addInternalError("error processing SQL");
        }
        return $processingResult;
    }

    public function doesInsuranceTypeHaveEntry($pid, $type = '')
    {
        if (!empty($type)) {
            return ServiceContainer::getInsuranceCoverage()->idAtLevel($pid, $type);
        }
        return ServiceContainer::getInsuranceCoverage()->hasPolicy($pid);
    }

    public function update($data): ProcessingResult
    {
        $validationResult = $this->coverageValidator->validate($data, CoverageValidator::DATABASE_UPDATE_CONTEXT);
        if (!$validationResult->isValid()) {
            return $validationResult;
        }
        return $this->writeUpdate($data);
    }

    /**
     * Updates a policy from a document import (see insertImported()), checked in the import
     * context. Like update(), it writes every column, so pass the stored row with the
     * document's values over it: what the caller leaves out is written as NULL.
     *
     * @param array<mixed> $data
     */
    public function updateImported(array $data): ProcessingResult
    {
        $validationResult = $this->coverageValidator->validate($data, CoverageValidator::DATABASE_IMPORT_CONTEXT);
        if (!$validationResult->isValid()) {
            return $validationResult;
        }
        if (!is_string($data['uuid'] ?? null)) {
            $processingResult = new ProcessingResult();
            $processingResult->setValidationMessages(['uuid' => 'Coverage UUID must be provided']);
            return $processingResult;
        }
        return $this->writeUpdate($data);
    }

    /**
     * The update statement, for a record the caller has validated.
     */
    private function writeUpdate(mixed $data): ProcessingResult
    {
        $processingResult = new ProcessingResult();

        // When the caller supplied a pid (REST patient-compartment path), confirm
        // the row keyed by $data['uuid'] actually belongs to that patient before
        // writing. Without this check a leaked insurance uuid supplied under one
        // patient's path could update a row on another patient's chart because
        // the UPDATE below is keyed on uuid alone. Reported as not-found rather
        // than forbidden so a uuid probe cannot confirm the existence of another
        // patient's policy.
        $callerPid = is_array($data) ? ($data['pid'] ?? null) : null;
        $callerUuid = is_array($data) ? ($data['uuid'] ?? null) : null;
        if (is_numeric($callerPid) && is_string($callerUuid) && $callerUuid !== '') {
            $uuidBinaryCheck = UuidRegistry::uuidToBytes($callerUuid);
            $rowPidRaw = ServiceContainer::getInsuranceCoverage()->pidByUuid($uuidBinaryCheck);
            if (!is_numeric($rowPidRaw) || (int) $rowPidRaw !== (int) $callerPid) {
                $notFound = new ProcessingResult();
                $notFound->setValidationMessages(['uuid' => 'Insurance policy not found']);
                return $notFound;
            }
        }

        $serviceSaveEvent = new ServiceSaveEvent($this, $data);
        $this->getEventDispatcher()->dispatch($serviceSaveEvent, ServiceSaveEvent::EVENT_PRE_SAVE);
        $data = $serviceSaveEvent->getSaveData();
        $uuid = UuidRegistry::uuidToBytes($data['uuid']);

        $results = ServiceContainer::getInsuranceCoverage()->updateByUuid(
            [
                $data["provider"],
                $data["plan_name"],
                $data["policy_number"],
                $data["group_number"],
                $data["subscriber_lname"],
                $data["subscriber_mname"],
                $data["subscriber_fname"],
                $data["subscriber_relationship"],
                $data["subscriber_ss"],
                $data["subscriber_DOB"],
                $data["subscriber_street"],
                $data["subscriber_postal_code"],
                $data["subscriber_city"],
                $data["subscriber_state"],
                $data["subscriber_country"],
                $data["subscriber_phone"],
                $data["subscriber_employer"],
                $data["subscriber_employer_street"],
                $data["subscriber_employer_postal_code"],
                $data["subscriber_employer_state"],
                $data["subscriber_employer_country"],
                $data["subscriber_employer_city"],
                $data["copay"],
                $data["date"],
                empty($data["date_end"]) ? null : $data["date_end"],
                $data["subscriber_sex"],
                $data["accept_assignment"],
                $data["policy_type"],
                $data['type'],
                $uuid
            ]
        );
        if ($results) {
            $serviceSavePostEvent = new ServiceSaveEvent($this, $data);
            $this->getEventDispatcher()->dispatch($serviceSavePostEvent, ServiceSaveEvent::EVENT_POST_SAVE);
            $processingResult = $this->getOne($data['uuid']);
        } else {
            $processingResult->addInternalError("error processing SQL Update");
        }
        return $processingResult;
    }

    public function insert($data): ProcessingResult
    {
        $validationResult = $this->coverageValidator->validate($data, CoverageValidator::DATABASE_INSERT_CONTEXT);
        if (!$validationResult->isValid()) {
            return $validationResult;
        }

        $data['uuid'] = UuidRegistry::getRegistryForTable(self::COVERAGE_TABLE)->createUuid();

        $serviceSaveEvent = new ServiceSaveEvent($this, $data);
        $dispatchedEvent = $this->getEventDispatcher()->dispatch($serviceSaveEvent, ServiceSaveEvent::EVENT_PRE_SAVE);
        $data = $dispatchedEvent->getSaveData();
        // as update() writes it: a policy with no end date is open-ended (NULL)
        $dateEnd = $data['date_end'] ?? null;
        $dateEnd = is_string($dateEnd) && $dateEnd !== '' ? $dateEnd : null;

        $insuranceDataId = ServiceContainer::getInsuranceCoverage()->insertPolicy(
            [
                $data['uuid'],
                $data['type'],
                $data["provider"],
                $data["plan_name"] ?? '',
                $data["policy_number"] ?? '',
                $data["group_number"] ?? '',
                $data["subscriber_lname"] ?? '',
                $data["subscriber_mname"] ?? '',
                $data["subscriber_fname"] ?? '',
                $data["subscriber_relationship"] ?? '',
                $data["subscriber_ss"] ?? '',
                $data["subscriber_DOB"] ?? '',
                $data["subscriber_street"] ?? '',
                $data["subscriber_postal_code"] ?? '',
                $data["subscriber_city"] ?? '',
                $data["subscriber_state"] ?? '',
                $data["subscriber_country"] ?? '',
                $data["subscriber_phone"] ?? '',
                $data["subscriber_employer"] ?? '',
                $data["subscriber_employer_street"] ?? '',
                $data["subscriber_employer_postal_code"] ?? '',
                $data["subscriber_employer_state"] ?? '',
                $data["subscriber_employer_country"] ?? '',
                $data["subscriber_employer_city"] ?? '',
                $data["copay"] ?? '',
                $data["date"] ?? '',
                $dateEnd,
                $data['pid'],
                $data["subscriber_sex"] ?? '',
                $data["accept_assignment"] ?? '',
                $data["policy_type"] ?? ''
            ]
        );
        // I prefer exceptions... but we will try to match other service handler formats for consistency
        $processingResult = new ProcessingResult();
        $stringUuid = UuidRegistry::uuidToString($data['uuid']);
        if ($insuranceDataId) {
            $data['id'] = $insuranceDataId;
            $processingResult->addData([
                'id' => $insuranceDataId
                ,'uuid' => $stringUuid
            ]);
            $this->getEventDispatcher()->dispatch($serviceSaveEvent, ServiceSaveEvent::EVENT_POST_SAVE);
            $processingResult = $this->getOne($stringUuid);
        } else {
            $processingResult->addInternalError("error processing SQL Update");
        }

        return $processingResult;
    }

    /**
     * Inserts a policy that a document import brings in, such as a QRDA Category I document's
     * Patient Characteristic Payer: the patient, the policy type, the payer and its dates.
     *
     * Checked in CoverageValidator's import context, which asks only for those. The document has
     * no policy number, plan name or subscriber, so those columns keep their defaults (NULL)
     * rather than a value it doesn't state. insert() keeps the full rules for every other caller.
     *
     * @param array<mixed> $data pid, type, provider, date (Y-m-d) and date_end (Y-m-d or null; the key is
     *                           required once there is a date, as in insert())
     */
    public function insertImported(array $data): ProcessingResult
    {
        $validationResult = $this->coverageValidator->validate($data, CoverageValidator::DATABASE_IMPORT_CONTEXT);
        if (!$validationResult->isValid()) {
            return $validationResult;
        }

        $uuid = UuidRegistry::getRegistryForTable(self::COVERAGE_TABLE)->createUuid();
        $data['uuid'] = $uuid;
        $serviceSaveEvent = new ServiceSaveEvent($this, $data);
        $this->getEventDispatcher()->dispatch($serviceSaveEvent, ServiceSaveEvent::EVENT_PRE_SAVE);
        $data = $serviceSaveEvent->getSaveData();

        $dateEnd = $data['date_end'] ?? null;
        $data['id'] = ServiceContainer::getInsuranceCoverage()->insertImportedPolicy(
            [$uuid, $data['pid'], $data['type'], $data['provider'], $data['date'], $dateEnd === '' ? null : $dateEnd]
        );
        $this->getEventDispatcher()->dispatch(new ServiceSaveEvent($this, $data), ServiceSaveEvent::EVENT_POST_SAVE);

        return $this->getOne(UuidRegistry::uuidToString($uuid));
    }

    /**
     * Return an array of encounters within a date range
     *
     * @param  $provider   Insurance company id
     * @param  $startDate  Any encounter starting on this date
     * @param  $endDate    Any encounter ending on this date
     * @return Array       Insurance data payload.
     */
    public function getPoliciesByPayerByEffectiveDate($provider, $type, $startDate, $endDate)
    {
        // most common case of null in 'date' field aka effective date which signifies is only insurance of that type
        // TBD: add another token for 'date_end' field
        $dateMissing = new TokenSearchField('date', [new TokenSearchValue(null)]);
        $dateMissing->setModifier(SearchModifier::MISSING);

        $dateEndMissing = new TokenSearchField('date_end', [new TokenSearchValue(null)]);
        $dateEndMissing->setModifier(SearchModifier::MISSING);

        // search for encounters by passed in start and end dates
        $dateField = new DateSearchField('date', ['ge' . $startDate, 'le' . $endDate], DateSearchField::DATE_TYPE_DATE);
        $dateEndField = new DateSearchField('date_end', ['ge' . $endDate], DateSearchField::DATE_TYPE_DATE);

        // set up composite search with false signifying an OR condition for the effective date
        $compositeDate = new CompositeSearchField('date', [], false);
        $compositeDate->addChild($dateMissing);
        $compositeDate->addChild($dateField);

        // set up composite search with false signifying an OR condition for the date end
        $compositeDateEnd = new CompositeSearchField('date_end', [], false);
        $compositeDateEnd->addChild($dateEndMissing);
        $compositeDateEnd->addChild($dateEndField);

        $insuranceDataResult = $this->search(
            [
                'provider' => $provider,
                'type' => $type,
                'date' => $compositeDate,
                'date_end' => $compositeDateEnd
            ]
        );
        $result = $insuranceDataResult->hasData() ? $insuranceDataResult->getData() : [];

        return $result;
    }

    public function getPoliciesOrganizedByTypeForPatientPid($pid): array
    {
        $insurancePolicies = $this->search(['pid' => $pid]);
        $result = [];
        foreach ($insurancePolicies->getData() as $insurancePolicy) {
            if (empty($insurancePolicy['type'])) {
                $result[$insurancePolicy['type']] = [];
                continue;
            }
            $result[$insurancePolicy['type']][] = $insurancePolicy;
        }
        // Twin (insurance-reference): "current" is the policy the coverage service
        // has in force today at its level; the others keep search()'s order. A
        // level with nothing in force keeps search()'s first row, as before.
        $inForce = [];
        $today = (new CoverageDay(ServiceContainer::getClock()))->today();
        foreach ($this->getEffectiveInsurances(is_numeric($pid) ? (int)$pid : 0, $today) as $policy) {
            $inForce[$policy->level->value] = $policy->id;
        }
        $organizedResults = [];
        foreach ($result as $key => $policies) {
            if (count($policies) > 0) {
                foreach ($policies as $i => $policy) {
                    $policyId = is_array($policy) ? ($policy['id'] ?? null) : null;
                    if (isset($inForce[$key]) && is_numeric($policyId) && (int)$policyId === $inForce[$key]) {
                        array_unshift($policies, ...array_splice($policies, $i, 1));
                        break;
                    }
                }
                reset($policies);
                $current = current($policies);
                $history = array_splice($policies, 1);

                $organizedResults[$key] = [
                    'current' => $current
                    ,'history' => $history // we want in descending order
                ];
            }
        }
        return $organizedResults;
    }

    /**
     * The policies in force for a patient on a day: at most one per level,
     * primary first, by the rule in EffectiveInsuranceRule. That is the
     * claim's rule (Claim::loadPayerInfo()) with three differences, which its
     * docblock lists. A level with nothing in force is left out and the others
     * are still returned, so a current secondary behind an ended primary comes
     * back on its own.
     *
     * getEffectiveInsurances() in library/patient.inc.php answers the same
     * question, but stops at the first level with nothing in force.
     *
     * @param DateTimeImmutable $day the day asked about (date of service, order or document); its time is ignored
     * @return list<EffectiveInsurance>
     */
    public function getEffectiveInsurances(int $pid, DateTimeImmutable $day): array
    {
        return ServiceContainer::getInsuranceCoverage()->getEffectiveInsurances($pid, $day);
    }

    /**
     * The policies in force on a day, as getEffectiveInsurances() chooses them
     * and in its order, each with its insurance_data row and its company's
     * name: for callers that show or send the plan, the policy number or the
     * subscriber (the lab IN1 and requisitions, the claim, the 270). An
     * inactive company's name comes back too: inactive keeps a company off the
     * pick lists, not off the policies already on file.
     *
     * @return list<EffectiveInsuranceRow>
     */
    public function getEffectiveInsuranceRows(int $pid, DateTimeImmutable $day): array
    {
        return ServiceContainer::getInsuranceCoverage()->getEffectiveInsuranceRows($pid, $day);
    }

    /**
     * Many patients' insurance_data rows, by pid, as EffectiveInsuranceRule
     * reads them (id, uuid, pid, type, provider, date, date_end): for reports
     * and the eCQM run, which read once and then ask the rule about each
     * patient's own days (EffectiveInsuranceRule::choose()) or period
     * (EffectiveInsuranceRule::segments()). One query per 1000 patients. A
     * patient with no rows has no key.
     *
     * @param list<int> $pids
     * @return array<int, list<array<mixed>>>
     */
    public function getRuleRowsByPid(array $pids): array
    {
        return ServiceContainer::getInsuranceCoverage()->getRuleRowsByPid($pids);
    }

    /**
     * The runs of days within a range on which each policy heads a level
     * (EffectiveInsuranceRule::segments()), oldest first: for readers that
     * report over a period, such as eCQM's payer over the measurement period.
     *
     * @return list<CoverageSegment>
     */
    public function getCoverageSegments(int $pid, InsuranceLevel $level, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return ServiceContainer::getInsuranceCoverage()->getCoverageSegments($pid, $level, $from, $to);
    }

    /**
     * The policy in force at one level on a day, or null when the level has
     * none, as getEffectiveInsurances() chooses it.
     */
    public function getEffectiveInsuranceAt(int $pid, InsuranceLevel $level, DateTimeImmutable $day): ?EffectiveInsurance
    {
        return ServiceContainer::getInsuranceCoverage()->getEffectiveInsuranceAt($pid, $level, $day);
    }

    public function swapInsurance($pid, string $targetType, string $insuranceUuid): ProcessingResult
    {
        $transactionCommitted = false;
        $validateData = ['pid' => $pid, 'type' => $targetType, 'uuid' => $insuranceUuid];
        $validationResult = $this->coverageValidator->validate($validateData, CoverageValidator::DATABASE_SWAP_CONTEXT);
        if (!$validationResult->isValid()) {
            return $validationResult;
        }
        $processingResult = new ProcessingResult();

        try {
            QueryUtils::startTransaction();

            $targetUuid = ServiceContainer::getInsuranceCoverage()->latestUuidAtLevel($pid, $targetType);
            $targetInsurance = null;
            if (!empty($targetUuid)) {
                $targetResult = $this->getOne(UuidRegistry::uuidToString($targetUuid));
                if ($targetResult->hasData()) {
                    $targetInsurance = $targetResult->getFirstDataResult();
                    if (!$this->coverageValidator->validate($targetInsurance, CoverageValidator::DATABASE_UPDATE_CONTEXT)->isValid()) {
                        $processingResult->setValidationMessages(
                            [
                                'type' => ['Record::TARGET_INSURANCE_UPDATE_PROHIBITED' => xl('Target insurance could not be saved as it was missing data required for database updates')]
                            ]
                        );
                        // note the finally clause will rollback the transaction
                        return $processingResult;
                    }
                }
            }
            $srcResult = $this->getOne($insuranceUuid);
            if (!$srcResult->hasData()) {
                // shouldn't happen as the validator should have caught this
                throw new \InvalidArgumentException("Could not find insurance policy with uuid: $insuranceUuid");
            }
            $srcInsurance = $srcResult->getFirstDataResult();
            if (!$this->coverageValidator->validate($srcInsurance, CoverageValidator::DATABASE_UPDATE_CONTEXT)->isValid()) {
                $processingResult->setValidationMessages(
                    [
                        'type' => ['Record::SOURCE_INSURANCE_UPDATE_PROHIBITED' => xl('Source insurance could not be saved as it was missing data required for database updates')]
                    ]
                );
                // note the finally clause will rollback the transaction
                return $processingResult;
            }

            // we need to look at changing up the date of the current insurance policy
            $resetStartDate = null;
            if (!empty($targetInsurance)) {
                $resetStartDate = $targetInsurance['date'];
                if ($resetStartDate != $srcInsurance['date']) {
                    $resetStartDate = null;
                } else {
                    // set to the largest possible date we can which should not conflict with any possible date
                    // I don't like this but due to the pid-type-date db constraint we have to set a temporary date so we don't conflict
                    // with the current insurance policy, the chances of conflict are infitisemally small.
                    // If OpenEMR is still around in 7K+ years, someone should have fixed this by then.
                    // again since its all wrapped in a transaction, this date should never permanently save
                    $targetInsurance["date"] = "9999-12-31";
                }
                $targetInsurance['type'] = $srcInsurance['type'];
                $updateResult = $this->update($targetInsurance);
                if ($updateResult->hasErrors()) {
                    throw new \InvalidArgumentException("Failed to update insurance policy with uuid: $insuranceUuid");
                }
            }

            // we have to do this in multiple steps due to the way the db constraint on the type and date are set
            $srcInsurance['type'] = $targetType;
            // Checked like the target update above: update() reports a failed
            // write in its result rather than throwing, so an unchecked result
            // would let a half-finished swap reach commitTransaction().
            $srcUpdateResult = $this->update($srcInsurance);
            if ($srcUpdateResult->hasErrors()) {
                throw new \InvalidArgumentException("Failed to update insurance policy with uuid: $insuranceUuid");
            }

            if (!empty($targetInsurance)) {
                if (!empty($resetStartDate)) {
                    $targetInsurance["date"] = $resetStartDate;
                }
                $targetDateResult = $this->update($targetInsurance);
                if ($targetDateResult->hasErrors()) {
                    throw new \InvalidArgumentException("Failed to update insurance policy with uuid: $insuranceUuid");
                }
            }
            QueryUtils::commitTransaction();
            $transactionCommitted = true;
            $result = [
                'src' => $srcInsurance
                ,'target' => $targetInsurance
            ];
            $processingResult->addData($result);
        } catch (\Throwable $e) {
            $processingResult->addInternalError($e->getMessage());
        } finally {
            try {
                if (!$transactionCommitted) {
                    QueryUtils::rollbackTransaction();
                }
            } catch (\Throwable $e) {
                ServiceContainer::getLogger()->error(
                    "Failed to rollback transaction " . $e->getMessage(),
                    ['exception' => $e, 'type' => $targetType, 'insuranceUuid' => $insuranceUuid, 'pid' => $pid]
                );
            }
        }
        return $processingResult;
    }
}
