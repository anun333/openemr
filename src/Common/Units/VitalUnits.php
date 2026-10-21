<?php

/**
 * The units of the Vitals form's columns, in UCUM, and their conversions.
 *
 * form_vitals has no unit columns: each column holds a bare number in a
 * fixed unit, US customary for the lengths, weight and temperature whatever
 * the units_of_measurement global says (it changes only what screens show).
 * This is the one place that says which unit each column is in and converts
 * to and from it, with core's UCUM engine, so that no screen, exporter or
 * importer carries its own factors (#14698).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Common\Units;

final class VitalUnits
{
    /**
     * Decimal places a converted vital keeps, as MeasurementUtils did.
     */
    public const PRECISION = 6;

    /**
     * form_vitals column => [the UCUM unit it's stored in, the UCUM unit a
     * metric screen shows it in (null: the same)].
     */
    private const FIELDS = [
        'weight' => ['[lb_av]', 'kg'],
        'height' => ['[in_i]', 'cm'],
        'head_circ' => ['[in_i]', 'cm'],
        'waist_circ' => ['[in_i]', 'cm'],
        'temperature' => ['[degF]', 'Cel'],
        'pulse' => ['/min', null],
        'respiration' => ['/min', null],
        'BMI' => ['kg/m2', null],
        'bps' => ['mm[Hg]', null],
        'bpd' => ['mm[Hg]', null],
        'inhaled_oxygen_concentration' => ['%', null],
        'oxygen_saturation' => ['%', null],
        'oxygen_flow_rate' => ['L/min', null],
        'ped_weight_height' => ['%', null],
        'ped_bmi' => ['%', null],
        'ped_head_circ' => ['%', null],
    ];

    /**
     * How a person reads the UCUM codes that aren't plain words.
     */
    private const LABELS = [
        '[lb_av]' => 'lb',
        '[in_i]' => 'in',
        '[degF]' => 'degF',
    ];

    /**
     * Single letters only a temperature can read as degrees. Elsewhere UCUM
     * reads them as farad and coulomb.
     */
    private const TEMPERATURE_SPELLINGS = [
        'F' => '[degF]',
        'f' => '[degF]',
        'C' => 'Cel',
        'c' => 'Cel',
    ];

    /**
     * @return list<string> the form_vitals columns that hold a measurement
     */
    public static function fields(): array
    {
        return array_keys(self::FIELDS);
    }

    /**
     * The UCUM unit a form_vitals column is stored in, or null for a column
     * that isn't a measurement.
     */
    public static function storedUnit(string $field): ?string
    {
        return self::FIELDS[$field][0] ?? null;
    }

    /**
     * The UCUM unit a metric screen shows a column in.
     */
    public static function metricUnit(string $field): ?string
    {
        if (!isset(self::FIELDS[$field])) {
            return null;
        }
        return self::FIELDS[$field][1] ?? self::FIELDS[$field][0];
    }

    /**
     * Display text for a UCUM unit: `lb` for [lb_av]; most codes read as written.
     */
    public static function label(string $ucum): string
    {
        return self::LABELS[$ucum] ?? $ucum;
    }

    /**
     * The UCUM code for a unit sent with a value for this column, reading `F`
     * and `C` as degrees for the temperature; null when it can't be placed.
     */
    public static function unitFor(string $field, ?string $unit): ?string
    {
        $unit = trim((string)$unit);
        if ($field === 'temperature' && isset(self::TEMPERATURE_SPELLINGS[$unit])) {
            return self::TEMPERATURE_SPELLINGS[$unit];
        }
        return UcumCode::of($unit);
    }

    /**
     * A stored value in the unit a metric screen shows, unrounded.
     */
    public static function toMetric(string $field, float $stored): ?float
    {
        $from = self::storedUnit($field);
        return $from === null ? null : UnitConverter::convert($stored, $from, self::metricUnit($field));
    }

    /**
     * A stored value in metric as a plain decimal string ('1133.980925'), or
     * null for a column that isn't a measurement.
     */
    public static function toMetricString(string $field, float $stored, int $decimals = self::PRECISION): ?string
    {
        $converted = self::toMetric($field, $stored);
        return $converted === null ? null : number_format(round($converted, $decimals), $decimals, '.', '');
    }

    /**
     * A value sent in any unit, in the unit its column is stored in, rounded
     * to PRECISION. Null when the unit can't be placed or doesn't measure the
     * same thing; storing the bare number then would record 70 lb for a 70 kg
     * patient, so callers reject instead.
     */
    public static function toStored(string $field, float $value, ?string $unit): ?float
    {
        $to = self::storedUnit($field);
        $from = self::unitFor($field, $unit);
        if ($to === null || $from === null) {
            return null;
        }
        $converted = UnitConverter::convert($value, $from, $to);
        return $converted === null ? null : round($converted, self::PRECISION);
    }

    /**
     * BMI from a weight and height as stored (pounds and inches).
     */
    public static function bmi(float $storedWeight, float $storedHeight): ?float
    {
        if ($storedWeight <= 0 || $storedHeight <= 0) {
            return null;
        }
        return UnitConverter::convert($storedWeight / ($storedHeight * $storedHeight), '[lb_av]/[in_i]2', 'kg/m2');
    }

    /**
     * The factors a page's script needs to convert as the engine does, all
     * computed by the engine: kilograms per pound, centimetres per inch, the
     * Celsius scale and offset for Fahrenheit, and BMI per lb/in².
     *
     * @return array{kgPerLb: float, cmPerIn: float, celPerDegF: float, celAtZeroDegF: float, bmiPerLbIn2: float}
     */
    public static function scriptFactors(): array
    {
        $celAtZero = (float)UnitConverter::convert(0.0, '[degF]', 'Cel');
        return [
            'kgPerLb' => (float)UnitConverter::convert(1.0, '[lb_av]', 'kg'),
            'cmPerIn' => (float)UnitConverter::convert(1.0, '[in_i]', 'cm'),
            'celPerDegF' => (float)UnitConverter::convert(1.0, '[degF]', 'Cel') - $celAtZero,
            'celAtZeroDegF' => $celAtZero,
            'bmiPerLbIn2' => (float)UnitConverter::convert(1.0, '[lb_av]/[in_i]2', 'kg/m2'),
        ];
    }
}
