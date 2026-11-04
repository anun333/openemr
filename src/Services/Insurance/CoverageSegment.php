<?php

/**
 * A run of days on which one policy heads its level (EffectiveInsuranceRule::segments()).
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

final readonly class CoverageSegment
{
    /**
     * @param ?DateTimeImmutable $from the first day, or null when it reaches back to always (a blank start)
     * @param ?DateTimeImmutable $to   the last day, included, or null when it is open
     */
    public function __construct(
        public EffectiveInsurance $policy,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $to,
    ) {
    }
}
