<?php

/**
 * PolicySql: the insurance module's PolicySql under the name the boundary branch gave it, so its callers are
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

final class PolicySql
{
    private function __construct()
    {
    }

    /**
     * The patient has a primary policy on file (any dates, any company): what an inner join on the primary
     * required.
     */
    public static function sqlPrimaryOnFile(string $patient): string
    {
        return ServiceContainer::getInsuranceCoverage()->sqlPrimaryOnFile($patient);
    }

    /**
     * The Billing Manager's "insurance" criterion. With a payer: some primary names a company and started on
     * or before the visit (or has no start). Without one: some primary has company 0 or starts after the
     * visit. A patient with no primary matches neither, as with the outer join and WHERE it replaces.
     */
    public static function sqlPrimaryPayerOnVisit(bool $withPayer, string $patient, string $visitDate): string
    {
        return ServiceContainer::getInsuranceCoverage()->sqlPrimaryPayerOnVisit($withPayer, $patient, $visitDate);
    }

    /**
     * A relation of the primaries with no company (provider ''), one row per policy with its pid, for a
     * caller's FROM list (the Indigent Patients report, which lists a visit once per such policy).
     */
    public static function sqlPrimariesWithoutPayer(string $alias): string
    {
        return ServiceContainer::getInsuranceCoverage()->sqlPrimariesWithoutPayer($alias);
    }

    /**
     * A relation of every policy's patient, level and company id, with the company's name (name, null when
     * the company is missing), for a caller's outer join (Patient List Creation's insurers, the prior
     * authorizations list).
     */
    public static function sqlPoliciesWithPayerName(string $alias): string
    {
        return ServiceContainer::getInsuranceCoverage()->sqlPoliciesWithPayerName($alias);
    }
}
