<?php

/**
 * FHIR Coverage against US Core's Coverage profile and the FHIR validator: a stored date that
 * isn't a day (a zero date) is no date, so the period stays a valid FHIR Period and a zero end
 * reads as open, and the copay type carries its code system's display.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @copyright Copyright (c) 2026 OpenEMR Foundation
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Services\FHIR;

use OpenEMR\Services\FHIR\FhirCoverageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FhirCoverageSpecTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function policy(?string $start, ?string $end): array
    {
        return ['uuid' => '0b8d8f8a-1b2c-4d3e-8f9a-0b1c2d3e4f51', 'puuid' => '0b8d8f8a-1b2c-4d3e-8f9a-0b1c2d3e4f52',
            'insureruuid' => '0b8d8f8a-1b2c-4d3e-8f9a-0b1c2d3e4f53', 'type' => 'primary', 'policy_number' => 'M-1',
            'subscriber_relationship' => 'self', 'cqm_sop' => '5', 'ins_type_code' => '17', 'copay' => '20',
            'date' => $start, 'date_end' => $end];
    }

    /**
     * The Coverage read from the policy, as its JSON decodes. The read needs no database: the
     * service's constructor (which makes InsuranceService) is skipped.
     */
    private static function read(?string $start, ?string $end): mixed
    {
        $service = (new \ReflectionClass(FhirCoverageService::class))->newInstanceWithoutConstructor();
        return json_decode((string)json_encode($service->parseOpenEMRRecord(self::policy($start, $end))), true);
    }

    /**
     * The value at a path in a decoded document, or null when the path isn't there.
     */
    private static function at(mixed $value, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: bool, 3: bool, 4: string}>
     * @codeCoverageIgnore
     */
    public static function datesProvider(): array
    {
        return [
            'a zero end is open' => ['2020-01-01', '0000-00-00', true, false, 'active'],
            'a zero start is none' => ['0000-00-00', null, false, false, 'active'],
            'no dates' => [null, null, false, false, 'active'],
            'an ended plan' => ['2020-01-01', '2021-01-01', true, true, 'cancelled'],
            'an open plan' => ['2020-01-01', null, true, false, 'active'],
            'not a day' => ['2020-02-30', null, false, false, 'active'],
        ];
    }

    #[Test]
    #[DataProvider('datesProvider')]
    public function aStoredDateThatIsNotADayIsNoDate(?string $start, ?string $end, bool $hasStart, bool $hasEnd, string $status): void
    {
        $json = self::read($start, $end);
        $this->assertSame($hasStart, self::at($json, 'period', 'start') !== null, 'period.start');
        $this->assertSame($hasEnd, self::at($json, 'period', 'end') !== null, 'period.end');
        foreach (['start', 'end'] as $bound) {
            $value = self::at($json, 'period', $bound);
            if ($value !== null) {
                $this->assertIsString($value);
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $value);
            }
        }
        $this->assertSame($status, self::at($json, 'status'));
    }

    #[Test]
    public function theCopayTypeCarriesItsCodeSystemsDisplay(): void
    {
        $coding = self::at(self::read('2020-01-01', null), 'costToBeneficiary', 0, 'type', 'coding', 0);
        $this->assertSame(
            ['http://terminology.hl7.org/CodeSystem/coverage-copay-type', 'copay', 'Copay Amount'],
            [self::at($coding, 'system'), self::at($coding, 'code'), self::at($coding, 'display')]
        );
    }
}
