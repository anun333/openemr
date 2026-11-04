<?php

/**
 * The level of a patient's insurance policy, as stored in insurance_data.type.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\Insurance;

/**
 * The order staff gave a policy. OpenEMR doesn't compute coordination of
 * benefits: the stored level is the order.
 */
enum InsuranceLevel: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Tertiary = 'tertiary';

    /**
     * The level's number, 1 to 3, as HL7 IN1-22 sends it and as
     * form_encounter.last_level_closed and ar_activity.payer_type count levels.
     */
    public function number(): int
    {
        return match ($this) {
            self::Primary => 1,
            self::Secondary => 2,
            self::Tertiary => 3,
        };
    }
}
