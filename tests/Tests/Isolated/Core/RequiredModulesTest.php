<?php

/**
 * RequiredModules and the container's insurance contracts: the insurance module answers in a run with no database
 * and no globals (an isolated test), each contract resolves once, a module may override it, and the classes at the
 * boundary branch's names answer through the contract.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Core;

use Countable;
use LogicException;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\RequiredModules;
use OpenEMR\Modules\Insurance\InsuranceCoverage;
use OpenEMR\Modules\Insurance\InsurancePayers;
use OpenEMR\Services\Insurance\Contract\Coverage;
use OpenEMR\Services\Insurance\Contract\Payer;
use OpenEMR\Services\Insurance\PolicySql;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequiredModulesTest extends TestCase
{
    protected function tearDown(): void
    {
        ServiceContainer::reset();
    }

    #[Test]
    public function theInsuranceModuleDefinesBothContracts(): void
    {
        self::assertSame(InsuranceCoverage::class, RequiredModules::make(Coverage::class)::class);
        self::assertSame(InsurancePayers::class, RequiredModules::make(Payer::class)::class);
    }

    #[Test]
    public function aContractNoRequiredModuleDefinesIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No required module defines ' . Countable::class);
        RequiredModules::make(Countable::class);
    }

    #[Test]
    public function theContainerResolvesEachContractOnceAndAModuleMayOverrideIt(): void
    {
        $coverage = ServiceContainer::getInsuranceCoverage();
        self::assertSame($coverage, ServiceContainer::getInsuranceCoverage());
        self::assertSame(InsuranceCoverage::class, $coverage::class);
        $payer = ServiceContainer::getInsurancePayer();
        self::assertSame($payer, ServiceContainer::getInsurancePayer());

        $stub = $this->createStub(Coverage::class);
        ServiceContainer::override(Coverage::class, $stub);
        self::assertSame($stub, ServiceContainer::getInsuranceCoverage());
    }

    #[Test]
    public function theOldNamesAnswerThroughTheContract(): void
    {
        // Through the module: a pure answer, no database (InsuranceCompanyService's class needs one to load).
        self::assertSame(
            "(SELECT pid FROM insurance_data WHERE type = 'primary' AND provider = '') AS ins",
            PolicySql::sqlPrimariesWithoutPayer('ins')
        );

        // Through whatever the container holds: the wrapper adds nothing of its own.
        $coverage = $this->createMock(Coverage::class);
        $coverage->expects(self::once())->method('sqlPrimariesWithoutPayer')->with('ins')->willReturn('from the contract');
        ServiceContainer::override(Coverage::class, $coverage);
        self::assertSame('from the contract', PolicySql::sqlPrimariesWithoutPayer('ins'));
    }
}
