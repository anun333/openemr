<?php

/**
 * Payer reads: the one door for company data. What callers outside the insurance services ask about a
 * company (a payer), each the query they ran before the boundary branch, moved here unchanged from
 * InsuranceCompanyService (which keeps the names and delegates through the payer contract). Static: a
 * read needs none of InsuranceCompanyService's setup, which backfills missing uuids (a write). No
 * permission checks: each caller keeps its own. A $columns or $where argument is SQL over the aliases
 * named; callers pass literals or values they have already escaped, as before.
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

final class PayerRecords
{
    private function __construct()
    {
    }

    /**
     * A company's stored name, or null when no company has the id.
     */
    public static function nameOf(mixed $id): mixed
    {
        $row = QueryUtils::querySingleRow("SELECT name FROM insurance_companies WHERE id = ?", [$id]);
        return is_array($row) ? $row['name'] : null;
    }

    /**
     * Companies' stored names by id, for the ids that exist (a list's payer
     * column, where its query joined the company for the name).
     *
     * @param array<mixed> $ids
     * @return array<int|string, mixed>
     */
    public static function namesOf(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn($id): bool => is_int($id) || (is_string($id) && $id !== ''))));
        $names = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = QueryUtils::fetchRecords(
                "SELECT id, name FROM insurance_companies WHERE id IN (" . implode(',', array_fill(0, count($chunk), '?')) . ")",
                $chunk
            );
            foreach ($rows as $row) {
                if (is_int($row['id']) || is_string($row['id'])) {
                    $names[$row['id']] = $row['name'];
                }
            }
        }
        return $names;
    }

    /**
     * A name from namesOf()'s answer by a row's payer id (null for no id, or an
     * id with no company), as the LEFT JOIN it replaces answered.
     *
     * @param array<int|string, mixed> $names
     */
    public static function nameFrom(array $names, mixed $id): mixed
    {
        return is_int($id) || is_string($id) ? ($names[$id] ?? null) : null;
    }

    /**
     * A company's row, or the columns named (over insurance_companies), as
     * stored: the uuid stays binary. As sqlQuery() answered: an array, or
     * false when no company has the id.
     *
     * @return array<mixed>|false
     */
    public static function columnsOf(mixed $id, string $columns = '*'): array|false
    {
        return QueryUtils::querySingleRow("SELECT $columns FROM insurance_companies WHERE id = ?", [$id]);
    }

    /**
     * A company's id by its uuid (bytes), or null.
     */
    public static function idByUuid(string $uuidBytes): mixed
    {
        return QueryUtils::fetchSingleValue("SELECT id FROM insurance_companies WHERE uuid = ?", 'id', [$uuidBytes]);
    }

    /**
     * The newest active company with a Source of Payment Typology code: its id
     * and uuid, as a one-row list or an empty one (the QRDA import).
     *
     * @return list<array<mixed>>
     */
    public static function newestActiveBySourceOfPayment(mixed $code): array
    {
        return QueryUtils::fetchRecords("SELECT `id`, `uuid` FROM `insurance_companies` WHERE `cqm_sop` = ? AND `inactive` = 0 ORDER BY `id` DESC LIMIT 1", [$code]);
    }

    /**
     * Every company's name, Source of Payment code and insurance type code, by
     * name (our modules' payer type settings and maps).
     *
     * @return list<array<mixed>>
     */
    public static function payerTypes(): array
    {
        return QueryUtils::fetchRecords("SELECT id, name, cqm_sop, ins_type_code FROM insurance_companies ORDER BY name");
    }

    /**
     * Active companies' names by id, by name then id (the procedural
     * getInsuranceProviders()).
     *
     * @return array<int|string, mixed>
     */
    public static function activeNamesById(): array
    {
        $returnval = [];
        foreach (QueryUtils::fetchRecords("select name, id from insurance_companies where inactive != 1 order by name, id") as $row) {
            if (is_int($row['id']) || is_string($row['id'])) {
                $returnval[$row['id']] = $row['name'];
            }
        }
        return $returnval;
    }

    /**
     * Every company with an address, its name followed by the zip and the
     * first number of the first line, by name then zip. Kept for the display
     * getInsuranceProviders() offers but doesn't use ("Please leave this
     * here", Rod Roark: plans with different billing addresses).
     *
     * @return array<int|string, string>
     */
    public static function namesWithZipById(): array
    {
        $returnval = [];
        $sql = "select insurance_companies.name, insurance_companies.id, " .
          "addresses.zip, addresses.line1 " .
          "from insurance_companies, addresses " .
          "where addresses.foreign_id = insurance_companies.id " .
          "order by insurance_companies.name, addresses.zip";
        foreach (QueryUtils::fetchRecords($sql) as $row) {
            preg_match("/\d+/", is_string($row['line1']) ? $row['line1'] : '', $matches);
            if (is_int($row['id']) || is_string($row['id'])) {
                $returnval[$row['id']] = (is_string($row['name']) ? $row['name'] : '') . " (" . (is_string($row['zip']) ? $row['zip'] : '') .
                  "," . ($matches[0] ?? '') . ")";
            }
        }
        return $returnval;
    }

    /**
     * Active companies with an address: name, id, payer id and address, by
     * name then zip (the procedural getInsuranceProvidersExtra()).
     *
     * @return list<array<mixed>>
     */
    public static function activeWithAddress(): array
    {
        $sql = "SELECT insurance_companies.name, insurance_companies.id, insurance_companies.cms_id,
            addresses.line1, addresses.line2, addresses.city, addresses.state, addresses.zip
            FROM insurance_companies, addresses
            WHERE addresses.foreign_id = insurance_companies.id
            AND insurance_companies.inactive != 1
            ORDER BY insurance_companies.name, addresses.zip";
        return QueryUtils::fetchRecords($sql);
    }

    /**
     * Companies whose name or id starts with the text, with the address's city,
     * state and country, by name (the payment screens' insurer picker).
     *
     * @return list<array<mixed>>
     */
    public static function searchByNameOrIdPrefix(string $text): array
    {
        return QueryUtils::fetchRecords("SELECT insurance_companies.id,name,city,state,country FROM insurance_companies
			left join addresses on insurance_companies.id=addresses.foreign_id  where name like ? or  insurance_companies.id like ? ORDER BY name", [$text . '%', $text . '%']);
    }

    /**
     * Companies with their address and phone, filtered by $where (conditions
     * over i, a and p, already escaped, each starting with AND), by name then
     * zip (Practice's insurance company search).
     *
     * @return list<array<mixed>>
     */
    public static function searchForPractice(string $where): array
    {
        $query = "SELECT " .
            "i.id, i.name, i.attn, " .
            "a.line1, a.line2, a.city, a.state, a.zip, " .
            "p.area_code, p.prefix, p.number " .
            "FROM insurance_companies i " .
            "LEFT JOIN addresses a ON a.foreign_id = i.id " .
            "LEFT JOIN phone_numbers p ON p.foreign_id = i.id WHERE 1=1 ";
        $query .= $where . " ORDER BY i.name, a.zip";
        return QueryUtils::fetchRecords($query);
    }

    /**
     * Store a company's UB-04 claim template (JSON).
     */
    public static function setClaimTemplate(mixed $id, mixed $templateJson): void
    {
        QueryUtils::sqlStatementThrowException("update insurance_companies set claim_template = ? where id = ?", [
            $templateJson,
            $id
        ]);
    }
}
