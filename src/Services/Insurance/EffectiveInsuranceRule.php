<?php

/**
 * Which insurance policy is in force for a patient on a day, at each level.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\Insurance;

use DateTimeImmutable;
use OpenEMR\Common\Uuid\UuidRegistry;

/**
 * The rule:
 *
 * - A policy is in force on day D when its start is on or before D and its
 *   end is on or after D. Both ends are inclusive, as FHIR's Period, HL7 v2's
 *   IN1-12 and IN1-13, and QDM's payer start and stop define them.
 * - A blank start means always. A blank end, or 0000-00-00 (which
 *   sql_mode = '' stores for a mistyped date), means open.
 * - At each level, the policy in force with the latest start wins. A blank
 *   start loses to any stored one, 0000-00-00 included; between two blank
 *   starts the higher id (the later entry) wins.
 * - If that policy has no company ('', '0' or NULL), the level has no
 *   coverage on D. This is the "Unassigned" row staff add to end a level.
 * - Levels are independent: an ended primary doesn't hide a current
 *   secondary. They come back in their stored order; nothing here computes
 *   coordination of benefits.
 *
 * Dates are compared as stored, as SQL compares a DATE column. D is the
 * calendar day of the date given, in its own time zone; its time is ignored.
 *
 * This is the claim's rule (Claim::loadPayerInfo()), with three differences:
 *
 * - a 0000-00-00 end is open here, where the claim compares it as a day in
 *   year 0, so the policy has ended;
 * - between two blank starts the claim takes whichever row the database
 *   returns first;
 * - a company that isn't a whole number ('Acme') is no company here, where
 *   the claim bills it.
 *
 * getEffectiveInsurances() in library/patient.inc.php chooses at each level
 * as the claim does, but stops at the first level with nothing in force.
 */
final class EffectiveInsuranceRule
{
    private const ZERO_DATE = '0000-00-00';

    /**
     * @param list<array<mixed>> $rows one patient's insurance_data rows, each with id,
     *                                 uuid, type, provider, date and date_end
     * @return list<EffectiveInsurance> at most one per level, primary first
     */
    public static function choose(array $rows, DateTimeImmutable $day): array
    {
        $onDay = $day->format('Y-m-d');
        $winners = [];
        foreach ($rows as $row) {
            $type = $row['type'] ?? null;
            $level = is_string($type) ? InsuranceLevel::tryFrom($type) : null;
            $id = self::positiveInt($row['id'] ?? null);
            if ($level === null || $id === null) {
                continue;
            }
            // '' for a blank start, which sorts below every stored date and so is in force on any day.
            $start = self::storedDate($row['date'] ?? null);
            $end = self::storedDate($row['date_end'] ?? null);
            if ($end === self::ZERO_DATE) {
                $end = '';
            }
            if (strcmp($start, $onDay) > 0 || ($end !== '' && strcmp($end, $onDay) < 0)) {
                continue;
            }
            $held = $winners[$level->value] ?? null;
            if ($held !== null && !self::outranks($start, $id, $held['start'], $held['id'])) {
                continue;
            }
            $companyId = self::positiveInt($row['provider'] ?? null);
            $winners[$level->value] = [
                'start' => $start,
                'id' => $id,
                'policy' => $companyId === null ? null : new EffectiveInsurance(
                    $level,
                    $id,
                    self::uuidText($row['uuid'] ?? null),
                    $companyId,
                    self::calendarDay($start),
                    self::calendarDay($end),
                ),
            ];
        }

        $inForce = [];
        foreach (InsuranceLevel::cases() as $level) {
            $policy = $winners[$level->value]['policy'] ?? null;
            if ($policy !== null) {
                $inForce[] = $policy;
            }
        }
        return $inForce;
    }

    /**
     * Each policy's time at the head of one level: the days on which choose()
     * returns it there, as runs of consecutive days. A run's from is null when
     * it reaches back to always (a blank start), its to null when it is open.
     * Computed by asking choose() itself at every day the answer can change
     * (each start, and the day after each end), so it can't disagree with it.
     *
     * Twin (insurance-reference): for readers that report over a period, such as
     * eCQM's payer, which QDM gives a start and a stop.
     *
     * @param list<array<mixed>> $rows one patient's insurance_data rows, as for choose()
     * @return list<CoverageSegment> oldest first
     */
    public static function segments(array $rows, InsuranceLevel $level): array
    {
        $points = [];
        foreach ($rows as $row) {
            $type = $row['type'] ?? null;
            if (!is_string($type) || InsuranceLevel::tryFrom($type) !== $level) {
                continue;
            }
            $start = self::calendarDay(self::storedDate($row['date'] ?? null));
            if ($start !== null) {
                $points[$start->format('Y-m-d')] = $start;
            }
            $end = self::calendarDay(self::storedDate($row['date_end'] ?? null));
            if ($end !== null) {
                $after = $end->modify('+1 day');
                $points[$after->format('Y-m-d')] = $after;
            }
        }
        ksort($points);
        $points = array_values($points);
        // The days to ask: one before the first change (what held "always"), then each change.
        $asks = $points === [] ? [new DateTimeImmutable('today')] : [$points[0]->modify('-1 day'), ...$points];
        $segments = [];
        foreach ($asks as $i => $day) {
            $held = null;
            foreach (self::choose($rows, $day) as $policy) {
                if ($policy->level === $level) {
                    $held = $policy;
                }
            }
            if ($held === null) {
                continue;
            }
            $from = $i === 0 ? null : $day;
            $to = $i === 0 ? ($points === [] ? null : $points[0]->modify('-1 day')) : (isset($asks[$i + 1]) ? $asks[$i + 1]->modify('-1 day') : null);
            $last = $segments === [] ? null : $segments[count($segments) - 1];
            if ($last !== null && $last->policy->id === $held->id && $last->to !== null && $from !== null && $last->to->modify('+1 day') == $from) {
                $segments[count($segments) - 1] = new CoverageSegment($held, $last->from, $to);
                continue;
            }
            $segments[] = new CoverageSegment($held, $from, $to);
        }
        return $segments;
    }

    /**
     * Whether a policy outranks the one held for its level: the later start,
     * then the higher id.
     */
    private static function outranks(string $start, int $id, string $heldStart, int $heldId): bool
    {
        $byStart = strcmp($start, $heldStart);
        return $byStart !== 0 ? $byStart > 0 : $id > $heldId;
    }

    /**
     * A DATE column's value as it is stored (YYYY-MM-DD, zeros included), or
     * '' when it is blank.
     */
    private static function storedDate(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    /**
     * A stored date as a calendar day, or null when it is blank or isn't a
     * real day (0000-00-00, or 2026-05-00).
     */
    private static function calendarDay(string $stored): ?DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $stored);
        return $day !== false && $day->format('Y-m-d') === $stored ? $day : null;
    }

    /**
     * A stored id as a positive int: an int, as a read with bound values
     * returns it, or a string of digits (leading zeros and surrounding spaces
     * allowed, as MySQL reads them). Anything else, '' and '0' included, is
     * no id.
     */
    private static function positiveInt(mixed $value): ?int
    {
        if (is_string($value) && ctype_digit(trim($value))) {
            $value = filter_var(ltrim(trim($value), '0'), FILTER_VALIDATE_INT);
        }
        return is_int($value) && $value > 0 ? $value : null;
    }

    private static function uuidText(mixed $bytes): ?string
    {
        return is_string($bytes) && strlen($bytes) === 16 ? UuidRegistry::uuidToString($bytes) : null;
    }
}
