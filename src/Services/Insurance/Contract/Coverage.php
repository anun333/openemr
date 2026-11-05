<?php

/**
 * Coverage: every question core asks about a patient's insurance policies (insurance_data).
 *
 * The insurance module implements it, and core resolves it through
 * OpenEMR\BC\ServiceContainer::getInsuranceCoverage() without naming the module's classes. Each
 * method is the query a caller ran before the boundary branch, moved unchanged; the sections
 * follow the module's classes, from which this file is generated (signatures and docblocks).
 * No permission checks: each caller keeps its own.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\Insurance\Contract;

use DateTimeImmutable;
use OpenEMR\Services\Insurance\{
    CoverageSegment,
    EffectiveInsurance,
    EffectiveInsuranceRow,
    InsuranceLevel,
};

interface Coverage
{
    // ---- as PolicyRecords (the insurance module) ---------------------------------------------------------

    /**
     * The latest-starting policy at a level, ended or not, with its company
     * joined as ic (the procedural getInsuranceData()).
     *
     * @return array<mixed>|false
     */
    public function latestAtLevel(mixed $pid, mixed $type, string $columns = 'insd.*, ic.name as provider_name'): array|false;

    /**
     * The latest-starting policy's id at a level (the custom XML and LabWorks
     * exports).
     *
     * @return array<mixed>|false
     */
    public function latestIdAtLevel(mixed $pid, mixed $type): array|false;

    /**
     * The latest-starting secondary whose company exists, with the company's
     * name and id (the id key is the company's): the paper requisitions.
     *
     * @return array<mixed>|false
     */
    public function latestSecondaryWithPayer(mixed $pid): array|false;

    /**
     * Every policy at a level, latest start first, with its company joined as
     * ic (the procedural getInsuranceDataNew()).
     *
     * @return list<array<mixed>>
     */
    public function historyAtLevel(mixed $pid, mixed $type, string $columns = 'insd.*, ic.name as provider_name'): array;

    /**
     * Every policy at a level, earliest start first (the patient report's
     * insurance section).
     *
     * @return list<array<mixed>>
     */
    public function historyOldestFirst(mixed $pid, mixed $type): array;

    /**
     * Every policy at a level by start, with every company column and the
     * company's name as provider_name (the patient report's change history).
     *
     * @return list<array<mixed>>
     */
    public function historyWithPayerName(mixed $pid, mixed $type): array;

    /**
     * The policy at a level that started most recently on or before a day and
     * hasn't ended before it, with its company joined as ic (the procedural
     * getInsuranceDataByDate() and getInsuranceNameByDate()). The day is
     * YYYY-MM-DD.
     *
     * @return array<mixed>|false
     */
    public function onDateAtLevel(mixed $pid, mixed $date, mixed $type, string $columns): array|false;

    /**
     * The primary in force on a day by the billing rule (start on or before,
     * end on or after or none), latest start first: its company and copay
     * (BillingUtilities::getCopay()).
     *
     * @return array<mixed>|false
     */
    public function primaryCopayOn(mixed $pid, mixed $date): array|false;

    /**
     * The level and company name of a patient's policy with a given company in
     * force on a day (the Billing Manager's "queued for ... billing to").
     *
     * @return array<mixed>|false
     */
    public function levelAndPayerNameOn(mixed $pid, mixed $provider, mixed $date): array|false;

    /**
     * Every policy, by level, latest start first (the old Copay screen keeps
     * the first of each level).
     *
     * @return list<array<mixed>>
     */
    public function allByLevelLatestFirst(mixed $pid): array;

    /**
     * Each policy with a company and an address on file, with the company's
     * name and the address (the procedural getAllinsurances(): the lab
     * requisitions).
     *
     * @return list<array<mixed>>
     */
    public function requisitionRows(mixed $pid): array;

    /**
     * The first policy row's subscriber relationship, in the database's order
     * (the lab's self-pay check, getSelfPay()).
     */
    public function firstSubscriberRelationship(mixed $pid): mixed;

    /**
     * The policy a lab's GT1 guarantor names: the first level's latest policy
     * that started on or before a day, ended or not (LabCorp and DORN).
     *
     * @return list<array<mixed>>
     */
    public function guarantorRowOn(mixed $pid, mixed $date): array;

    /**
     * Whether a primary names a subscriber: a row with the count (the Billing
     * Manager colours a patient without one).
     *
     * @return array<mixed>|false
     */
    public function countPrimaryWithSubscriber(mixed $pid): array|false;

    /**
     * How many levels have ever had a company on file (checkout and the portal
     * compare it with the visit's last level closed).
     */
    public function countLevelsWithPayer(mixed $pid): mixed;

    /**
     * Each policy whose company exists, as level and company name, in the
     * database's order (the invoice summary keeps the last of each level).
     *
     * @return list<array<mixed>>
     */
    public function levelsAndPayerNames(mixed $pid): array;

    /**
     * The first primary row's id and copay, in the database's order (the 271
     * response saves its verification against it).
     *
     * @return array<mixed>|false
     */
    public function primaryIdAndCopay(mixed $pid): array|false;

    /**
     * The latest eligibility verification of each policy, with its benefits
     * and the company's name (the eligibility panel).
     *
     * @return list<array<mixed>>
     */
    public function eligibilityResults(mixed $pid): array;

    /**
     * The company names of the patient's own policies (subscriber self, a
     * company set), one per level as the database groups them: the eRx
     * health plans. The statement's result, as sqlStatement() returns it.
     */
    public function selfPlanNamesStatement(mixed $pid): \ADORecordSet;

    /**
     * The first primary's subscriber as a responsible party, when the patient
     * isn't the subscriber (Weno prescribing for a minor).
     *
     * @return array<mixed>|false
     */
    public function primarySubscriberAsResponsibleParty(mixed $pid): array|false;

    /**
     * The first primary row's subscriber relationship (Weno).
     *
     * @return array<mixed>|false
     */
    public function primaryRelationshipRow(mixed $pid): array|false;

    /**
     * The first primary's company name, of primaries whose company exists
     * (prior authorizations).
     *
     * @return array<mixed>|false
     */
    public function primaryPayerNameRow(mixed $pid): array|false;

    /**
     * A policy by id, at a level and for a patient, whose company exists: every
     * policy column, the company's name as company_name and its uuid as
     * compuuid (the C-CDA payer section).
     *
     * @return array<mixed>|false
     */
    public function withPayerById(mixed $type, mixed $pid, mixed $id): array|false;

    /**
     * The columns named (a select list over insd) of a policy by id; sqlQuery()'s answer, false when there is
     * no such policy (the custom XML and LabWorks exports).
     *
     * @return array<mixed>|false
     */
    public function columnsById(mixed $id, string $columns): array|false;

    /**
     * Each policy with a company id set, by level then latest start, with the subscriber, the company's name,
     * its address and work phone, and the provider's number with it: one row per address, phone and number on
     * file (the custom billing CSV export keeps the first row of each level).
     *
     * @return list<array<mixed>>
     */
    public function withPayerForBillingExport(mixed $pid, mixed $providerId): array;

    /**
     * A policy row by its uuid (bytes), every column (FHIR Coverage's update).
     *
     * @return array<mixed>|false
     */
    public function byUuid(string $uuidBytes): array|false;

    /**
     * Many patients' policies at a level, each with its company joined as ic,
     * by patient, then start (none first), then id; a query per 1000 patients.
     *
     * @param array<mixed> $pids
     * @return list<array<mixed>>
     */
    public function forPatientsAtLevel(array $pids, mixed $type, string $columns = 'insd.*, ic.name AS company_name'): array;

    /**
     * The patients with a policy naming a company, matched with LIKE, oldest
     * row first (prior authorizations' TriWest list).
     *
     * @return list<mixed>
     */
    public function patientsWithPayerLike(string $provider): array;

    /**
     * Every primary whose company exists, with the company's insurance type
     * code, for the patients given (escaped into the statement as the QRDA
     * functions always did).
     *
     * @param array<mixed> $pids
     * @return list<array<mixed>>
     */
    public function primaryPayerTypes(array $pids): array;

    /**
     * Every primary whose company exists, with the company's insurance type
     * code, for one patient (QRDA).
     *
     * @return list<array<mixed>>
     */
    public function primaryPayerTypesForPatient(mixed $pid): array;

    // ---- as PolicyChecks (the insurance module) ----------------------------------------------------------

    /**
     * The latest-starting date at a level (the swap validator).
     */
    public function latestStartAtLevel(mixed $pid, mixed $type): mixed;

    /**
     * A policy's start date and level by its uuid (bytes): the swap validator.
     *
     * @return list<array<mixed>>
     */
    public function startAndLevelByUuid(string $uuidBytes): array;

    /**
     * Policies at a level starting on a day, leaving out the one with the
     * given uuid (bytes) when there is one.
     */
    public function countStartingAt(mixed $pid, mixed $type, mixed $date, ?string $exceptUuidBytes): mixed;

    /**
     * Policies at a level with no end date, leaving out the one with the given
     * uuid (bytes) when there is one.
     */
    public function countOpenAtLevel(mixed $pid, mixed $type, ?string $exceptUuidBytes): mixed;

    /**
     * Dated policies at a level starting on a day (the swap validator).
     */
    public function countDatedStartingAt(mixed $pid, mixed $type, mixed $date): mixed;

    // ---- as PolicySql (the insurance module) -------------------------------------------------------------

    /**
     * The patient has a primary policy on file (any dates, any company): what an inner join on the primary
     * required.
     */
    public function sqlPrimaryOnFile(string $patient): string;

    /**
     * The Billing Manager's "insurance" criterion. With a payer: some primary names a company and started on
     * or before the visit (or has no start). Without one: some primary has company 0 or starts after the
     * visit. A patient with no primary matches neither, as with the outer join and WHERE it replaces.
     */
    public function sqlPrimaryPayerOnVisit(bool $withPayer, string $patient, string $visitDate): string;

    /**
     * A relation of the primaries with no company (provider ''), one row per policy with its pid, for a
     * caller's FROM list (the Indigent Patients report, which lists a visit once per such policy).
     */
    public function sqlPrimariesWithoutPayer(string $alias): string;

    /**
     * A relation of every policy's patient, level and company id, with the company's name (name, null when
     * the company is missing), for a caller's outer join (Patient List Creation's insurers, the prior
     * authorizations list).
     */
    public function sqlPoliciesWithPayerName(string $alias): string;

    // ---- as PolicySave (the insurance module) ------------------------------------------------------------

    /**
     * A new policy row (the procedural newInsuranceData()): every column of
     * INSERT_COLUMNS, valued as the caller has already normalized them. The
     * new row's id.
     *
     * @param array<string, mixed> $values keyed by INSERT_COLUMNS
     */
    public function insert(array $values): int;

    /**
     * Update a policy row's columns by id (the procedural
     * updateInsuranceData()): keys that aren't columns are dropped.
     *
     * @param iterable<mixed, mixed> $new
     */
    public function update(mixed $id, iterable $new): void;

    /**
     * The patient merge's move: the source chart's policies move to the target
     * where the target has none at that level and start; the rest are deleted.
     */
    public function moveForMerge(int $sourcePid, int $targetPid): void;

    // ---- as CoverageReads (the insurance module) ---------------------------------------------------------

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
    public function getEffectiveInsurances(int $pid, DateTimeImmutable $day): array;

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
    public function getEffectiveInsuranceRows(int $pid, DateTimeImmutable $day): array;

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
    public function getRuleRowsByPid(array $pids): array;

    /**
     * The runs of days within a range on which each policy heads a level
     * (EffectiveInsuranceRule::segments()), oldest first: for readers that
     * report over a period, such as eCQM's payer over the measurement period.
     *
     * @return list<CoverageSegment>
     */
    public function getCoverageSegments(int $pid, InsuranceLevel $level, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * The policy in force at one level on a day, or null when the level has
     * none, as getEffectiveInsurances() chooses it.
     */
    public function getEffectiveInsuranceAt(int $pid, InsuranceLevel $level, DateTimeImmutable $day): ?EffectiveInsurance;

    // ---- as PolicyStatements (the insurance module) ------------------------------------------------------

    /**
     * The row at a level, the first the database returns (InsuranceService::getOneByPid()).
     *
     * @return array<mixed>|false
     */
    public function rowByPidAndType(mixed $id, mixed $type): array|false;

    /**
     * InsuranceService::search()'s statement: every policy with its company's uuid, type and Source of Payment
     * and its patient's uuid, under the search's WHERE fragment, in force today first.
     *
     * @param array<mixed> $sqlBindArray the fragment's bound values
     */
    public function searchStatement(string $whereFragment, array $sqlBindArray): \ADORecordSet;

    /**
     * A policy's row by its uuid (bytes), uuid binary (InsuranceService::getOne()).
     *
     * @return array<mixed>|false
     */
    public function rowByUuid(string $uuidBytes): array|false;

    /**
     * The id of the row at a level the database returns first, or null (InsuranceService::doesInsuranceTypeHaveEntry()).
     */
    public function idAtLevel(mixed $pid, mixed $type): mixed;

    /**
     * Whether the patient has any policy row (InsuranceService::doesInsuranceTypeHaveEntry()).
     */
    public function hasPolicy(mixed $pid): bool;

    /**
     * The patient a policy (by uuid, bytes) belongs to: the update's ownership check.
     */
    public function pidByUuid(string $uuidBinaryCheck): mixed;

    /**
     * InsuranceService's update statement, every column the policy editor and the API write, keyed by uuid.
     * True once the statement has run; a failing statement throws. (ADOdb answers an UPDATE with its empty
     * recordset, which isn't an ADORecordSet; the callers only ever tested the answer for truth.)
     *
     * @param array<mixed> $binds the columns' values in the statement's order, then the uuid (bytes)
     */
    public function updateByUuid(array $binds): true;

    /**
     * InsuranceService's insert statement (the API and FHIR create). The new row's id.
     *
     * @param array<mixed> $binds the columns' values in the statement's order
     */
    public function insertPolicy(array $binds): int;

    /**
     * InsuranceService::insertImported()'s statement (a document import's payer). The new row's id.
     *
     * @param array<mixed> $binds uuid (bytes), pid, type, provider, date, date_end
     */
    public function insertImportedPolicy(array $binds): int;

    /**
     * The latest-starting policy's uuid (bytes) at a level, dated ones first: the swap's target.
     */
    public function latestUuidAtLevel(mixed $pid, string $targetType): mixed;
}
