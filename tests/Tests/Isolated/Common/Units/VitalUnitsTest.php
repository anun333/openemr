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

use OpenEMR\Common\Units\UcumCode;
use OpenEMR\Common\Units\VitalUnits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VitalUnitsTest extends TestCase
{
    public function testEveryUnitIsUcum(): void
    {
        $this->assertCount(16, VitalUnits::fields());
        foreach (VitalUnits::fields() as $field) {
            $this->assertTrue(UcumCode::isUcum(VitalUnits::storedUnit($field)), $field);
            $this->assertTrue(UcumCode::isUcum(VitalUnits::metricUnit($field)), $field);
        }
    }

    public function testStoredUnitsAreUsCustomary(): void
    {
        $this->assertSame('[lb_av]', VitalUnits::storedUnit('weight'));
        $this->assertSame('[in_i]', VitalUnits::storedUnit('height'));
        $this->assertSame('[degF]', VitalUnits::storedUnit('temperature'));
        $this->assertSame('kg', VitalUnits::metricUnit('weight'));
        $this->assertSame('/min', VitalUnits::metricUnit('pulse'));
        $this->assertNull(VitalUnits::storedUnit('note'));
        $this->assertNull(VitalUnits::metricUnit('note'));
    }

    /**
     * @return array<string, array{string, float, ?string, ?float}>
     */
    public static function incoming(): array
    {
        return [
            'kilograms' => ['weight', 70.0, 'kg', 154.323584],
            'grams' => ['weight', 70000.0, 'g', 154.323584],
            'pounds as UCUM' => ['weight', 150.0, '[lb_av]', 150.0],
            'pounds as lbs' => ['weight', 150.0, 'lbs', 150.0],
            'centimetres' => ['height', 170.0, 'cm', 66.929134],
            'metres' => ['height', 1.7, 'm', 66.929134],
            'Celsius' => ['temperature', 37.0, 'Cel', 98.6],
            'C for a temperature' => ['temperature', 37.0, 'C', 98.6],
            'F for a temperature' => ['temperature', 98.6, 'F', 98.6],
            'a weight in centimetres' => ['weight', 70.0, 'cm', null],
            'an unknown unit' => ['weight', 11.0, 'stones', null],
            'C for a weight is coulomb' => ['weight', 70.0, 'C', null],
            'no unit' => ['weight', 70.0, null, null],
            'not a measurement' => ['note', 1.0, 'kg', null],
        ];
    }

    #[DataProvider('incoming')]
    public function testToStored(string $field, float $value, ?string $unit, ?float $expected): void
    {
        $stored = VitalUnits::toStored($field, $value, $unit);
        if ($expected === null) {
            $this->assertNull($stored);
            return;
        }
        $this->assertNotNull($stored);
        $this->assertEqualsWithDelta($expected, $stored, 1e-6);
    }

    public function testToMetric(): void
    {
        $this->assertEqualsWithDelta(68.0388555, VitalUnits::toMetric('weight', 150.0), 1e-9);
        $this->assertEqualsWithDelta(157.48, VitalUnits::toMetric('height', 62.0), 1e-9);
        $this->assertEqualsWithDelta(37.0, VitalUnits::toMetric('temperature', 98.6), 1e-9);
        $this->assertSame(72.0, VitalUnits::toMetric('pulse', 72.0));
        $this->assertSame('1133.980925', VitalUnits::toMetricString('weight', 2500.0));
        $this->assertNull(VitalUnits::toMetric('note', 1.0));
    }

    public function testBmi(): void
    {
        // 150 lb, 62 in: 68.0388555 kg / (1.5748 m)^2
        $this->assertEqualsWithDelta(68.0388555 / (1.5748 * 1.5748), VitalUnits::bmi(150.0, 62.0), 1e-9);
        $this->assertNull(VitalUnits::bmi(0.0, 62.0));
        $this->assertNull(VitalUnits::bmi(150.0, 0.0));
    }

    public function testScriptFactorsComeFromTheEngine(): void
    {
        $f = VitalUnits::scriptFactors();
        $this->assertEqualsWithDelta(0.45359237, $f['kgPerLb'], 1e-12);
        $this->assertEqualsWithDelta(2.54, $f['cmPerIn'], 1e-12);
        $this->assertEqualsWithDelta(5 / 9, $f['celPerDegF'], 1e-12);
        $this->assertEqualsWithDelta(-160 / 9, $f['celAtZeroDegF'], 1e-12);
        $this->assertEqualsWithDelta(0.45359237 / (0.0254 * 0.0254), $f['bmiPerLbIn2'], 1e-9);
    }

    public function testLabels(): void
    {
        $this->assertSame('lb', VitalUnits::label('[lb_av]'));
        $this->assertSame('in', VitalUnits::label('[in_i]'));
        $this->assertSame('degF', VitalUnits::label('[degF]'));
        $this->assertSame('kg', VitalUnits::label('kg'));
    }
}
