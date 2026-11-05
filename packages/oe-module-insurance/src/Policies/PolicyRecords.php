<?php

/**
 * Policies on file: the stored insurance_data rows a caller asks about by
 * patient, by level, by id or uuid, or for many patients at once, as they are
 * on file (ended, current or not yet begun). Which policy is in force on a day
 * is InsuranceService::getEffectiveInsurances() and the reads beside it; this
 * class answers the other questions today's screens ask. The validator's
 * checks are PolicyChecks; SQL other queries embed is PolicySql.
 *
 * Each read is the query its callers ran before the boundary branch, moved
 * here unchanged (the same statement, the same binds and the same database
 * function), so it answers exactly as it did, ties and engine order included.
 * Where several callers asked the same question with the same query, they
 * share one method. A $columns argument is a SQL select list over the aliases
 * the method names (insd for the policy, ic for its company); callers pass
 * literals only, as the procedural functions always required.
 *
 * No permission checks: each caller keeps its own. Static, so a read runs none
 * of InsuranceService's setup (its constructor backfills missing uuids).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Insurance\Policies;

use OpenEMR\Common\Database\QueryUtils;

final class PolicyRecords
{
    private const DEFAULT_COLUMNS = 'insd.*, ic.name as provider_name';

    private function __construct()
    {
    }

    // ---- by patient and level ------------------------------------------------------------------

    /**
     * The latest-starting policy at a level, ended or not, with its company
     * joined as ic (the procedural getInsuranceData()).
     *
     * @return array<mixed>|false
     */
    public static function latestAtLevel(mixed $pid, mixed $type, string $columns = self::DEFAULT_COLUMNS): array|false
    {
        $sql = "select $columns from insurance_data as insd " .
            "left join insurance_companies as ic on ic.id = insd.provider " .
            "where pid = ? and type = ? order by date DESC limit 1";
        return QueryUtils::querySingleRow($sql, [$pid, $type]);
    }

    /**
     * The latest-starting policy's id at a level (the custom XML and LabWorks
     * exports).
     *
     * @return array<mixed>|false
     */
    public static function latestIdAtLevel(mixed $pid, mixed $type): array|false
    {
        return QueryUtils::querySingleRow("SELECT id FROM insurance_data WHERE " .
            "pid = ? AND type = ? ORDER BY date DESC LIMIT 1", [$pid, $type]);
    }

    /**
     * The latest-starting secondary whose company exists, with the company's
     * name and id (the id key is the company's): the paper requisitions.
     *
     * @return array<mixed>|false
     */
    public static function latestSecondaryWithPayer(mixed $pid): array|false
    {
        return QueryUtils::querySingleRow("SELECT i.*, ic.name, ic.id FROM insurance_data i join insurance_companies ic ON i.provider=ic.id WHERE i.pid=? and i.type='secondary' ORDER BY i.date DESC LIMIT 1", [$pid]);
    }

    /**
     * Every policy at a level, latest start first, with its company joined as
     * ic (the procedural getInsuranceDataNew()).
     *
     * @return list<array<mixed>>
     */
    public static function historyAtLevel(mixed $pid, mixed $type, string $columns = self::DEFAULT_COLUMNS): array
    {
        $sql = "select $columns from insurance_data as insd " .
            "left join insurance_companies as ic on ic.id = insd.provider " .
            "where pid = ? and type = ? order by date DESC";
        return QueryUtils::fetchRecords($sql, [$pid, $type]);
    }

    /**
     * Every policy at a level, earliest start first (the patient report's
     * insurance section).
     *
     * @return list<array<mixed>>
     */
    public static function historyOldestFirst(mixed $pid, mixed $type): array
    {
        $sql = "select * from insurance_data where pid=? and type=? order by date ASC";
        return QueryUtils::fetchRecords($sql, [$pid, $type]);
    }

    /**
     * Every policy at a level by start, with every company column and the
     * company's name as provider_name (the patient report's change history).
     *
     * @return list<array<mixed>>
     */
    public static function historyWithPayerName(mixed $pid, mixed $type): array
    {
        return QueryUtils::fetchRecords("select *, ic.name as provider_name from insurance_data left join insurance_companies as ic on ic.id = provider where pid=? and type=? order by date", [$pid, $type]);
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
        $sql = "select $columns from insurance_data as insd " .
            "left join insurance_companies as ic on ic.id = provider " .
            "where pid = ? and (date_format(date,'%Y-%m-%d') <= ? OR date IS NULL) and " .
            "(date_format(date_end,'%Y-%m-%d') >= ? OR date_end IS NULL) and " .
            "type = ? order by date DESC limit 1";
        return QueryUtils::querySingleRow($sql, [$pid, $date, $date, $type]);
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
        return QueryUtils::querySingleRow("SELECT provider, copay FROM insurance_data " .
            "WHERE pid = ? AND type = 'primary' " .
            "AND (date <= ? OR date IS NULL) AND (date_end >= ? OR date_end IS NULL) ORDER BY date DESC LIMIT 1", [$pid, $date, $date]);
    }

    /**
     * The level and company name of a patient's policy with a given company in
     * force on a day (the Billing Manager's "queued for ... billing to").
     *
     * @return array<mixed>|false
     */
    public static function levelAndPayerNameOn(mixed $pid, mixed $provider, mixed $date): array|false
    {
        $query = "SELECT id.type, ic.name " .
            "FROM insurance_data AS id, insurance_companies AS ic WHERE " .
            "id.pid = ? AND " .
            "id.provider = ? AND " .
            "(id.date <= ? OR id.date IS NULL) AND " .
            "(id.date_end >= ? OR id.date_end IS NULL) AND " .
            "ic.id = id.provider " .
            "ORDER BY id.type ASC, id.date DESC";
        return QueryUtils::querySingleRow($query, [$pid, $provider, $date, $date]);
    }

    // ---- by patient ------------------------------------------------------------------------------

    /**
     * Every policy, by level, latest start first (the old Copay screen keeps
     * the first of each level).
     *
     * @return list<array<mixed>>
     */
    public static function allByLevelLatestFirst(mixed $pid): array
    {
        return QueryUtils::fetchRecords("SELECT * FROM insurance_data WHERE pid = ? " .
            "ORDER BY type ASC, date DESC", [$pid]);
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
        $sql = "SELECT a.type, a.provider, a.plan_name, a.policy_number, a.group_number,
           a.subscriber_lname, a.subscriber_fname, a.subscriber_relationship, a.subscriber_employer,
		   b.name, c.line1, c.line2, c.city, c.state, c.zip
           FROM `insurance_data` AS a
           RIGHT JOIN insurance_companies AS b
           ON a.provider = b.id
           RIGHT JOIN addresses AS c
           ON a.provider = c.foreign_id
           WHERE a.pid = ? ";
        return QueryUtils::fetchRecords($sql, [$pid]);
    }

    /**
     * The first policy row's subscriber relationship, in the database's order
     * (the lab's self-pay check, getSelfPay()).
     */
    public static function firstSubscriberRelationship(mixed $pid): mixed
    {
        $res = QueryUtils::querySingleRow("SELECT `subscriber_relationship` FROM `insurance_data` WHERE pid = ?", [$pid]);
        return is_array($res) ? $res['subscriber_relationship'] : null;
    }

    /**
     * The policy a lab's GT1 guarantor names: the first level's latest policy
     * that started on or before a day, ended or not (LabCorp and DORN).
     *
     * @return list<array<mixed>>
     */
    public static function guarantorRowOn(mixed $pid, mixed $date): array
    {
        return QueryUtils::fetchRecords("SELECT * FROM insurance_data WHERE " .
            "pid = ? AND date <= ? ORDER BY type ASC, date DESC LIMIT 1", [$pid, $date]);
    }

    /**
     * Whether a primary names a subscriber: a row with the count (the Billing
     * Manager colours a patient without one).
     *
     * @return array<mixed>|false
     */
    public static function countPrimaryWithSubscriber(mixed $pid): array|false
    {
        return QueryUtils::querySingleRow(
            "SELECT count(*) AS count FROM insurance_data WHERE " .
            "pid = ? AND " .
            "type='primary' AND " .
            "subscriber_lname IS NOT NULL AND " .
            "subscriber_lname != '' LIMIT 1",
            [$pid]
        );
    }

    /**
     * How many levels have ever had a company on file (checkout and the portal
     * compare it with the visit's last level closed).
     */
    public static function countLevelsWithPayer(mixed $pid): mixed
    {
        return QueryUtils::fetchSingleValue("SELECT COUNT( DISTINCT TYPE ) NumberOfInsurance FROM `insurance_data`
                                    where pid = ? and provider>0 ", 'NumberOfInsurance', [$pid]);
    }

    /**
     * Each policy whose company exists, as level and company name, in the
     * database's order (the invoice summary keeps the last of each level).
     *
     * @return list<array<mixed>>
     */
    public static function levelsAndPayerNames(mixed $pid): array
    {
        return QueryUtils::fetchRecords("SELECT insurance_data.type as type, insurance_companies.name as name " .
            "FROM insurance_data " .
            "INNER JOIN insurance_companies ON insurance_data.provider = insurance_companies.id " .
            "WHERE insurance_data.pid = ?", [$pid]);
    }

    /**
     * The first primary row's id and copay, in the database's order (the 271
     * response saves its verification against it).
     *
     * @return array<mixed>|false
     */
    public static function primaryIdAndCopay(mixed $pid): array|false
    {
        return QueryUtils::querySingleRow("SELECT id, copay FROM insurance_data WHERE type = 'primary' and pid = ?", [$pid]);
    }

    /**
     * The latest eligibility verification of each policy, with its benefits
     * and the company's name (the eligibility panel).
     *
     * @return list<array<mixed>>
     */
    public static function eligibilityResults(mixed $pid): array
    {
        $query =
            "SELECT eligr.*, eligv.insurance_id, eligv.copay, insd.pid, insc.name, " .
            "Date_Format(eligv.eligibility_check_date, '%W %M %e, %Y %h:%i %p') AS verificationDate " .
            "FROM eligibility_verification eligv " .
            "INNER JOIN benefit_eligibility eligr ON eligr.verification_id = eligv.verification_id " .
            "INNER JOIN insurance_data insd ON insd.id = eligv.insurance_id " .
            "INNER JOIN insurance_companies insc ON insc.id = insd.provider " .
            "WHERE insd.pid = ? AND eligv.eligibility_check_date = " .
            "(SELECT Max(eligibility_verification.eligibility_check_date) FROM eligibility_verification " .
            "WHERE eligibility_verification.insurance_id = eligv.insurance_id)";
        return QueryUtils::fetchRecords($query, [$pid]);
    }

    /**
     * The company names of the patient's own policies (subscriber self, a
     * company set), one per level as the database groups them: the eRx
     * health plans. The statement's result, as sqlStatement() returns it.
     */
    public static function selfPlanNamesStatement(mixed $pid): \ADORecordSet
    {
        return QueryUtils::sqlStatementThrowException(
            'SELECT `ins`.`name`
			FROM (
				SELECT
					`id`.`type`,
					`ic`.`name`
				FROM `insurance_data` AS `id`
					LEFT JOIN `insurance_companies` AS `ic` ON `ic`.`id` = `id`.`provider`
				WHERE `id`.`pid` = ?
					AND `id`.`subscriber_relationship` = \'self\'
					AND `id`.`provider` > 0
				ORDER BY `id`.`date` DESC
			) AS `ins`
			GROUP BY `ins`.`type`;',
            [$pid]
        );
    }

    /**
     * The first primary's subscriber as a responsible party, when the patient
     * isn't the subscriber (Weno prescribing for a minor).
     *
     * @return array<mixed>|false
     */
    public static function primarySubscriberAsResponsibleParty(mixed $pid): array|false
    {
        $insurance = <<<SQL
        select subscriber_lname AS ResponsiblePartyLastName,
               subscriber_fname AS ResponsiblePartyFirstName,
               subscriber_street AS ResponsiblePartyAddressLine1,
               subscriber_postal_code AS ResponsiblePartyPostalCode,
               subscriber_city AS ResponsiblePartyCity,
               subscriber_state AS ResponsiblePartyState,
               subscriber_phone AS ResponsiblePartyPrimaryPhone,
               subscriber_street_line_2 AS ResponsiblePartyAddressLine2
          FROM insurance_data
         WHERE pid = ?
           AND subscriber_relationship > ''
           AND subscriber_relationship != 'self'
           AND type = 'primary'
        SQL;
        return QueryUtils::querySingleRow($insurance, [$pid]);
    }

    /**
     * The first primary row's subscriber relationship (Weno).
     *
     * @return array<mixed>|false
     */
    public static function primaryRelationshipRow(mixed $pid): array|false
    {
        return QueryUtils::querySingleRow("select subscriber_relationship from insurance_data where pid = ? and type = 'primary'", [$pid]);
    }

    /**
     * The first primary's company name, of primaries whose company exists
     * (prior authorizations).
     *
     * @return array<mixed>|false
     */
    public static function primaryPayerNameRow(mixed $pid): array|false
    {
        return QueryUtils::querySingleRow("SELECT ic.name FROM `insurance_data` id
            JOIN insurance_companies ic ON id.provider = ic.id
            WHERE `pid` = ? AND type = 'primary'", [$pid]);
    }

    // ---- by id and uuid --------------------------------------------------------------------------

    /**
     * A policy by id, at a level and for a patient, whose company exists: every
     * policy column, the company's name as company_name and its uuid as
     * compuuid (the C-CDA payer section).
     *
     * @return array<mixed>|false
     */
    public static function withPayerById(mixed $type, mixed $pid, mixed $id): array|false
    {
        return QueryUtils::querySingleRow(
            "SELECT i.*, c.name AS company_name, c.uuid as compuuid FROM insurance_data i " .
            "JOIN insurance_companies c ON i.provider = c.id WHERE i.type = ? AND i.pid = ? AND i.id = ?",
            [$type, $pid, $id]
        );
    }

    /**
     * The columns named (a select list over insd) of a policy by id; sqlQuery()'s answer, false when there is
     * no such policy (the custom XML and LabWorks exports).
     *
     * @return array<mixed>|false
     */
    public static function columnsById(mixed $id, string $columns): array|false
    {
        return QueryUtils::querySingleRow("SELECT $columns FROM insurance_data AS insd WHERE insd.id = ?", [$id]);
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
        $query = "SELECT " .
          "d.type, d.policy_number, d.group_number, " .
          "d.subscriber_lname, d.subscriber_fname, d.subscriber_mname, " .
          "d.subscriber_street, d.subscriber_city, d.subscriber_state, " .
          "d.subscriber_postal_code, d.subscriber_DOB, d.subscriber_sex, " .
          "d.subscriber_relationship, " .
          "c.name, " .
          "a.line1, a.line2, a.city, a.state, a.zip, " .
          "p.area_code, p.prefix, p.number, " .
          "n.provider_number " .
          "FROM insurance_data AS d " .
          "LEFT OUTER JOIN insurance_companies AS c ON c.id = d.provider " .
          "LEFT OUTER JOIN addresses AS a ON a.foreign_id = c.id " .
          "LEFT OUTER JOIN phone_numbers AS p ON p.foreign_id = c.id AND p.type = 2 " .
          "LEFT OUTER JOIN insurance_numbers AS n ON n.provider_id = ? " .
          "AND n.insurance_company_id = c.id " .
          "WHERE d.pid = ? AND d.provider != '' " .
          "ORDER BY d.type ASC, d.date DESC";
        return QueryUtils::fetchRecords($query, [$providerId, $pid]);
    }

    /**
     * A policy row by its uuid (bytes), every column (FHIR Coverage's update).
     *
     * @return array<mixed>|false
     */
    public static function byUuid(string $uuidBytes): array|false
    {
        return QueryUtils::querySingleRow("SELECT * FROM insurance_data WHERE uuid = ?", [$uuidBytes]);
    }

    // ---- many patients ---------------------------------------------------------------------------

    /**
     * Many patients' policies at a level, each with its company joined as ic,
     * by patient, then start (none first), then id; a query per 1000 patients.
     *
     * @param array<mixed> $pids
     * @return list<array<mixed>>
     */
    public static function forPatientsAtLevel(array $pids, mixed $type, string $columns = 'insd.*, ic.name AS company_name'): array
    {
        $pids = array_values(array_unique(array_filter($pids, fn($pid): bool => is_int($pid) || (is_string($pid) && $pid !== ''))));
        $rows = [];
        foreach (array_chunk($pids, 1000) as $chunk) {
            $sql = "SELECT $columns FROM insurance_data AS insd " .
                "LEFT JOIN insurance_companies AS ic ON ic.id = insd.provider " .
                "WHERE insd.type = ? AND insd.pid IN (" . implode(',', array_fill(0, count($chunk), '?')) . ") " .
                "ORDER BY insd.pid, insd.date, insd.id";
            foreach (QueryUtils::fetchRecords($sql, [$type, ...$chunk]) as $row) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * The patients with a policy naming a company, matched with LIKE, oldest
     * row first (prior authorizations' TriWest list).
     *
     * @return list<mixed>
     */
    public static function patientsWithPayerLike(string $provider): array
    {
        return QueryUtils::fetchTableColumn("SELECT `pid`  FROM `insurance_data` WHERE `provider` LIKE ? ORDER BY `id` ASC", 'pid', [$provider]);
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
        $insQry = "SELECT insd.*, ic.ins_type_code FROM (SELECT pid, provider FROM insurance_data WHERE type = 'primary' ORDER BY id DESC) insd " .
            "INNER JOIN  insurance_companies ic ON insd.provider = ic.id " .
            "WHERE insd.pid IN (" . add_escape_custom(implode(",", array_map(fn($pid): string => is_scalar($pid) ? (string)$pid : '', $pids))) . ")";
        return QueryUtils::fetchRecords($insQry);
    }

    /**
     * Every primary whose company exists, with the company's insurance type
     * code, for one patient (QRDA).
     *
     * @return list<array<mixed>>
     */
    public static function primaryPayerTypesForPatient(mixed $pid): array
    {
        $insQry = "SELECT insd.*, ic.ins_type_code FROM (SELECT pid, provider FROM insurance_data WHERE type = 'primary' ORDER BY id DESC) insd " .
            "INNER JOIN  insurance_companies ic ON insd.provider = ic.id " .
            "WHERE insd.pid = ?";
        return QueryUtils::fetchRecords($insQry, [$pid]);
    }

}
