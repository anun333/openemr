<?php

/**
 * MeasurementUtils.php
 * @package openemr
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <stephen@nielson.org>
 * @copyright Copyright (c) 2021 Stephen Nielson <stephen@nielson.org>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Common\Utils;

use OpenEMR\Common\Units\UnitConverter;

/**
 * Conversions through core's UCUM engine, kept with their old output (number_format() with a
 * thousands separator) for callers outside core. New code should use OpenEMR\Common\Units\
 * UnitConverter or VitalUnits, which return floats or plain decimal strings.
 */
class MeasurementUtils
{
    const MEASUREMENT_PRECISION = 6;

    public static function kgToLb(float|int $val): string
    {
        return self::convert($val, 'kg', '[lb_av]');
    }
    public static function lbToKg(float|int $val): string
    {
        return self::convert($val, '[lb_av]', 'kg');
    }
    public static function cmToInches(float|int $val): string
    {
        return self::convert($val, 'cm', '[in_i]');
    }
    public static function inchesToCm(float|int $val): string
    {
        return self::convert($val, '[in_i]', 'cm');
    }
    public static function fhToCelsius(float|int $val): string
    {
        return self::convert($val, '[degF]', 'Cel');
    }

    public static function celsiusToFh(float|int $val): string
    {
        return self::convert($val, 'Cel', '[degF]');
    }

    private static function convert(float|int $val, string $from, string $to): string
    {
        return number_format(round((float)UnitConverter::convert((float)$val, $from, $to), self::MEASUREMENT_PRECISION), self::MEASUREMENT_PRECISION);
    }
}
