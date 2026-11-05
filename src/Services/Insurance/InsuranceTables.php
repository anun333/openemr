<?php

/**
 * The insurance tables' names, written once. Registries that list tables by
 * name (the audit log's categories, the uuid registry, the EHI export's
 * traversal, the patient deleter and merge, the legacy ORM) name them through
 * these constants, so nothing outside the insurance services spells a table.
 * A module would register its tables instead; until then these are the one
 * place the names live.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\Insurance;

final class InsuranceTables
{
    /** Policies: one row per policy a patient holds, by level (insurance_data). */
    public const POLICIES = 'insurance_data';

    /** Payers: one row per insurance company (insurance_companies). */
    public const PAYERS = 'insurance_companies';

    private function __construct()
    {
    }
}
