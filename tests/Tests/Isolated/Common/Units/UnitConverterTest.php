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

use OpenEMR\Common\Units\UnitConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitConverterTest extends TestCase
{
    /**
     * @return array<string, array{float, string, string, float}>
     */
    public static function conversions(): array
    {
        return [
            'pounds to kilograms' => [150.0, '[lb_av]', 'kg', 68.0388555],
            'a legacy spelling' => [150.0, 'lbs', 'kg', 68.0388555],
            'kilograms to pounds' => [1.0, 'kg', '[lb_av]', 1 / 0.45359237],
            'inches to centimetres' => [62.0, 'in', 'cm', 157.48],
            'Fahrenheit to Celsius' => [98.6, '[degF]', 'Cel', 37.0],
            'Celsius to Fahrenheit' => [37.0, 'Cel', 'degF', 98.6],
            'grams to pounds' => [1000.0, 'g', '[lb_av]', 1 / 0.45359237],
            'a capitals lab spelling' => [1.0, 'G/DL', 'mg/dL', 1000.0],
            'the same unit' => [5.5, 'mg/dL', 'MG/DL', 5.5],
        ];
    }

    #[DataProvider('conversions')]
    public function testConverts(float $value, string $from, string $to, float $expected): void
    {
        $converted = UnitConverter::convert($value, $from, $to);
        $this->assertNotNull($converted);
        $this->assertEqualsWithDelta($expected, $converted, 1e-9);
    }

    /**
     * @return array<string, array{float, ?string, ?string}>
     */
    public static function impossible(): array
    {
        return [
            'mass to temperature' => [1.0, 'kg', 'Cel'],
            'moles to mass needs a molecular weight' => [5.0, 'mmol/L', 'mg/dL'],
            'an unknown unit' => [1.0, 'stones', 'kg'],
            'an option id' => [1.0, '9', 'mL'],
            'no unit' => [1.0, null, 'kg'],
            'not a number' => [NAN, 'kg', '[lb_av]'],
        ];
    }

    #[DataProvider('impossible')]
    public function testReturnsNullWhenItCantConvert(float $value, ?string $from, ?string $to): void
    {
        $this->assertNull(UnitConverter::convert($value, $from, $to));
    }

    public function testFormatHasNoThousandsSeparator(): void
    {
        // MeasurementUtils' number_format() gave '1,133.980925', which casts to 1.0
        $this->assertSame('1133.980925', UnitConverter::format(2500.0, '[lb_av]', 'kg', 6));
        $this->assertNull(UnitConverter::format(1.0, 'kg', 'Cel', 2));
    }
}
