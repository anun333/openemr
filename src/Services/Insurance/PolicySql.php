<?php

/**
 * SQL the coverage service publishes for another domain's query to embed.
 * The Billing Manager, its print views, the day sheets and the OFX export
 * filter their billing query by the patient's primary policy; the Indigent
 * Patients report, Patient List Creation and the prior authorizations list
 * join the policies in. Rather than name the policy table in those queries,
 * they append a predicate or join a relation from here: the table and the
 * rule live in the service, and the statement still runs as one query (a
 * module would publish these as views, or answer with id lists). $patient,
 * $visitDate and $alias name the caller's columns and alias (literals
 * only).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\Insurance;

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
        return "EXISTS (SELECT 1 FROM insurance_data AS ins_p WHERE ins_p.pid = $patient AND ins_p.type = 'primary')";
    }

    /**
     * The Billing Manager's "insurance" criterion. With a payer: some primary names a company and started on
     * or before the visit (or has no start). Without one: some primary has company 0 or starts after the
     * visit. A patient with no primary matches neither, as with the outer join and WHERE it replaces.
     */
    public static function sqlPrimaryPayerOnVisit(bool $withPayer, string $patient, string $visitDate): string
    {
        $match = $withPayer
            ? "ins_c.provider > '0' and (ins_c.date <= $visitDate OR ins_c.date IS NULL)"
            : "(ins_c.provider = '0' or ins_c.date > $visitDate)";
        return "EXISTS (SELECT 1 FROM insurance_data AS ins_c WHERE ins_c.pid = $patient AND ins_c.type = 'primary' AND $match)";
    }

    /**
     * A relation of the primaries with no company (provider ''), one row per policy with its pid, for a
     * caller's FROM list (the Indigent Patients report, which lists a visit once per such policy).
     */
    public static function sqlPrimariesWithoutPayer(string $alias): string
    {
        return "(SELECT pid FROM insurance_data WHERE type = 'primary' AND provider = '') AS $alias";
    }

    /**
     * A relation of every policy's patient, level and company id, with the company's name (name, null when
     * the company is missing), for a caller's outer join (Patient List Creation's insurers, the prior
     * authorizations list).
     */
    public static function sqlPoliciesWithPayerName(string $alias): string
    {
        return "(SELECT insd.pid, insd.type, insd.provider, ic.name FROM insurance_data AS insd " .
            "LEFT JOIN insurance_companies AS ic ON ic.id = insd.provider) AS $alias";
    }
}
