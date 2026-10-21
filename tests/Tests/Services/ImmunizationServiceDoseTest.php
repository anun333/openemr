<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services;

use OpenEMR\Services\ImmunizationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImmunizationServiceDoseTest extends TestCase
{
    /**
     * @return array<string, array{mixed, int|float}>
     */
    public static function amounts(): array
    {
        return [
            'half a millilitre, the usual vaccine dose' => ['0.5', 0.5],
            'a whole number stays an integer' => ['25', 25],
            'a float column value' => [0.25, 0.25],
            'no amount is 0, as before' => [null, 0],
            'an empty amount is 0, as before' => ['', 0],
        ];
    }

    #[DataProvider('amounts')]
    public function testTheDoseKeepsItsFraction(mixed $stored, int|float $expected): void
    {
        $record = (new ImmunizationService())->createResultRecordFromDatabaseResult([
            'primarySource' => '1',
            'amount_administered' => $stored,
        ]);
        $this->assertSame($expected, $record['amount_administered']);
    }
}
