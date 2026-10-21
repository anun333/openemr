<?php

/**
 * The time between doses a drug_interval option stands for, as a UCUM
 * quantity, for the period of a C-CDA PIVL_TS or a FHIR Timing (#14705).
 *
 * Only the regular intervals have one. `a.c.`, `p.c.` and `h.s.` are tied to
 * meals and bedtime (EIVL_TS events in C-CDA), `p.r.n.` is as needed, `stat`
 * is once, and `a.m.`, `p.m.` and `ante` name a time of day or an event; none
 * of those is a period, so they have none here.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Common\Units;

final class DrugIntervalPeriod
{
    /**
     * drug_interval codes (the list's codes field) => [value, UCUM unit].
     */
    private const BY_CODE = [
        'Q1H' => [1, 'h'],
        'Q3H' => [3, 'h'],
        'Q4H' => [4, 'h'],
        'Q5H' => [5, 'h'],
        'Q6H' => [6, 'h'],
        'Q8H' => [8, 'h'],
        'BID' => [12, 'h'],
        'TID' => [8, 'h'],
        'QID' => [6, 'h'],
        'QD' => [1, 'd'],
        'WK' => [1, 'wk'],
        'MO' => [1, 'mo'],
    ];

    /**
     * drug_interval titles as installed, lower case, for options with no code.
     */
    private const BY_TITLE = [
        'h' => 'Q1H',
        'b.i.d.' => 'BID',
        't.i.d.' => 'TID',
        'q.i.d.' => 'QID',
        'daily' => 'QD',
        'weekly' => 'WK',
        'monthly' => 'MO',
    ];

    /**
     * @return array{value: int, unit: string}|null the period, or null for an option that has none
     */
    public static function of(mixed $codes, mixed $title): ?array
    {
        foreach (explode(';', is_string($codes) ? $codes : '') as $code) {
            $code = strtoupper(trim($code));
            if (isset(self::BY_CODE[$code])) {
                return self::period(self::BY_CODE[$code]);
            }
        }
        $title = mb_strtolower(trim(is_string($title) ? $title : ''));
        if (isset(self::BY_TITLE[$title])) {
            return self::period(self::BY_CODE[self::BY_TITLE[$title]]);
        }
        // q.4h, q4h, q 4 h
        if (preg_match('/^q\.?\s*(\d+)\s*h$/', $title, $m) === 1 && (int)$m[1] > 0) {
            return ['value' => (int)$m[1], 'unit' => 'h'];
        }
        return null;
    }

    /**
     * @param array{int, string} $entry
     * @return array{value: int, unit: string}
     */
    private static function period(array $entry): array
    {
        return ['value' => $entry[0], 'unit' => $entry[1]];
    }
}
