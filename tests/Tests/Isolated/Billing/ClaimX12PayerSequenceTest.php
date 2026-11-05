<?php

/**
 * The claim's payer order as X12 5010 sends it (SBR01 in loops 2000B and 2320): the payers in
 * force on the date of service, in level order, are P, S and T with no gap; the stored level
 * (payerSequence(), which the posting levels use) is unchanged, and the site switch keeps it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @copyright Copyright (c) 2026 OpenEMR Foundation
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Billing;

use OpenEMR\Billing\Claim;
use OpenEMR\Core\OEGlobalsBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClaimX12PayerSequenceTest extends TestCase
{
    protected function tearDown(): void
    {
        OEGlobalsBag::getInstance()->remove('x12_5010_payer_rules');
    }

    /**
     * @param list<string> $levels the payers in force, the billed one first, then in payment order
     */
    private static function claim(array $levels): Claim
    {
        $claim = new class extends Claim {
            public function __construct()
            {
            }
        };
        $claim->payers = array_map(fn(string $l): array => ['data' => ['type' => $l], 'company' => [], 'object' => null], $levels);
        return $claim;
    }

    /**
     * @return array<string, array{0: list<string>, 1: list<string>, 2: list<string>}>
     * @codeCoverageIgnore
     */
    public static function claimsProvider(): array
    {
        return [
            'primary and secondary' => [['primary', 'secondary'], ['P', 'S'], ['P', 'S']],
            'a sole secondary' => [['secondary'], ['P'], ['S']],
            'secondary and tertiary, billing the secondary' => [['secondary', 'tertiary'], ['P', 'S'], ['S', 'T']],
            'secondary and tertiary, billing the tertiary' => [['tertiary', 'secondary'], ['S', 'P'], ['T', 'S']],
            'all three, billing the secondary' => [['secondary', 'primary', 'tertiary'], ['S', 'P', 'T'], ['S', 'P', 'T']],
            'a sole tertiary' => [['tertiary'], ['P'], ['T']],
        ];
    }

    /**
     * @param list<string> $levels
     * @param list<string> $x12
     * @param list<string> $stored
     */
    #[Test]
    #[DataProvider('claimsProvider')]
    public function thePayersInForceAreSentInOrderWithNoGap(array $levels, array $x12, array $stored): void
    {
        $claim = self::claim($levels);
        $this->assertSame($x12, array_map($claim->x12PayerSequence(...), array_keys($levels)));
        $this->assertSame($stored, array_map($claim->payerSequence(...), array_keys($levels)));
    }

    /**
     * @param list<string> $levels
     * @param list<string> $x12
     * @param list<string> $stored
     */
    #[Test]
    #[DataProvider('claimsProvider')]
    public function withTheSwitchOffTheStoredLevelIsSent(array $levels, array $x12, array $stored): void
    {
        OEGlobalsBag::getInstance()->set('x12_5010_payer_rules', '0');
        $claim = self::claim($levels);
        $this->assertSame($stored, array_map($claim->x12PayerSequence(...), array_keys($levels)));
    }

    #[Test]
    public function aClaimWithNoPayerSendsNoSequence(): void
    {
        $this->assertSame('', self::claim([])->x12PayerSequence());
    }
}
