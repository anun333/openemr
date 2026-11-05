<?php

/**
 * PolicyRecords: the insurance module's PolicyRecords under the name the boundary branch gave it, so its callers are
 * unchanged. Each method is one call through the Coverage contract
 * (OpenEMR\BC\ServiceContainer::getInsuranceCoverage()). To be deprecated once core's callers ask
 * the contract themselves (a @deprecated tag now would put every caller on PHPStan's deprecation
 * rule). Generated from the module's class.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\Insurance;

use OpenEMR\BC\ServiceContainer;

final class PolicyRecords
{
    private function __construct()
    {
    }

    /**
     * The latest-starting policy at a level, ended or not, with its company
     * joined as ic (the procedural getInsuranceData()).
     *
     * @return array<mixed>|false
     */
    public static function latestAtLevel(mixed $pid, mixed $type, string $columns = 'insd.*, ic.name as provider_name'): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->latestAtLevel($pid, $type, $columns);
    }

    /**
     * The latest-starting policy's id at a level (the custom XML and LabWorks
     * exports).
     *
     * @return array<mixed>|false
     */
    public static function latestIdAtLevel(mixed $pid, mixed $type): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->latestIdAtLevel($pid, $type);
    }

    /**
     * The latest-starting secondary whose company exists, with the company's
     * name and id (the id key is the company's): the paper requisitions.
     *
     * @return array<mixed>|false
     */
    public static function latestSecondaryWithPayer(mixed $pid): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->latestSecondaryWithPayer($pid);
    }

    /**
     * Every policy at a level, latest start first, with its company joined as
     * ic (the procedural getInsuranceDataNew()).
     *
     * @return list<array<mixed>>
     */
    public static function historyAtLevel(mixed $pid, mixed $type, string $columns = 'insd.*, ic.name as provider_name'): array
    {
        return ServiceContainer::getInsuranceCoverage()->historyAtLevel($pid, $type, $columns);
    }

    /**
     * Every policy at a level, earliest start first (the patient report's
     * insurance section).
     *
     * @return list<array<mixed>>
     */
    public static function historyOldestFirst(mixed $pid, mixed $type): array
    {
        return ServiceContainer::getInsuranceCoverage()->historyOldestFirst($pid, $type);
    }

    /**
     * Every policy at a level by start, with every company column and the
     * company's name as provider_name (the patient report's change history).
     *
     * @return list<array<mixed>>
     */
    public static function historyWithPayerName(mixed $pid, mixed $type): array
    {
        return ServiceContainer::getInsuranceCoverage()->historyWithPayerName($pid, $type);
    }

    /**
     * The policy at a level that started most recently on or before a day and
     * hasn't ended before it, with its company joined as ic (the procedural
     * getInsuranceDataByDate() and getInsuranceNameByDate()). The day is
     * YYYY-MM-DD.
     *
     * @return array<mixed>|false
     */
    public static function onDateAtLevel(mixed $pid, mixed $date, mixed $type, string $columns): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->onDateAtLevel($pid, $date, $type, $columns);
    }

    /**
     * The primary in force on a day by the billing rule (start on or before,
     * end on or after or none), latest start first: its company and copay
     * (BillingUtilities::getCopay()).
     *
     * @return array<mixed>|false
     */
    public static function primaryCopayOn(mixed $pid, mixed $date): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->primaryCopayOn($pid, $date);
    }

    /**
     * The level and company name of a patient's policy with a given company in
     * force on a day (the Billing Manager's "queued for ... billing to").
     *
     * @return array<mixed>|false
     */
    public static function levelAndPayerNameOn(mixed $pid, mixed $provider, mixed $date): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->levelAndPayerNameOn($pid, $provider, $date);
    }

    /**
     * Every policy, by level, latest start first (the old Copay screen keeps
     * the first of each level).
     *
     * @return list<array<mixed>>
     */
    public static function allByLevelLatestFirst(mixed $pid): array
    {
        return ServiceContainer::getInsuranceCoverage()->allByLevelLatestFirst($pid);
    }

    /**
     * Each policy with a company and an address on file, with the company's
     * name and the address (the procedural getAllinsurances(): the lab
     * requisitions).
     *
     * @return list<array<mixed>>
     */
    public static function requisitionRows(mixed $pid): array
    {
        return ServiceContainer::getInsuranceCoverage()->requisitionRows($pid);
    }

    /**
     * The first policy row's subscriber relationship, in the database's order
     * (the lab's self-pay check, getSelfPay()).
     */
    public static function firstSubscriberRelationship(mixed $pid): mixed
    {
        return ServiceContainer::getInsuranceCoverage()->firstSubscriberRelationship($pid);
    }

    /**
     * The policy a lab's GT1 guarantor names: the first level's latest policy
     * that started on or before a day, ended or not (LabCorp and DORN).
     *
     * @return list<array<mixed>>
     */
    public static function guarantorRowOn(mixed $pid, mixed $date): array
    {
        return ServiceContainer::getInsuranceCoverage()->guarantorRowOn($pid, $date);
    }

    /**
     * Whether a primary names a subscriber: a row with the count (the Billing
     * Manager colours a patient without one).
     *
     * @return array<mixed>|false
     */
    public static function countPrimaryWithSubscriber(mixed $pid): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->countPrimaryWithSubscriber($pid);
    }

    /**
     * How many levels have ever had a company on file (checkout and the portal
     * compare it with the visit's last level closed).
     */
    public static function countLevelsWithPayer(mixed $pid): mixed
    {
        return ServiceContainer::getInsuranceCoverage()->countLevelsWithPayer($pid);
    }

    /**
     * Each policy whose company exists, as level and company name, in the
     * database's order (the invoice summary keeps the last of each level).
     *
     * @return list<array<mixed>>
     */
    public static function levelsAndPayerNames(mixed $pid): array
    {
        return ServiceContainer::getInsuranceCoverage()->levelsAndPayerNames($pid);
    }

    /**
     * The first primary row's id and copay, in the database's order (the 271
     * response saves its verification against it).
     *
     * @return array<mixed>|false
     */
    public static function primaryIdAndCopay(mixed $pid): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->primaryIdAndCopay($pid);
    }

    /**
     * The latest eligibility verification of each policy, with its benefits
     * and the company's name (the eligibility panel).
     *
     * @return list<array<mixed>>
     */
    public static function eligibilityResults(mixed $pid): array
    {
        return ServiceContainer::getInsuranceCoverage()->eligibilityResults($pid);
    }

    /**
     * The company names of the patient's own policies (subscriber self, a
     * company set), one per level as the database groups them: the eRx
     * health plans. The statement's result, as sqlStatement() returns it.
     */
    public static function selfPlanNamesStatement(mixed $pid): \ADORecordSet
    {
        return ServiceContainer::getInsuranceCoverage()->selfPlanNamesStatement($pid);
    }

    /**
     * The first primary's subscriber as a responsible party, when the patient
     * isn't the subscriber (Weno prescribing for a minor).
     *
     * @return array<mixed>|false
     */
    public static function primarySubscriberAsResponsibleParty(mixed $pid): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->primarySubscriberAsResponsibleParty($pid);
    }

    /**
     * The first primary row's subscriber relationship (Weno).
     *
     * @return array<mixed>|false
     */
    public static function primaryRelationshipRow(mixed $pid): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->primaryRelationshipRow($pid);
    }

    /**
     * The first primary's company name, of primaries whose company exists
     * (prior authorizations).
     *
     * @return array<mixed>|false
     */
    public static function primaryPayerNameRow(mixed $pid): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->primaryPayerNameRow($pid);
    }

    /**
     * A policy by id, at a level and for a patient, whose company exists: every
     * policy column, the company's name as company_name and its uuid as
     * compuuid (the C-CDA payer section).
     *
     * @return array<mixed>|false
     */
    public static function withPayerById(mixed $type, mixed $pid, mixed $id): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->withPayerById($type, $pid, $id);
    }

    /**
     * The columns named (a select list over insd) of a policy by id; sqlQuery()'s answer, false when there is
     * no such policy (the custom XML and LabWorks exports).
     *
     * @return array<mixed>|false
     */
    public static function columnsById(mixed $id, string $columns): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->columnsById($id, $columns);
    }

    /**
     * Each policy with a company id set, by level then latest start, with the subscriber, the company's name,
     * its address and work phone, and the provider's number with it: one row per address, phone and number on
     * file (the custom billing CSV export keeps the first row of each level).
     *
     * @return list<array<mixed>>
     */
    public static function withPayerForBillingExport(mixed $pid, mixed $providerId): array
    {
        return ServiceContainer::getInsuranceCoverage()->withPayerForBillingExport($pid, $providerId);
    }

    /**
     * A policy row by its uuid (bytes), every column (FHIR Coverage's update).
     *
     * @return array<mixed>|false
     */
    public static function byUuid(string $uuidBytes): array|false
    {
        return ServiceContainer::getInsuranceCoverage()->byUuid($uuidBytes);
    }

    /**
     * Many patients' policies at a level, each with its company joined as ic,
     * by patient, then start (none first), then id; a query per 1000 patients.
     *
     * @param array<mixed> $pids
     * @return list<array<mixed>>
     */
    public static function forPatientsAtLevel(array $pids, mixed $type, string $columns = 'insd.*, ic.name AS company_name'): array
    {
        return ServiceContainer::getInsuranceCoverage()->forPatientsAtLevel($pids, $type, $columns);
    }

    /**
     * The patients with a policy naming a company, matched with LIKE, oldest
     * row first (prior authorizations' TriWest list).
     *
     * @return list<mixed>
     */
    public static function patientsWithPayerLike(string $provider): array
    {
        return ServiceContainer::getInsuranceCoverage()->patientsWithPayerLike($provider);
    }

    /**
     * Every primary whose company exists, with the company's insurance type
     * code, for the patients given (escaped into the statement as the QRDA
     * functions always did).
     *
     * @param array<mixed> $pids
     * @return list<array<mixed>>
     */
    public static function primaryPayerTypes(array $pids): array
    {
        return ServiceContainer::getInsuranceCoverage()->primaryPayerTypes($pids);
    }

    /**
     * Every primary whose company exists, with the company's insurance type
     * code, for one patient (QRDA).
     *
     * @return list<array<mixed>>
     */
    public static function primaryPayerTypesForPatient(mixed $pid): array
    {
        return ServiceContainer::getInsuranceCoverage()->primaryPayerTypesForPatient($pid);
    }
}
