<?php

/**
 * The day each use asks the coverage service about. One place per use, so the
 * answer to "which date decides" is written once.
 *
 * Twin (insurance-reference): the defaults stated in #15038's description are
 * marked DEFAULT with their number; each is one line to change.
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

final class CoverageDay
{
    /**
     * A stored DATE or DATETIME (or a Y-m-d string) as its calendar day, or
     * null when it is blank, zero or not a date. The time is dropped, so a
     * visit at 10:00 asks about its whole day.
     */
    public static function fromStored(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || strlen($value) < 10) {
            return null;
        }
        $text = substr($value, 0, 10);
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        return $day !== false && $day->format('Y-m-d') === $text ? $day : null;
    }

    /**
     * Lab bill-to (HL7 IN1, the requisition).
     *
     * DEFAULT 1 (#15038): the specimen's collection date, else the order date
     * (42 CFR 414.510(a): a lab test's date of service is the collection
     * date). To bill on the order date instead, swap the first two arguments
     * of the ?? chain.
     */
    public static function forLabOrder(mixed $collected, mixed $ordered, DateTimeImmutable $today): DateTimeImmutable
    {
        return self::fromStored($collected) ?? self::fromStored($ordered) ?? $today;
    }

    /** A visit, a charge or a claim: the encounter's date, else today. */
    public static function forVisit(mixed $encounterDate, DateTimeImmutable $today): DateTimeImmutable
    {
        return self::fromStored($encounterDate) ?? $today;
    }

    /**
     * Screens and documents that show "current" coverage (the demographics
     * card, the policy editor, a CCD): today.
     *
     * DEFAULT (research question 10, not in #15038's five): the server's
     * calendar day, as date() gives it.
     */
    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}
