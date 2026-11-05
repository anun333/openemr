<?php

/**
 * PolicyChecks: the insurance module's PolicyChecks under the name the boundary branch gave it, so its callers are
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
        return ServiceContainer::getInsuranceCoverage()->latestStartAtLevel($pid, $type);
    }

    /**
     * A policy's start date and level by its uuid (bytes): the swap validator.
     *
     * @return list<array<mixed>>
     */
    public static function startAndLevelByUuid(string $uuidBytes): array
    {
        return ServiceContainer::getInsuranceCoverage()->startAndLevelByUuid($uuidBytes);
    }

    /**
     * Policies at a level starting on a day, leaving out the one with the
     * given uuid (bytes) when there is one.
     */
    public static function countStartingAt(mixed $pid, mixed $type, mixed $date, ?string $exceptUuidBytes): mixed
    {
        return ServiceContainer::getInsuranceCoverage()->countStartingAt($pid, $type, $date, $exceptUuidBytes);
    }

    /**
     * Policies at a level with no end date, leaving out the one with the given
     * uuid (bytes) when there is one.
     */
    public static function countOpenAtLevel(mixed $pid, mixed $type, ?string $exceptUuidBytes): mixed
    {
        return ServiceContainer::getInsuranceCoverage()->countOpenAtLevel($pid, $type, $exceptUuidBytes);
    }

    /**
     * Dated policies at a level starting on a day (the swap validator).
     */
    public static function countDatedStartingAt(mixed $pid, mixed $type, mixed $date): mixed
    {
        return ServiceContainer::getInsuranceCoverage()->countDatedStartingAt($pid, $type, $date);
    }
}
