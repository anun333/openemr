<?php

/**
 * Saving policies: the writes to insurance_data outside InsuranceService's
 * own insert, update and swap (REST, FHIR and the policy editor). Each is the
 * statement its caller ran before the boundary branch, moved here unchanged,
 * so the rows and the audit log's entries (which carry the statement) are
 * what they were: the procedural newInsuranceData() (the new-patient screen
 * and the imports) and updateInsuranceData() (kept for callers outside the
 * tree: nothing in it calls it), and the patient merge's move.
 *
 * No permission checks: each caller keeps its own. Static, so a write runs
 * none of InsuranceService's setup.
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

final class PolicySave
{
    /** The columns a new policy sets, in the statement's order. */
    public const INSERT_COLUMNS = ['type', 'provider', 'policy_number', 'group_number', 'plan_name', 'subscriber_lname', 'subscriber_mname', 'subscriber_fname', 'subscriber_relationship', 'subscriber_ss', 'subscriber_DOB', 'subscriber_street', 'subscriber_postal_code', 'subscriber_city', 'subscriber_state', 'subscriber_country', 'subscriber_phone', 'subscriber_employer', 'subscriber_employer_city', 'subscriber_employer_street', 'subscriber_employer_postal_code', 'subscriber_employer_state', 'subscriber_employer_country', 'copay', 'subscriber_sex', 'pid', 'date', 'accept_assignment', 'policy_type', 'date_end'];

    private function __construct()
    {
    }

    /**
     * A new policy row (the procedural newInsuranceData()): every column of
     * INSERT_COLUMNS, valued as the caller has already normalized them. The
     * new row's id.
     *
     * @param array<string, mixed> $values keyed by INSERT_COLUMNS
     */
    public static function insert(array $values): int
    {
        $binds = [];
        foreach (self::INSERT_COLUMNS as $column) {
            $binds[] = $values[$column] ?? null;
        }
        return QueryUtils::sqlInsert(
            "INSERT INTO `insurance_data` SET `type` = ?,
        `provider` = ?,
        `policy_number` = ?,
        `group_number` = ?,
        `plan_name` = ?,
        `subscriber_lname` = ?,
        `subscriber_mname` = ?,
        `subscriber_fname` = ?,
        `subscriber_relationship` = ?,
        `subscriber_ss` = ?,
        `subscriber_DOB` = ?,
        `subscriber_street` = ?,
        `subscriber_postal_code` = ?,
        `subscriber_city` = ?,
        `subscriber_state` = ?,
        `subscriber_country` = ?,
        `subscriber_phone` = ?,
        `subscriber_employer` = ?,
        `subscriber_employer_city` = ?,
        `subscriber_employer_street` = ?,
        `subscriber_employer_postal_code` = ?,
        `subscriber_employer_state` = ?,
        `subscriber_employer_country` = ?,
        `copay` = ?,
        `subscriber_sex` = ?,
        `pid` = ?,
        `date` = ?,
        `accept_assignment` = ?,
        `policy_type` = ?,
        `date_end` = ?",
            $binds
        );
    }

    /**
     * Update a policy row's columns by id (the procedural
     * updateInsuranceData()): keys that aren't columns are dropped.
     *
     * @param iterable<mixed, mixed> $new
     */
    public static function update(mixed $id, iterable $new): void
    {
        $fields = QueryUtils::listTableFields("insurance_data");
        $use = [];

        foreach ($new as $key => $value) {
            if (is_string($key) && in_array($key, $fields)) {
                $use[$key] = $value;
            }
        }

        $sqlBindArray = [];
        $sql = "UPDATE insurance_data SET ";
        foreach ($use as $key => $value) {
            $sql .= "`" . $key . "` = ?, ";
            array_push($sqlBindArray, $value);
        }

        $sql = substr($sql, 0, -2) . " WHERE id = ?";
        array_push($sqlBindArray, $id);

        QueryUtils::sqlStatementThrowException($sql, $sqlBindArray);
    }

    /**
     * The patient merge's move: the source chart's policies move to the target
     * where the target has none at that level and start; the rest are deleted.
     */
    public static function moveForMerge(int $sourcePid, int $targetPid): void
    {
        QueryUtils::sqlStatementThrowException(
            "UPDATE `insurance_data` AS `s` LEFT JOIN `insurance_data` AS `t` ON `t`.`pid` = ? AND `t`.`type` = `s`.`type` AND `t`.`date` = `s`.`date`"
            . " SET `s`.`pid` = ? WHERE `s`.`pid` = ? AND `t`.`id` IS NULL",
            [$targetPid, $targetPid, $sourcePid]
        );
        QueryUtils::sqlStatementThrowException("DELETE FROM `insurance_data` WHERE `pid` = ?", [$sourcePid]);
    }
}
