<?php

/**
 * PolicySave: the insurance module's PolicySave under the name the boundary branch gave it, so its callers are
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

final class PolicySave
{
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
        return ServiceContainer::getInsuranceCoverage()->insert($values);
    }

    /**
     * Update a policy row's columns by id (the procedural
     * updateInsuranceData()): keys that aren't columns are dropped.
     *
     * @param iterable<mixed, mixed> $new
     */
    public static function update(mixed $id, iterable $new): void
    {
        ServiceContainer::getInsuranceCoverage()->update($id, $new);
    }

    /**
     * The patient merge's move: the source chart's policies move to the target
     * where the target has none at that level and start; the rest are deleted.
     */
    public static function moveForMerge(int $sourcePid, int $targetPid): void
    {
        ServiceContainer::getInsuranceCoverage()->moveForMerge($sourcePid, $targetPid);
    }
}
