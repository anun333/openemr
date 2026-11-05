<?php

/**
 * InsuranceService's statements over insurance_data, moved here verbatim; InsuranceService keeps its
 * validation, events and results and runs each through the coverage contract. The procedural
 * sqlQuery(), sqlStatement() and sqlInsert() it called are the QueryUtils calls used here, which
 * differ only when the database fails (an exception where they printed an error and stopped).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Matthew Vita <matthewvita48@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2018 Matthew Vita <matthewvita48@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2024-2025 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\Insurance\Policies;

use OpenEMR\Common\Database\QueryUtils;

final class PolicyStatements
{
    private function __construct()
    {
    }

    /**
     * The row at a level, the first the database returns (InsuranceService::getOneByPid()).
     *
     * @return array<mixed>|false
     */
    public static function rowByPidAndType(mixed $id, mixed $type): array|false
    {
        $sql = "SELECT * FROM insurance_data WHERE pid=? AND type=?";
        return QueryUtils::querySingleRow($sql, [$id, $type]);
    }

    /**
     * InsuranceService::search()'s statement: every policy with its company's uuid, type and Source of Payment
     * and its patient's uuid, under the search's WHERE fragment, in force today first.
     *
     * @param array<mixed> $sqlBindArray the fragment's bound values
     */
    public static function searchStatement(string $whereFragment, array $sqlBindArray): \ADORecordSet
    {
        $sql = "SELECT `insurance_data`.*,
                       `puuid`,
                       `insureruuid`,
                       `ins_type_code`,
                       `cqm_sop`
                FROM `insurance_data`
                LEFT JOIN (
                    SELECT
                    `uuid` AS `insureruuid`,
                    `id` AS `insurerid`,
                    `ins_type_code`,
                    `cqm_sop`
                    FROM `insurance_companies`
                    ) `insurance_company_data` ON `insurance_data`.`provider` = `insurance_company_data`.`insurerid`
                LEFT JOIN (
                    SELECT
                    `pid` AS `patient_data_pid`,
                    `uuid` AS `puuid`
                    FROM `patient_data`
                ) `patient_data` ON `insurance_data`.`pid` = `patient_data`.`patient_data_pid` ";


        // Twin (insurance-reference): the policy in force today first, as the coverage
        // service decides it (both ends inclusive, a blank or zero end open), then the
        // latest start. Master put any open or future end first, so next year's card
        // led the list the policy editor and the demographics card open on.
        $orderClause = " ORDER BY `patient_data_pid` ASC,`type` ASC"
        . ", ((`date` IS NULL OR `date` <= CURDATE()) AND (`date_end` IS NULL OR `date_end` = '0000-00-00' OR `date_end` >= CURDATE())) DESC"
        . ", `date` DESC, `date_end` DESC, `policy_number` ASC";

        $sql .= $whereFragment . $orderClause;
        return QueryUtils::sqlStatementThrowException($sql, $sqlBindArray);
    }

    /**
     * A policy's row by its uuid (bytes), uuid binary (InsuranceService::getOne()).
     *
     * @return array<mixed>|false
     */
    public static function rowByUuid(string $uuidBytes): array|false
    {
        $sql = "SELECT * FROM insurance_data WHERE uuid=? ";

        return QueryUtils::querySingleRow($sql, [$uuidBytes]);
    }

    /**
     * The id of the row at a level the database returns first, or null (InsuranceService::doesInsuranceTypeHaveEntry()).
     */
    public static function idAtLevel(mixed $pid, mixed $type): mixed
    {
        $row = QueryUtils::querySingleRow("Select `id` From `insurance_data` Where pid = ? And type = ?", [$pid, $type]);
        return is_array($row) ? ($row['id'] ?? null) : null;
    }

    /**
     * Whether the patient has any policy row (InsuranceService::doesInsuranceTypeHaveEntry()).
     */
    public static function hasPolicy(mixed $pid): bool
    {
        return QueryUtils::fetchSingleValue("SELECT `id` FROM `insurance_data` WHERE `pid` = ? LIMIT 1", 'id', [$pid]) !== null;
    }

    /**
     * The patient a policy (by uuid, bytes) belongs to: the update's ownership check.
     */
    public static function pidByUuid(string $uuidBinaryCheck): mixed
    {
        return QueryUtils::fetchSingleValue(
            "SELECT pid FROM insurance_data WHERE uuid = ?",
            'pid',
            [$uuidBinaryCheck]
        );
    }

    /**
     * InsuranceService's update statement, every column the policy editor and the API write, keyed by uuid.
     * True once the statement has run; a failing statement throws. (ADOdb answers an UPDATE with its empty
     * recordset, which isn't an ADORecordSet; the callers only ever tested the answer for truth.)
     *
     * @param array<mixed> $binds the columns' values in the statement's order, then the uuid (bytes)
     */
    public static function updateByUuid(array $binds): true
    {
        $sql = " UPDATE insurance_data SET ";
        $sql .= "   provider=?,";
        $sql .= "   plan_name=?,";
        $sql .= "   policy_number=?,";
        $sql .= "   group_number=?,";
        $sql .= "   subscriber_lname=?,";
        $sql .= "   subscriber_mname=?,";
        $sql .= "   subscriber_fname=?,";
        $sql .= "   subscriber_relationship=?,";
        $sql .= "   subscriber_ss=?,";
        $sql .= "   subscriber_DOB=?,";
        $sql .= "   subscriber_street=?,";
        $sql .= "   subscriber_postal_code=?,";
        $sql .= "   subscriber_city=?,";
        $sql .= "   subscriber_state=?,";
        $sql .= "   subscriber_country=?,";
        $sql .= "   subscriber_phone=?,";
        $sql .= "   subscriber_employer=?,";
        $sql .= "   subscriber_employer_street=?,";
        $sql .= "   subscriber_employer_postal_code=?,";
        $sql .= "   subscriber_employer_state=?,";
        $sql .= "   subscriber_employer_country=?,";
        $sql .= "   subscriber_employer_city=?,";
        $sql .= "   copay=?,";
        $sql .= "   date=?,";
        $sql .= "   date_end=?,";
        $sql .= "   subscriber_sex=?,";
        $sql .= "   accept_assignment=?,";
        $sql .= "   policy_type=?,";
        $sql .= "   type=?";
        $sql .= "   WHERE uuid = ? ";

        QueryUtils::sqlStatementThrowException($sql, $binds);
        return true;
    }

    /**
     * InsuranceService's insert statement (the API and FHIR create). The new row's id.
     *
     * @param array<mixed> $binds the columns' values in the statement's order
     */
    public static function insertPolicy(array $binds): int
    {
        $sql = " INSERT INTO insurance_data SET ";
        $sql .= "   uuid=?,";
        $sql .= "   type=?,";
        $sql .= "   provider=?,";
        $sql .= "   plan_name=?,";
        $sql .= "   policy_number=?,";
        $sql .= "   group_number=?,";
        $sql .= "   subscriber_lname=?,";
        $sql .= "   subscriber_mname=?,";
        $sql .= "   subscriber_fname=?,";
        $sql .= "   subscriber_relationship=?,";
        $sql .= "   subscriber_ss=?,";
        $sql .= "   subscriber_DOB=?,";
        $sql .= "   subscriber_street=?,";
        $sql .= "   subscriber_postal_code=?,";
        $sql .= "   subscriber_city=?,";
        $sql .= "   subscriber_state=?,";
        $sql .= "   subscriber_country=?,";
        $sql .= "   subscriber_phone=?,";
        $sql .= "   subscriber_employer=?,";
        $sql .= "   subscriber_employer_street=?,";
        $sql .= "   subscriber_employer_postal_code=?,";
        $sql .= "   subscriber_employer_state=?,";
        $sql .= "   subscriber_employer_country=?,";
        $sql .= "   subscriber_employer_city=?,";
        $sql .= "   copay=?,";
        $sql .= "   date=?,";
        $sql .= "   date_end=?,";
        $sql .= "   pid=?,";
        $sql .= "   subscriber_sex=?,";
        $sql .= "   accept_assignment=?,";
        $sql .= "   policy_type=?";

        return QueryUtils::sqlInsert($sql, $binds);
    }

    /**
     * InsuranceService::insertImported()'s statement (a document import's payer). The new row's id.
     *
     * @param array<mixed> $binds uuid (bytes), pid, type, provider, date, date_end
     */
    public static function insertImportedPolicy(array $binds): int
    {
        return QueryUtils::sqlInsert(
            "INSERT INTO `insurance_data` (`uuid`, `pid`, `type`, `provider`, `date`, `date_end`) VALUES (?, ?, ?, ?, ?, ?)",
            $binds
        );
    }

    /**
     * The latest-starting policy's uuid (bytes) at a level, dated ones first: the swap's target.
     */
    public static function latestUuidAtLevel(mixed $pid, string $targetType): mixed
    {
        return QueryUtils::fetchSingleValue("SELECT uuid FROM insurance_data WHERE pid = ? AND type = ? ORDER BY (date IS NULL) ASC, date DESC", 'uuid', [$pid, $targetType]);
    }
}
