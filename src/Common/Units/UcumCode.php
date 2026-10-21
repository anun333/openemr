<?php

/**
 * The UCUM code a stored or listed unit string stands for.
 *
 * Units reach OpenEMR's exporters as whatever a list title, a form or an
 * interface stored: `lb`, `MG/DL`, `mcg`. This turns such a string into the
 * UCUM code it means, or null when it means none, so that an exporter claims
 * UCUM only for a code that is UCUM (#14698, #14702).
 *
 * Validity is core's UCUM engine (OpenEMR\Cqm\Cql\Ucum\Ucum, ported from
 * ucum-lhc in #14469). Some strings pass that engine with a meaning OpenEMR
 * never intended, so they don't count as written:
 *  - a bare number (`9`), which UCUM reads as a dimensionless factor and
 *    which, in OpenEMR, is a list option id;
 *  - a spelling with no lower-case letter (`PG`, `MG/DL`, `F`), from lab
 *    systems and old lists. UCUM is case-sensitive: `PG` is petagauss and `F`
 *    is farad, while the list means picograms and a form means Fahrenheit;
 *  - the lab spellings in READ_AS, which UCUM parses as something else.
 * Those strings, and the other spellings OpenEMR has stored, are looked up in
 * a table of known spellings instead, and anything else is null.
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

final class UcumCode
{
    /**
     * The code system name for a list option's codes field: `UCUM:mg`.
     */
    public const CODES_PREFIX = 'UCUM';

    /**
     * Lab spellings that are valid UCUM as written but mean something else,
     * matched exactly. `K/uL` would be kelvin per microlitre.
     */
    private const READ_AS = [
        'K/uL' => '10*3/uL',
        'M/uL' => '10*6/uL',
    ];

    /**
     * Spellings found in OpenEMR's forms, lists and interfaces, keyed in lower
     * case, with the UCUM code each stands for. No single letters: `F` and `C`
     * mean degrees only in a temperature field (VitalUnits).
     */
    private const SPELLINGS = [
        // vitals, as #14698 lists them
        'lb' => '[lb_av]',
        'lbs' => '[lb_av]',
        'lb_av' => '[lb_av]',
        'pound' => '[lb_av]',
        'pounds' => '[lb_av]',
        'oz' => '[oz_av]',
        'in' => '[in_i]',
        'in_i' => '[in_i]',
        'inch' => '[in_i]',
        'inches' => '[in_i]',
        'degf' => '[degF]',
        '°f' => '[degF]',
        'fahrenheit' => '[degF]',
        'degc' => 'Cel',
        '°c' => 'Cel',
        'cel' => 'Cel',
        'celsius' => 'Cel',
        'kg' => 'kg',
        'cm' => 'cm',
        'mmhg' => 'mm[Hg]',
        'bpm' => '/min',
        'per min' => '/min',
        'beats/min' => '/min',
        'breaths/min' => '/min',
        'kg/m^2' => 'kg/m2',
        'kg/m2' => 'kg/m2',
        'l/min' => 'L/min',
        'lpm' => 'L/min',
        // drug_units and drug_form titles
        'mg' => 'mg',
        'mcg' => 'ug',
        'grams' => 'g',
        'gm' => 'g',
        'cc' => 'mL',
        'ml' => 'mL',
        'l' => 'L',
        'mg/1cc' => 'mg/mL',
        'mg/2cc' => 'mg/(2.mL)',
        'mg/3cc' => 'mg/(3.mL)',
        'mg/4cc' => 'mg/(4.mL)',
        'mg/5cc' => 'mg/(5.mL)',
        'tsp' => '[tsp_us]',
        'gtts(drops)' => '[drp]',
        // proc_unit titles, and common lab spellings in capitals
        'cu.mm' => 'mm3',
        'fl' => 'fL',
        'g/dl' => 'g/dL',
        'gm/dl' => 'g/dL',
        'iu/l' => '[IU]/L',
        'mg/dl' => 'mg/dL',
        'mg/l' => 'mg/L',
        'mil/cu.mm' => '10*6/mm3',
        'pg' => 'pg',
        'thous/cu.mm' => '10*3/mm3',
        'percent' => '%',
        'u' => 'U',
        'u/l' => 'U/L',
        'units/l' => 'U/L',
        'iu' => '[IU]',
        'ratio' => '{ratio}',
        'mmol/l' => 'mmol/L',
        'meq/l' => 'meq/L',
        'ng/ml' => 'ng/mL',
        'ng/dl' => 'ng/dL',
        'ug/dl' => 'ug/dL',
        'days' => 'd',
        'weeks' => 'wk',
        'months' => 'mo',
        // ages
        'day' => 'd',
        'week' => 'wk',
        'month' => 'mo',
        'year' => 'a',
        'years' => 'a',
        'yr' => 'a',
        'yrs' => 'a',
    ];

    /**
     * The UCUM code a unit string stands for, or null when it stands for none
     * this can vouch for. Anything but a string (a database null, say) stands
     * for none.
     */
    public static function of(mixed $unit): ?string
    {
        $unit = is_string($unit) ? trim($unit) : '';
        if ($unit === '') {
            return null;
        }
        if (isset(self::READ_AS[$unit])) {
            return self::READ_AS[$unit];
        }
        if (!self::isLegacySpelling($unit) && Ucum::instance()->isValid($unit)) {
            return $unit;
        }
        return self::SPELLINGS[mb_strtolower($unit)] ?? null;
    }

    /**
     * Whether a string is a UCUM code as written, meaning what UCUM says it
     * means: what an exporter may send as a UCUM code unchanged.
     */
    public static function isUcum(mixed $unit): bool
    {
        $code = self::of($unit);
        return $code !== null && is_string($unit) && $code === trim($unit);
    }

    /**
     * The UCUM code in a list option's codes field (`NCI-CONCEPT-ID:C28253;UCUM:mg`),
     * or null when it has none or the one it has isn't valid.
     */
    public static function fromCodes(mixed $codes): ?string
    {
        if (!is_string($codes)) {
            return null;
        }
        foreach (explode(';', $codes) as $entry) {
            $parts = explode(':', trim($entry), 2);
            if (count($parts) === 2 && strcasecmp($parts[0], self::CODES_PREFIX) === 0) {
                $code = trim($parts[1]);
                return $code !== '' && Ucum::instance()->isValid($code) ? $code : null;
            }
        }
        return null;
    }

    /**
     * The UCUM code for a unit list option (drug_units, drug_form, proc_unit): the
     * UCUM entry in its codes field, or else its title read as a unit.
     */
    public static function forListOption(mixed $title, mixed $codes): ?string
    {
        return self::fromCodes($codes) ?? self::of($title);
    }

    /**
     * A bare number, or a spelling with letters but none in lower case, outside
     * UCUM's brackets and braces (which hold upper-case codes such as [IU]).
     */
    private static function isLegacySpelling(string $unit): bool
    {
        if (preg_match('/^[0-9.]+$/', $unit)) {
            return true;
        }
        if (preg_match('/[\[\]{}]/', $unit)) {
            return false;
        }
        return preg_match('/[A-Za-z]/', $unit) === 1 && preg_match('/[a-z]/', $unit) === 0;
    }
}
