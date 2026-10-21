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
use OpenEMR\Cqm\Cql\Ucum\Ucum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UcumCodeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validAsWritten(): array
    {
        return [
            'mg' => ['mg'],
            'mL' => ['mL'],
            'mm[Hg]' => ['mm[Hg]'],
            '/min' => ['/min'],
            '%' => ['%'],
            'kg/m2' => ['kg/m2'],
            '10*3/uL' => ['10*3/uL'],
            '[IU]/L' => ['[IU]/L'],
            'Cel' => ['Cel'],
            'mmol/L' => ['mmol/L'],
            '[lb_av]' => ['[lb_av]'],
            '{score}' => ['{score}'],
            'l/min, l is litre too' => ['l/min'],
        ];
    }

    #[DataProvider('validAsWritten')]
    public function testValidCodesAreKeptAsWritten(string $code): void
    {
        $this->assertSame($code, UcumCode::of($code));
        $this->assertTrue(UcumCode::isUcum($code));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function knownSpellings(): array
    {
        return [
            'vitals lb' => ['lb', '[lb_av]'],
            'vitals lbs' => ['lbs', '[lb_av]'],
            'vitals in' => ['in', '[in_i]'],
            'vitals degF' => ['degF', '[degF]'],
            'vitals mmHg' => ['mmHg', 'mm[Hg]'],
            'vitals kg/m^2' => ['kg/m^2', 'kg/m2'],
            'vitals L/MIN' => ['L/MIN', 'L/min'],
            'vitals bpm' => ['bpm', '/min'],
            'drug_units mcg' => ['mcg', 'ug'],
            'drug_units grams' => ['grams', 'g'],
            'drug_units mg/5cc' => ['mg/5cc', 'mg/(5.mL)'],
            'drug_form tsp' => ['tsp', '[tsp_us]'],
            'drug_form gtts' => ['gtts(drops)', '[drp]'],
            'proc_unit MG/DL' => ['MG/DL', 'mg/dL'],
            'proc_unit PG is picograms' => ['PG', 'pg'],
            'proc_unit CU.MM' => ['CU.MM', 'mm3'],
            'proc_unit Thous/CU.MM' => ['Thous/CU.MM', '10*3/mm3'],
            'proc_unit Mil/CU.MM' => ['Mil/CU.MM', '10*6/mm3'],
            'proc_unit FL' => ['FL', 'fL'],
            'proc_unit IU/L' => ['IU/L', '[IU]/L'],
            'proc_unit Units/L' => ['Units/L', 'U/L'],
            'proc_unit Percent' => ['Percent', '%'],
            'lab K/uL is thousands' => ['K/uL', '10*3/uL'],
            'capitals KG' => ['KG', 'kg'],
            'surrounding space' => ['  mg  ', 'mg'],
        ];
    }

    #[DataProvider('knownSpellings')]
    public function testKnownSpellingsMapToTheirCode(string $spelling, string $code): void
    {
        $this->assertSame($code, UcumCode::of($spelling));
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function refused(): array
    {
        return [
            'a drug_units option id' => ['9'],
            'the option id for mg' => ['1'],
            'F is farad' => ['F'],
            'C is coulomb' => ['C'],
            'G is gauss' => ['G'],
            'an unknown capitals spelling' => ['HMOL/L'],
            'a dose form' => ['tablet'],
            'empty' => [''],
            'spaces' => ['   '],
            'null' => [null],
        ];
    }

    #[DataProvider('refused')]
    public function testStringsThatDontMeanAUnitAreRefused(?string $unit): void
    {
        $this->assertNull(UcumCode::of($unit));
        $this->assertFalse(UcumCode::isUcum($unit));
    }

    public function testTheTrapsReallyAreValidUcum(): void
    {
        // what makes them traps: the engine accepts each with another meaning
        foreach (['9', '1', 'F', 'C', 'G', 'PG', 'K/uL'] as $trap) {
            $this->assertTrue(Ucum::instance()->isValid($trap), $trap);
        }
    }

    public function testSpellingsAreNotUcumAsWritten(): void
    {
        foreach (['lb', 'PG', 'MG/DL', 'K/uL', 'mcg'] as $spelling) {
            $this->assertFalse(UcumCode::isUcum($spelling), $spelling);
        }
    }

    public function testEveryMappedCodeIsValid(): void
    {
        $class = new \ReflectionClass(UcumCode::class);
        $codes = array_merge(
            array_values($class->getConstant('SPELLINGS')),
            array_values($class->getConstant('READ_AS'))
        );
        $this->assertGreaterThan(50, count($codes));
        foreach ($codes as $code) {
            $this->assertTrue(Ucum::instance()->isValid($code), $code);
        }
    }

    public function testSpellingKeysAreLowerCase(): void
    {
        foreach (array_keys((new \ReflectionClass(UcumCode::class))->getConstant('SPELLINGS')) as $key) {
            $this->assertSame(mb_strtolower($key), $key);
        }
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function codesFields(): array
    {
        return [
            'after another code' => ['NCI-CONCEPT-ID:C28253;UCUM:mg', 'mg'],
            'alone' => ['UCUM:ug', 'ug'],
            'lower-case prefix' => ['ucum:mL', 'mL'],
            'spaces' => [' NCI-CONCEPT-ID:C28253 ; UCUM: mg/dL ', 'mg/dL'],
            'not valid UCUM' => ['UCUM:lb', null],
            'no UCUM entry' => ['NCI-CONCEPT-ID:C28253', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    public function testForListOptionPrefersTheCodesField(): void
    {
        $this->assertSame('ug', UcumCode::forListOption('mcg', ''));
        $this->assertSame('ug', UcumCode::forListOption('micrograms (site title)', 'UCUM:ug'));
        $this->assertSame('mL', UcumCode::forListOption('mL', 'NCI-CONCEPT-ID:C28254'));
        $this->assertNull(UcumCode::forListOption('tablet', 'NCI-CONCEPT-ID:C42998'));
        $this->assertNull(UcumCode::forListOption(null, null));
    }

    #[DataProvider('codesFields')]
    public function testFromCodesReadsTheUcumEntry(?string $codes, ?string $expected): void
    {
        $this->assertSame($expected, UcumCode::fromCodes($codes));
    }
}
