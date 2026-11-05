<?php

/**
 * InsuranceCompanyService's statements over insurance_companies, moved here verbatim; the service keeps
 * its validation, addresses, phones and results and runs each through the payer contract. The
 * procedural sqlQuery() and sqlStatement() it called are the QueryUtils calls used here, which differ
 * only when the database fails (an exception where they printed an error and stopped).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Matthew Vita <matthewvita48@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2018 Matthew Vita <matthewvita48@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2024 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\Insurance\Payers;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\PhoneType;

final class PayerStatements
{
    private function __construct()
    {
    }

    /**
     * InsuranceCompanyService::search()'s statement: each company with its address, work and fax numbers and
     * X12 partner, under the search's WHERE fragment.
     *
     * @param array<mixed> $whereValues the fragment's bound values
     * @return list<array<mixed>>
     */
    public static function searchRows(string $whereFragment, array $whereValues): array
    {
        // the foreign_id here is a globally unique sequence so there is no conflict (see search()).
        $sql = <<<'SQL'
            SELECT i.id,
                i.uuid,
                i.name,
                i.attn,
                i.cms_id,
                i.ins_type_code,
                i.x12_receiver_id,
                i.x12_default_partner_id,
                x12.x12_default_partner_name,
                i.alt_cms_id,
                i.inactive,
                work_number.work_id,
                fax_number.fax_id,
                CONCAT(
                    COALESCE(work_number.country_code, ''),
                    COALESCE(work_number.area_code, ''),
                    COALESCE(work_number.prefix, ''),
                    work_number.number
                ) AS work_number,
                CONCAT(
                    COALESCE(fax_number.country_code, ''),
                    COALESCE(fax_number.area_code, ''),
                    COALESCE(fax_number.prefix, ''),
                    fax_number.number
                ) AS fax_number,
                a.line1,
                a.line2,
                a.city,
                a.state,
                a.zip,
                a.plus_four,
                a.country,
                i.date_created,
                i.last_updated
            FROM insurance_companies i
            LEFT JOIN (
                SELECT line1, line2, city, state, zip, plus_four, country, foreign_id
                FROM addresses
            ) a ON i.id = a.foreign_id
            LEFT JOIN (
                SELECT id AS work_id, foreign_id, country_code, area_code, prefix, number
                FROM phone_numbers
                WHERE number IS NOT NULL AND type = ?
            ) work_number ON i.id = work_number.foreign_id
            LEFT JOIN (
                SELECT id AS fax_id, foreign_id, country_code, area_code, prefix, number
                FROM phone_numbers
                WHERE number IS NOT NULL AND type = ?
            ) fax_number ON i.id = fax_number.foreign_id
            LEFT JOIN (
                SELECT id AS x12_default_partner_id, name AS x12_default_partner_name
                FROM x12_partners
            ) x12 ON i.x12_default_partner_id = x12.x12_default_partner_id
            SQL;
        $typeBindings = [PhoneType::WORK->value, PhoneType::FAX->value];
        $sql .= $whereFragment;
        $boundValues = array_merge($typeBindings, $whereValues);
        return QueryUtils::fetchRecords($sql, $boundValues);
    }

    /**
     * InsuranceCompanyService::getAll()'s statement: each company with its address, the search's fields
     * joined to the address condition as the method always has.
     *
     * @param array<mixed> $search column => value
     */
    public static function allStatement(array $search, mixed $isAndCondition): \ADORecordSet
    {
        $sqlBindArray = [];
        $sql = " SELECT i.id,";
        $sql .= "        i.uuid,";
        $sql .= "        i.name,";
        $sql .= "        i.attn,";
        $sql .= "        i.cms_id,";
        $sql .= "        i.ins_type_code,";
        $sql .= "        i.x12_receiver_id,";
        $sql .= "        i.x12_default_partner_id,";
        $sql .= "        i.alt_cms_id,";
        $sql .= "        i.inactive,";
        $sql .= "        a.line1,";
        $sql .= "        a.line2,";
        $sql .= "        a.city,";
        $sql .= "        a.state,";
        $sql .= "        a.zip,";
        $sql .= "        a.country";
        $sql .= " FROM insurance_companies i";
        $sql .= " LEFT JOIN addresses a ON i.id = a.foreign_id";

        if ($search !== []) {
            $sql .= ' AND ';
            $whereClauses = [];
            foreach ($search as $fieldName => $fieldValue) {
                array_push($whereClauses, $fieldName . ' = ?');
                array_push($sqlBindArray, $fieldValue);
            }
            $sqlCondition = ($isAndCondition == true) ? 'AND' : 'OR';
            $sql .= implode(' ' . $sqlCondition . ' ', $whereClauses);
        }

        return QueryUtils::sqlStatementThrowException($sql, $sqlBindArray);
    }

    /**
     * A company's row by id, uuid binary (InsuranceCompanyService::getOneById()).
     *
     * @return array<mixed>|false
     */
    public static function rowById(mixed $id): array|false
    {
        $sql = "SELECT * FROM insurance_companies WHERE id=?";
        return QueryUtils::querySingleRow($sql, [$id]);
    }

    /**
     * InsuranceCompanyService::insert()'s statement. The new row's id.
     *
     * @param array<mixed> $binds the columns' values in the statement's order
     */
    public static function insertPayer(array $binds): int
    {
        $sql = " INSERT INTO insurance_companies SET";
        $sql .= "     id=?,";
        $sql .= "     uuid=?,";
        $sql .= "     name=?,";
        $sql .= "     attn=?,";
        $sql .= "     cms_id=?,";
        $sql .= "     ins_type_code=?,";
        $sql .= "     x12_receiver_id=?,";
        $sql .= "     x12_default_partner_id=?,";
        $sql .= "     alt_cms_id=?,";
        $sql .= "     cqm_sop=?,";
        $sql .= "     inactive=?";

        return QueryUtils::sqlInsert($sql, $binds);
    }

    /**
     * InsuranceCompanyService::update()'s statement, keyed by id.
     * True once the statement has run; a failing statement throws. (ADOdb answers an UPDATE with its empty
     * recordset, which isn't an ADORecordSet; the callers only ever tested the answer for truth.)
     *
     * @param array<mixed> $binds the columns' values in the statement's order, then the id
     */
    public static function updatePayer(array $binds): true
    {
        $sql = " UPDATE insurance_companies SET";
        $sql .= "     name=?,";
        $sql .= "     attn=?,";
        $sql .= "     cms_id=?,";
        $sql .= "     ins_type_code=?,";
        $sql .= "     x12_receiver_id=?,";
        $sql .= "     x12_default_partner_id=?,";
        $sql .= "     alt_cms_id=?,";
        $sql .= "     cqm_sop=?";
        $sql .= "     WHERE id = ?";

        QueryUtils::sqlStatementThrowException($sql, $binds);
        return true;
    }
}
