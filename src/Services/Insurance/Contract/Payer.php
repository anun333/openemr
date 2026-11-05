<?php

/**
 * Payer: every question core asks about an insurance company, a payer (insurance_companies).
 *
 * The insurance module implements it, and core resolves it through
 * OpenEMR\BC\ServiceContainer::getInsurancePayer() without naming the module's classes. Each
 * method is the query a caller ran before the boundary branch, moved unchanged; the sections
 * follow the module's classes, from which this file is generated (signatures and docblocks).
 * No permission checks: each caller keeps its own.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\Insurance\Contract;

interface Payer
{
    // ---- as PayerRecords (the insurance module) ----------------------------------------------------------

    /**
     * A company's stored name, or null when no company has the id.
     */
    public function nameOf(mixed $id): mixed;

    /**
     * Companies' stored names by id, for the ids that exist (a list's payer
     * column, where its query joined the company for the name).
     *
     * @param array<mixed> $ids
     * @return array<int|string, mixed>
     */
    public function namesOf(array $ids): array;

    /**
     * A name from namesOf()'s answer by a row's payer id (null for no id, or an
     * id with no company), as the LEFT JOIN it replaces answered.
     *
     * @param array<int|string, mixed> $names
     */
    public function nameFrom(array $names, mixed $id): mixed;

    /**
     * A company's row, or the columns named (over insurance_companies), as
     * stored: the uuid stays binary. As sqlQuery() answered: an array, or
     * false when no company has the id.
     *
     * @return array<mixed>|false
     */
    public function columnsOf(mixed $id, string $columns = '*'): array|false;

    /**
     * A company's id by its uuid (bytes), or null.
     */
    public function idByUuid(string $uuidBytes): mixed;

    /**
     * The newest active company with a Source of Payment Typology code: its id
     * and uuid, as a one-row list or an empty one (the QRDA import).
     *
     * @return list<array<mixed>>
     */
    public function newestActiveBySourceOfPayment(mixed $code): array;

    /**
     * Every company's name, Source of Payment code and insurance type code, by
     * name (our modules' payer type settings and maps).
     *
     * @return list<array<mixed>>
     */
    public function payerTypes(): array;

    /**
     * Active companies' names by id, by name then id (the procedural
     * getInsuranceProviders()).
     *
     * @return array<int|string, mixed>
     */
    public function activeNamesById(): array;

    /**
     * Every company with an address, its name followed by the zip and the
     * first number of the first line, by name then zip. Kept for the display
     * getInsuranceProviders() offers but doesn't use ("Please leave this
     * here", Rod Roark: plans with different billing addresses).
     *
     * @return array<int|string, string>
     */
    public function namesWithZipById(): array;

    /**
     * Active companies with an address: name, id, payer id and address, by
     * name then zip (the procedural getInsuranceProvidersExtra()).
     *
     * @return list<array<mixed>>
     */
    public function activeWithAddress(): array;

    /**
     * Companies whose name or id starts with the text, with the address's city,
     * state and country, by name (the payment screens' insurer picker).
     *
     * @return list<array<mixed>>
     */
    public function searchByNameOrIdPrefix(string $text): array;

    /**
     * Companies with their address and phone, filtered by $where (conditions
     * over i, a and p, already escaped, each starting with AND), by name then
     * zip (Practice's insurance company search).
     *
     * @return list<array<mixed>>
     */
    public function searchForPractice(string $where): array;

    /**
     * Store a company's UB-04 claim template (JSON).
     */
    public function setClaimTemplate(mixed $id, mixed $templateJson): void;

    // ---- as PayerStatements (the insurance module) -------------------------------------------------------

    /**
     * InsuranceCompanyService::search()'s statement: each company with its address, work and fax numbers and
     * X12 partner, under the search's WHERE fragment.
     *
     * @param array<mixed> $whereValues the fragment's bound values
     * @return list<array<mixed>>
     */
    public function searchRows(string $whereFragment, array $whereValues): array;

    /**
     * InsuranceCompanyService::getAll()'s statement: each company with its address, the search's fields
     * joined to the address condition as the method always has.
     *
     * @param array<mixed> $search column => value
     */
    public function allStatement(array $search, mixed $isAndCondition): \ADORecordSet;

    /**
     * A company's row by id, uuid binary (InsuranceCompanyService::getOneById()).
     *
     * @return array<mixed>|false
     */
    public function rowById(mixed $id): array|false;

    /**
     * InsuranceCompanyService::insert()'s statement. The new row's id.
     *
     * @param array<mixed> $binds the columns' values in the statement's order
     */
    public function insertPayer(array $binds): int;

    /**
     * InsuranceCompanyService::update()'s statement, keyed by id.
     * True once the statement has run; a failing statement throws. (ADOdb answers an UPDATE with its empty
     * recordset, which isn't an ADORecordSet; the callers only ever tested the answer for truth.)
     *
     * @param array<mixed> $binds the columns' values in the statement's order, then the id
     */
    public function updatePayer(array $binds): true;
}
