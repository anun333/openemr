<?php

/**
 * What the coverage validator asks before an insert, an update or a swap:
 * how many policies a level has on a start day or open-ended, and a
 * policy's start and level by uuid. Each is the statement CoverageValidator
 * ran before the boundary branch, moved here unchanged. Split from
 * PolicyRecords by question: these answer the validator, not a screen.
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

final class PolicyChecks
{
    private function __construct()
    {
    }

    /**
     * The latest-starting date at a level (the swap validator).
     */
    public static function latestStartAtLevel(mixed $pid, mixed $type): mixed
    {
        return QueryUtils::fetchSingleValue(
            "SELECT `date` FROM insurance_data WHERE pid = ? AND type = ? ORDER BY date DESC LIMIT 1",
            'date',
            [$pid, $type]
        );
    }

    /**
     * A policy's start date and level by its uuid (bytes): the swap validator.
     *
     * @return list<array<mixed>>
     */
    public static function startAndLevelByUuid(string $uuidBytes): array
    {
        return QueryUtils::fetchRecords("SELECT `date`,`type` FROM insurance_data WHERE uuid = ?", [$uuidBytes]);
    }

    /**
     * Policies at a level starting on a day, leaving out the one with the
     * given uuid (bytes) when there is one.
     */
    public static function countStartingAt(mixed $pid, mixed $type, mixed $date, ?string $exceptUuidBytes): mixed
    {
        $sqlCheck = "SELECT COUNT(*) AS cnt FROM insurance_data WHERE pid = ? AND type = ? AND date = ?";
        $binds = [$pid, $type, $date];
        if ($exceptUuidBytes !== null) {
            $sqlCheck .= " AND uuid != ?";
            $binds[] = $exceptUuidBytes;
        }
        return QueryUtils::fetchSingleValue($sqlCheck, 'cnt', $binds);
    }

    /**
     * Policies at a level with no end date, leaving out the one with the given
     * uuid (bytes) when there is one.
     */
    public static function countOpenAtLevel(mixed $pid, mixed $type, ?string $exceptUuidBytes): mixed
    {
        $sqlCheck = "SELECT COUNT(*) AS cnt FROM insurance_data WHERE pid = ? AND type = ? AND date_end IS NULL";
        $binds = [$pid, $type];
        if ($exceptUuidBytes !== null) {
            $sqlCheck .= " AND uuid != ?";
            $binds[] = $exceptUuidBytes;
        }
        return QueryUtils::fetchSingleValue($sqlCheck, 'cnt', $binds);
    }

    /**
     * Dated policies at a level starting on a day (the swap validator).
     */
    public static function countDatedStartingAt(mixed $pid, mixed $type, mixed $date): mixed
    {
        return QueryUtils::fetchSingleValue(
            "SELECT COUNT(*) AS cnt FROM insurance_data WHERE pid = ? AND type = ? AND date IS NOT NULL AND date = ?",
            'cnt',
            [$pid, $type, $date]
        );
    }
}
