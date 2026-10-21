<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Common\Units;

use OpenEMR\Common\Units\DrugIntervalPeriod;
use OpenEMR\Common\Units\UcumCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DrugIntervalPeriodTest extends TestCase
{
    /**
     * The drug_interval options as installed: [codes, title, value, unit] (null: no period).
     *
     * @return array<string, array{string, string, ?int, ?string}>
     */
    public static function installedOptions(): array
    {
        return [
            'b.i.d.' => ['BID', 'b.i.d.', 12, 'h'],
            't.i.d.' => ['TID', 't.i.d.', 8, 'h'],
            'q.i.d.' => ['QID', 'q.i.d.', 6, 'h'],
            'q.3h' => ['Q3H', 'q.3h', 3, 'h'],
            'q.4h' => ['Q4H', 'q.4h', 4, 'h'],
            'q.5h has no code' => ['', 'q.5h', 5, 'h'],
            'q.6h' => ['Q6H', 'q.6h', 6, 'h'],
            'q.8h' => ['Q8H', 'q.8h', 8, 'h'],
            'Daily' => ['QD', 'Daily', 1, 'd'],
            'h' => ['Q1H', 'h', 1, 'h'],
            'Weekly' => ['WK', 'Weekly', 1, 'wk'],
            'Monthly' => ['MO', 'Monthly', 1, 'mo'],
            'a.c. is an event' => ['', 'a.c.', null, null],
            'p.c. is an event' => ['', 'p.c.', null, null],
            'a.m. is a time of day' => ['AM', 'a.m.', null, null],
            'p.m. is a time of day' => ['PM', 'p.m.', null, null],
            'ante' => ['', 'ante', null, null],
            'h.s. is bedtime' => ['HS', 'h.s.', null, null],
            'p.r.n. is as needed' => ['', 'p.r.n.', null, null],
            'stat is once' => ['', 'stat', null, null],
            'blank' => ['', '', null, null],
        ];
    }

    #[DataProvider('installedOptions')]
    public function testPeriods(string $codes, string $title, ?int $value, ?string $unit): void
    {
        $period = DrugIntervalPeriod::of($codes, $title);
        if ($value === null) {
            $this->assertNull($period);
            return;
        }
        $this->assertSame(['value' => $value, 'unit' => $unit], $period);
        $this->assertTrue(UcumCode::isUcum($unit));
    }

    public function testACodeWinsOverTheTitle(): void
    {
        // a site that renamed the option keeps its code
        $this->assertSame(['value' => 12, 'unit' => 'h'], DrugIntervalPeriod::of('BID', 'twice a day'));
        $this->assertNull(DrugIntervalPeriod::of('', 'twice a day'));
    }
}
