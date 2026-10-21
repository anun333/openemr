<?php

/**
 * Converts a value between two units with core's UCUM engine, returning a
 * float. One conversion implementation for the code outside the eCQM engine
 * that used to carry its own factors (#14698).
 *
 * Either unit may be any spelling UcumCode recognises (`lb`, `MG/DL`);
 * a unit it doesn't recognise, or a pair that can't be converted (kg to Cel,
 * or mmol/L to mg/dL, which needs a molecular weight), yields null.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Common\Units;

use OpenEMR\Cqm\Cql\Ucum\Ucum;

final class UnitConverter
{
    public static function convert(float $value, ?string $fromUnit, ?string $toUnit): ?float
    {
        $from = UcumCode::of($fromUnit);
        $to = UcumCode::of($toUnit);
        if ($from === null || $to === null || is_nan($value) || is_infinite($value)) {
            return null;
        }
        if ($from === $to) {
            return $value;
        }
        return Ucum::instance()->convert($from, $value, $to);
    }

    /**
     * The value converted and rounded, as a plain decimal string ('1133.980925',
     * never '1,133.980925'), or null when it can't be converted.
     */
    public static function format(float $value, ?string $fromUnit, ?string $toUnit, int $decimals): ?string
    {
        $converted = self::convert($value, $fromUnit, $toUnit);
        return $converted === null ? null : number_format(round($converted, $decimals), $decimals, '.', '');
    }
}
