<?php

/**
 * The insurance module's Payer contract: each method one call to the module class that holds
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

use OpenEMR\Modules\Insurance\Payers\PayerRecords;
use OpenEMR\Modules\Insurance\Payers\PayerStatements;
use OpenEMR\Services\Insurance\Contract\Payer;

final class InsurancePayers implements Payer
{
    public function nameOf(mixed $id): mixed
    {
        return PayerRecords::nameOf($id);
    }

    public function namesOf(array $ids): array
    {
        return PayerRecords::namesOf($ids);
    }

    public function nameFrom(array $names, mixed $id): mixed
    {
        return PayerRecords::nameFrom($names, $id);
    }

    public function columnsOf(mixed $id, string $columns = '*'): array|false
    {
        return PayerRecords::columnsOf($id, $columns);
    }

    public function idByUuid(string $uuidBytes): mixed
    {
        return PayerRecords::idByUuid($uuidBytes);
    }

    public function newestActiveBySourceOfPayment(mixed $code): array
    {
        return PayerRecords::newestActiveBySourceOfPayment($code);
    }

    public function payerTypes(): array
    {
        return PayerRecords::payerTypes();
    }

    public function activeNamesById(): array
    {
        return PayerRecords::activeNamesById();
    }

    public function namesWithZipById(): array
    {
        return PayerRecords::namesWithZipById();
    }

    public function activeWithAddress(): array
    {
        return PayerRecords::activeWithAddress();
    }

    public function searchByNameOrIdPrefix(string $text): array
    {
        return PayerRecords::searchByNameOrIdPrefix($text);
    }

    public function searchForPractice(string $where): array
    {
        return PayerRecords::searchForPractice($where);
    }

    public function setClaimTemplate(mixed $id, mixed $templateJson): void
    {
        PayerRecords::setClaimTemplate($id, $templateJson);
    }

    public function searchRows(string $whereFragment, array $whereValues): array
    {
        return PayerStatements::searchRows($whereFragment, $whereValues);
    }

    public function allStatement(array $search, mixed $isAndCondition): \ADORecordSet
    {
        return PayerStatements::allStatement($search, $isAndCondition);
    }

    public function rowById(mixed $id): array|false
    {
        return PayerStatements::rowById($id);
    }

    public function insertPayer(array $binds): int
    {
        return PayerStatements::insertPayer($binds);
    }

    public function updatePayer(array $binds): true
    {
        return PayerStatements::updatePayer($binds);
    }
}
