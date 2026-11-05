<?php

/**
 * Which policies are in force: #15038's reads, moved from InsuranceService, which delegates to them
 * through the coverage contract. Static: a read runs none of InsuranceService's setup (its constructor
 * backfills missing uuids). No permission checks: each caller keeps its own.
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

use DateTimeImmutable;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\Insurance\{
    CoverageSegment,
    EffectiveInsurance,
    EffectiveInsuranceRow,
    InsuranceLevel,
};

final class CoverageReads
{
    private function __construct()
    {
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
    public static function getEffectiveInsurances(int $pid, DateTimeImmutable $day): array
    {
        $rows = QueryUtils::fetchRecords(
            "SELECT `id`, `uuid`, `type`, `provider`, `date`, `date_end` FROM `insurance_data` WHERE `pid` = ?",
            [$pid]
        );
        return EffectiveInsuranceRule::choose($rows, $day);
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
    public static function getEffectiveInsuranceRows(int $pid, DateTimeImmutable $day): array
    {
        $rows = QueryUtils::fetchRecords("SELECT * FROM `insurance_data` WHERE `pid` = ?", [$pid]);
        $policies = EffectiveInsuranceRule::choose($rows, $day);
        if ($policies === []) {
            return [];
        }
        $rowsById = [];
        foreach ($rows as $row) {
            // A read with bound values returns the id as an int.
            $id = $row['id'] ?? null;
            if (is_int($id)) {
                $rowsById[$id] = $row;
            }
        }
        $companyIds = array_values(array_unique(array_map(
            static fn(EffectiveInsurance $policy): int => $policy->companyId,
            $policies
        )));
        $names = [];
        $companies = QueryUtils::fetchRecords(
            "SELECT `id`, `name` FROM `insurance_companies` WHERE `id` IN ("
                . implode(',', array_fill(0, count($companyIds), '?')) . ")",
            $companyIds
        );
        foreach ($companies as $company) {
            $id = $company['id'] ?? null;
            $name = $company['name'] ?? null;
            if (is_int($id) && is_string($name)) {
                $names[$id] = $name;
            }
        }
        $result = [];
        foreach ($policies as $policy) {
            if (isset($rowsById[$policy->id])) {
                $result[] = new EffectiveInsuranceRow($policy, $names[$policy->companyId] ?? null, $rowsById[$policy->id]);
            }
        }
        return $result;
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
    public static function getRuleRowsByPid(array $pids): array
    {
        $rowsByPid = [];
        foreach (array_chunk(array_values(array_unique($pids)), 1000) as $chunk) {
            $rows = QueryUtils::fetchRecords(
                "SELECT `id`, `uuid`, `pid`, `type`, `provider`, `date`, `date_end` FROM `insurance_data` WHERE `pid` IN ("
                    . implode(',', array_fill(0, count($chunk), '?')) . ")",
                $chunk
            );
            foreach ($rows as $row) {
                // A read with bound values returns the pid as an int.
                $rowPid = $row['pid'] ?? null;
                if (is_int($rowPid)) {
                    $rowsByPid[$rowPid][] = $row;
                }
            }
        }
        return $rowsByPid;
    }

    /**
     * The runs of days within a range on which each policy heads a level
     * (EffectiveInsuranceRule::segments()), oldest first: for readers that
     * report over a period, such as eCQM's payer over the measurement period.
     *
     * @return list<CoverageSegment>
     */
    public static function getCoverageSegments(int $pid, InsuranceLevel $level, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return EffectiveInsuranceRule::segments(self::getRuleRowsByPid([$pid])[$pid] ?? [], $level, $from, $to);
    }

    /**
     * The policy in force at one level on a day, or null when the level has
     * none, as getEffectiveInsurances() chooses it.
     */
    public static function getEffectiveInsuranceAt(int $pid, InsuranceLevel $level, DateTimeImmutable $day): ?EffectiveInsurance
    {
        foreach (self::getEffectiveInsurances($pid, $day) as $policy) {
            if ($policy->level === $level) {
                return $policy;
            }
        }
        return null;
    }
}
