<?php

/**
 * The insurance module's Coverage contract: each method one call to the module class that holds
 * its query (the contract's docblocks say what each answers). Made by the module's container
 * definitions (config/services.php). Generated from the module's classes.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Insurance;

use DateTimeImmutable;
use OpenEMR\Modules\Insurance\Policies\CoverageReads;
use OpenEMR\Modules\Insurance\Policies\PolicyChecks;
use OpenEMR\Modules\Insurance\Policies\PolicyRecords;
use OpenEMR\Modules\Insurance\Policies\PolicySave;
use OpenEMR\Modules\Insurance\Policies\PolicySql;
use OpenEMR\Modules\Insurance\Policies\PolicyStatements;
use OpenEMR\Services\Insurance\Contract\Coverage;
use OpenEMR\Services\Insurance\{
    EffectiveInsurance,
    InsuranceLevel,
};

final class InsuranceCoverage implements Coverage
{
    public function latestAtLevel(mixed $pid, mixed $type, string $columns = 'insd.*, ic.name as provider_name'): array|false
    {
        return PolicyRecords::latestAtLevel($pid, $type, $columns);
    }

    public function latestIdAtLevel(mixed $pid, mixed $type): array|false
    {
        return PolicyRecords::latestIdAtLevel($pid, $type);
    }

    public function latestSecondaryWithPayer(mixed $pid): array|false
    {
        return PolicyRecords::latestSecondaryWithPayer($pid);
    }

    public function historyAtLevel(mixed $pid, mixed $type, string $columns = 'insd.*, ic.name as provider_name'): array
    {
        return PolicyRecords::historyAtLevel($pid, $type, $columns);
    }

    public function historyOldestFirst(mixed $pid, mixed $type): array
    {
        return PolicyRecords::historyOldestFirst($pid, $type);
    }

    public function historyWithPayerName(mixed $pid, mixed $type): array
    {
        return PolicyRecords::historyWithPayerName($pid, $type);
    }

    public function onDateAtLevel(mixed $pid, mixed $date, mixed $type, string $columns): array|false
    {
        return PolicyRecords::onDateAtLevel($pid, $date, $type, $columns);
    }

    public function primaryCopayOn(mixed $pid, mixed $date): array|false
    {
        return PolicyRecords::primaryCopayOn($pid, $date);
    }

    public function levelAndPayerNameOn(mixed $pid, mixed $provider, mixed $date): array|false
    {
        return PolicyRecords::levelAndPayerNameOn($pid, $provider, $date);
    }

    public function allByLevelLatestFirst(mixed $pid): array
    {
        return PolicyRecords::allByLevelLatestFirst($pid);
    }

    public function requisitionRows(mixed $pid): array
    {
        return PolicyRecords::requisitionRows($pid);
    }

    public function firstSubscriberRelationship(mixed $pid): mixed
    {
        return PolicyRecords::firstSubscriberRelationship($pid);
    }

    public function guarantorRowOn(mixed $pid, mixed $date): array
    {
        return PolicyRecords::guarantorRowOn($pid, $date);
    }

    public function countPrimaryWithSubscriber(mixed $pid): array|false
    {
        return PolicyRecords::countPrimaryWithSubscriber($pid);
    }

    public function countLevelsWithPayer(mixed $pid): mixed
    {
        return PolicyRecords::countLevelsWithPayer($pid);
    }

    public function levelsAndPayerNames(mixed $pid): array
    {
        return PolicyRecords::levelsAndPayerNames($pid);
    }

    public function primaryIdAndCopay(mixed $pid): array|false
    {
        return PolicyRecords::primaryIdAndCopay($pid);
    }

    public function eligibilityResults(mixed $pid): array
    {
        return PolicyRecords::eligibilityResults($pid);
    }

    public function selfPlanNamesStatement(mixed $pid): \ADORecordSet
    {
        return PolicyRecords::selfPlanNamesStatement($pid);
    }

    public function primarySubscriberAsResponsibleParty(mixed $pid): array|false
    {
        return PolicyRecords::primarySubscriberAsResponsibleParty($pid);
    }

    public function primaryRelationshipRow(mixed $pid): array|false
    {
        return PolicyRecords::primaryRelationshipRow($pid);
    }

    public function primaryPayerNameRow(mixed $pid): array|false
    {
        return PolicyRecords::primaryPayerNameRow($pid);
    }

    public function withPayerById(mixed $type, mixed $pid, mixed $id): array|false
    {
        return PolicyRecords::withPayerById($type, $pid, $id);
    }

    public function columnsById(mixed $id, string $columns): array|false
    {
        return PolicyRecords::columnsById($id, $columns);
    }

    public function withPayerForBillingExport(mixed $pid, mixed $providerId): array
    {
        return PolicyRecords::withPayerForBillingExport($pid, $providerId);
    }

    public function byUuid(string $uuidBytes): array|false
    {
        return PolicyRecords::byUuid($uuidBytes);
    }

    public function forPatientsAtLevel(array $pids, mixed $type, string $columns = 'insd.*, ic.name AS company_name'): array
    {
        return PolicyRecords::forPatientsAtLevel($pids, $type, $columns);
    }

    public function patientsWithPayerLike(string $provider): array
    {
        return PolicyRecords::patientsWithPayerLike($provider);
    }

    public function primaryPayerTypes(array $pids): array
    {
        return PolicyRecords::primaryPayerTypes($pids);
    }

    public function primaryPayerTypesForPatient(mixed $pid): array
    {
        return PolicyRecords::primaryPayerTypesForPatient($pid);
    }

    public function latestStartAtLevel(mixed $pid, mixed $type): mixed
    {
        return PolicyChecks::latestStartAtLevel($pid, $type);
    }

    public function startAndLevelByUuid(string $uuidBytes): array
    {
        return PolicyChecks::startAndLevelByUuid($uuidBytes);
    }

    public function countStartingAt(mixed $pid, mixed $type, mixed $date, ?string $exceptUuidBytes): mixed
    {
        return PolicyChecks::countStartingAt($pid, $type, $date, $exceptUuidBytes);
    }

    public function countOpenAtLevel(mixed $pid, mixed $type, ?string $exceptUuidBytes): mixed
    {
        return PolicyChecks::countOpenAtLevel($pid, $type, $exceptUuidBytes);
    }

    public function countDatedStartingAt(mixed $pid, mixed $type, mixed $date): mixed
    {
        return PolicyChecks::countDatedStartingAt($pid, $type, $date);
    }

    public function sqlPrimaryOnFile(string $patient): string
    {
        return PolicySql::sqlPrimaryOnFile($patient);
    }

    public function sqlPrimaryPayerOnVisit(bool $withPayer, string $patient, string $visitDate): string
    {
        return PolicySql::sqlPrimaryPayerOnVisit($withPayer, $patient, $visitDate);
    }

    public function sqlPrimariesWithoutPayer(string $alias): string
    {
        return PolicySql::sqlPrimariesWithoutPayer($alias);
    }

    public function sqlPoliciesWithPayerName(string $alias): string
    {
        return PolicySql::sqlPoliciesWithPayerName($alias);
    }

    public function insert(array $values): int
    {
        return PolicySave::insert($values);
    }

    public function update(mixed $id, iterable $new): void
    {
        PolicySave::update($id, $new);
    }

    public function moveForMerge(int $sourcePid, int $targetPid): void
    {
        PolicySave::moveForMerge($sourcePid, $targetPid);
    }

    public function getEffectiveInsurances(int $pid, DateTimeImmutable $day): array
    {
        return CoverageReads::getEffectiveInsurances($pid, $day);
    }

    public function getEffectiveInsuranceRows(int $pid, DateTimeImmutable $day): array
    {
        return CoverageReads::getEffectiveInsuranceRows($pid, $day);
    }

    public function getRuleRowsByPid(array $pids): array
    {
        return CoverageReads::getRuleRowsByPid($pids);
    }

    public function getCoverageSegments(int $pid, InsuranceLevel $level, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return CoverageReads::getCoverageSegments($pid, $level, $from, $to);
    }

    public function getEffectiveInsuranceAt(int $pid, InsuranceLevel $level, DateTimeImmutable $day): ?EffectiveInsurance
    {
        return CoverageReads::getEffectiveInsuranceAt($pid, $level, $day);
    }

    public function rowByPidAndType(mixed $id, mixed $type): array|false
    {
        return PolicyStatements::rowByPidAndType($id, $type);
    }

    public function searchStatement(string $whereFragment, array $sqlBindArray): \ADORecordSet
    {
        return PolicyStatements::searchStatement($whereFragment, $sqlBindArray);
    }

    public function rowByUuid(string $uuidBytes): array|false
    {
        return PolicyStatements::rowByUuid($uuidBytes);
    }

    public function idAtLevel(mixed $pid, mixed $type): mixed
    {
        return PolicyStatements::idAtLevel($pid, $type);
    }

    public function hasPolicy(mixed $pid): bool
    {
        return PolicyStatements::hasPolicy($pid);
    }

    public function pidByUuid(string $uuidBinaryCheck): mixed
    {
        return PolicyStatements::pidByUuid($uuidBinaryCheck);
    }

    public function updateByUuid(array $binds): true
    {
        return PolicyStatements::updateByUuid($binds);
    }

    public function insertPolicy(array $binds): int
    {
        return PolicyStatements::insertPolicy($binds);
    }

    public function insertImportedPolicy(array $binds): int
    {
        return PolicyStatements::insertImportedPolicy($binds);
    }

    public function latestUuidAtLevel(mixed $pid, string $targetType): mixed
    {
        return PolicyStatements::latestUuidAtLevel($pid, $targetType);
    }
}
