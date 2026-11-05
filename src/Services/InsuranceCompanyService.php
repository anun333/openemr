<?php

/**
 * InsuranceCompanyService
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Matthew Vita <matthewvita48@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @copyright Copyright (c) 2018 Matthew Vita <matthewvita48@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2024 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Database\SqlQueryException;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\Address\AddressData;
use OpenEMR\Services\{
    AddressService,
    PhoneNumberService,
    Search\FhirSearchWhereClauseBuilder,
    Search\ISearchField,
    Search\SearchFieldException
};
use OpenEMR\Validators\InsuranceCompanyValidator;
use OpenEMR\Validators\ProcessingResult;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\BC\ServiceContainer;

class InsuranceCompanyService extends BaseService
{
    private const INSURANCE_TABLE = "insurance_companies";
    private readonly InsuranceCompanyValidator $insuranceCompanyValidator;
    private readonly AddressService $addressService;
    private $phoneNumberService = null;

    /**
     * @var null | array $cqm_sops cached CQM SOPS
     */
    private $cqm_sops = null;

    /**
     * @var null | array $types cached insurance types
     */
    private $types = null;

    /**
     * @var null | array $claim_types cached claim types
     */
    private $claim_types = null;


    /**
     * Default constructor.
     */
    public function __construct()
    {
        $this->addressService = new AddressService();
        $this->phoneNumberService = new PhoneNumberService();
        UuidRegistry::createMissingUuidsForTables([self::INSURANCE_TABLE]);
        $this->insuranceCompanyValidator = new InsuranceCompanyValidator();
        parent::__construct(self::INSURANCE_TABLE);
    }

    public function getInsuranceDisplayName($insuranceId): string
    {
        $searchResults = $this->search(['id' => $insuranceId]);
        $insuranceCompany = null;
        if ($searchResults->hasData()) {
            $insuranceCompany = $searchResults->getData()[0];
        }
        if ($insuranceCompany !== null) {
            return self::getDisplayNameForInsuranceRecord($insuranceCompany);
        }
        return "";
    }
    public static function getDisplayNameForInsuranceRecord($insuranceCompany): string
    {
        switch (OEGlobalsBag::getInstance()->get('insurance_information')) {
            case '1':
                $returnval = $insuranceCompany['name'] . " (" . $insuranceCompany['line1'] . ", " . $insuranceCompany['line2'] . ")";
                break;
            case '2':
                $returnval = $insuranceCompany['name'] . " (" . $insuranceCompany['line1'] . ", " . $insuranceCompany['line2'] . ", " . $insuranceCompany['zip'] . ")";
                break;
            case '3':
                $returnval = $insuranceCompany['name'] . " (" . $insuranceCompany['line1'] . ", " . $insuranceCompany['line2'] . ", " . $insuranceCompany['state'] . ")";
                break;
            case '4':
                $returnval = $insuranceCompany['name'] . " (" . $insuranceCompany['line1'] . ", " . $insuranceCompany['line2'] . ", " . $insuranceCompany['state'] .
                    ", " . $insuranceCompany['zip'] . ")";
                break;
            case '5':
                $returnval = $insuranceCompany['name'] . " (" . $insuranceCompany['line1'] . ", " . $insuranceCompany['line2'] . ", " . $insuranceCompany['city'] .
                    ", " . $insuranceCompany['state'] . ", " . $insuranceCompany['zip'] . ")";
                break;
            case '6':
                $returnval = $insuranceCompany['name'] . " (" . $insuranceCompany['line1'] . ", " . $insuranceCompany['line2'] . ", " . $insuranceCompany['city'] .
                    ", " . $insuranceCompany['state'] . ", " . $insuranceCompany['zip'] . ", " . $insuranceCompany['cms_id'] . ")";
                break;
            case '7':
                preg_match("/\d+/", (string) $insuranceCompany['line1'], $matches);
                $returnval = $insuranceCompany['name'] . " (" . $insuranceCompany['zip'] .
                    "," . $matches[0] . ")";
                break;
            case '0':
            default:
                $returnval = $insuranceCompany['name'];
                break;
        }
        return (string) $returnval;
    }

    public function getUuidFields(): array
    {
        return ['uuid'];
    }

    public function search(array $search, $isAndCondition = true): ProcessingResult
    {
        // the foreign_id here is a globally unique sequence so there is no conflict.
        // I don't like the assumption here as it should be more explicit what table we are pulling
        // from since OpenEMR mixes a bunch of paradigms.  I initially worried about data corruption as phone_numbers
        // foreign id could be ambiguous here... but since the sequence is globally unique @see \generate_id() we can
        // join here safely...
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

        $processingResult = new ProcessingResult();
        try {
            $whereFragment = FhirSearchWhereClauseBuilder::build($search, $isAndCondition);
            $sql .= $whereFragment->getFragment();
            $boundValues = array_merge($typeBindings, $whereFragment->getBoundValues());
            $records = QueryUtils::fetchRecords($sql, $boundValues);

            foreach ($records as $row) {
                $resultRecord = $this->createResultRecordFromDatabaseResult($row);
                $processingResult->addData($resultRecord);
            }
        } catch (SqlQueryException $exception) {
            // we shouldn't hit a query exception
            ServiceContainer::getLogger()->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            $processingResult->addInternalError("Error selecting data from database");
        } catch (SearchFieldException $exception) {
            ServiceContainer::getLogger()->error(
                $exception->getMessage(),
                ['trace' => $exception->getTraceAsString(),
                    'field' => $exception->getField()]
            );
            $processingResult->setValidationMessages([$exception->getField() => $exception->getMessage()]);
        }
        return $processingResult;
    }

    /**
     * @param array<string, string> $search
     */
    public function getAll(array $search = [], $isAndCondition = true)
    {
        // Validating and Converting UUID to ID
        if (isset($search['id'])) {
            $isValidcondition = $this->insuranceCompanyValidator->validateId(
                'uuid',
                self::INSURANCE_TABLE,
                $search['id'],
                true
            );
            if ($isValidcondition !== true) {
                return $isValidcondition;
            }
            $uuidBytes = UuidRegistry::uuidToBytes($search['id']);
            $search['id'] = $this->getIdByUuid($uuidBytes, self::INSURANCE_TABLE, "id");
        }

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

        $statementResults = sqlStatement($sql, $sqlBindArray);

        $processingResult = new ProcessingResult();
        while ($row = sqlFetchArray($statementResults)) {
            $row = $this->createResultRecordFromDatabaseResult($row);
            $processingResult->addData($row);
        }
        return $processingResult;
    }

    public function getOneById($id)
    {
        // TODO: this should be refactored to use getAll but its selecting all the columns and for backwards
        // compatibility we will leave this here.
        $sql = "SELECT * FROM insurance_companies WHERE id=?";
        $row = sqlQuery($sql, [$id]);
        // uuid is binary(16); hand it back as a string so the REST API can json_encode the row
        if (is_array($row) && is_string($row['uuid'] ?? null) && $row['uuid'] !== '') {
            $row['uuid'] = UuidRegistry::uuidToString($row['uuid']);
        }
        return $row;
    }

    public function getOne($uuid): ProcessingResult
    {
        return $this->getAll(['uuid' => $uuid]);
    }


    public function getInsuranceTypesCached()
    {
        if ($this->types !== null) {
            return $this->types;
        }
        $this->types = $this->getInsuranceTypes();
        return $this->types;
    }

    public function getInsuranceTypes(): array
    {
        $types = [];
        $type = sqlStatement("SELECT `type` FROM `insurance_type_codes`");
        $i = 0;
        while ($row = sqlFetchArray($type)) {
            $i++;
            $types[$i] = $row['type'];
        }
        return $types;
    }

    public function getInsuranceClaimTypesCached()
    {
        if ($this->claim_types !== null) {
            return $this->claim_types;
        }
        $this->claim_types = $this->getInsuranceClaimTypes();
        return $this->claim_types;
    }

    public function getInsuranceClaimTypes(): array
    {
        $claim_types = [];
        $claim_type = sqlStatement("SELECT `claim_type` FROM `insurance_type_codes`");
        $i = 0;
        while ($row = sqlFetchArray($claim_type)) {
            $i++;
            $claim_types[$i] = $row['claim_type'];
        }
        return $claim_types;
    }

    public function getInsuranceCqmSopCached()
    {
        if ($this->cqm_sops !== null) {
            return $this->cqm_sops;
        }
        $this->cqm_sops = $this->getInsuranceCqmSop();
        return $this->cqm_sops;
    }

    public function getInsuranceCqmSop(): array
    {
        $cqm_sop = sqlStatement(
            "SELECT distinct code, description FROM `valueset` WHERE `valueset` = '2.16.840.1.114222.4.11.3591';"
        );

        $cqm_sops = [];
        while ($row = sqlFetchArray($cqm_sop)) {
            $cqm_sops[$row['code']] = $row['description'];
        }
        return $cqm_sops;
    }

    /**
     * Map the posted insurance company search/add form ($_POST style keys) to the
     * canonical data array consumed by insert() and update().
     *
     * Pure: no database access and no superglobals, so the field mapping and the
     * "Save as New" vs. update branch can be tested in isolation.
     *
     * @param array<string, mixed> $form Raw posted form values (form_* keys).
     * @return array<string, mixed>
     */
    public function buildSaveDataFromForm(array $form): array
    {
        $isNew = (($form['form_save'] ?? '') === 'Save as New' || ($form['form_id'] ?? null) === null || $form['form_id'] === '');
        $foreignId = $isNew ? '' : $form['form_id'];

        return [
            'name' => $form['form_name'] ?? '',
            'attn' => $form['form_attn'] ?? '',
            'cms_id' => $form['form_cms_id'] ?? '',
            'ins_type_code' => $form['form_ins_type_code'] ?? '',
            'x12_receiver_id' => $form['form_x12_receiver'] ?? null,
            'x12_default_partner_id' => $form['form_partner'] ?? '',
            'alt_cms_id' => null,
            'line1' => $form['form_addr1'] ?? '',
            'line2' => $form['form_addr2'] ?? '',
            'city' => $form['form_city'] ?? '',
            'state' => $form['form_state'] ?? '',
            'zip' => $form['form_zip'] ?? '',
            'country' => $form['form_country'] ?? '',
            'phone' => $form['form_phone'] ?? '',
            'foreign_id' => $foreignId,
            'cqm_sop' => $form['form_cqm_sop'] ?? '',
        ];
    }

    /**
     * Persist the insurance company search/add form. Inserts a new company when
     * "Save as New" was used (or no id is present), otherwise updates the existing
     * company identified by form_id. Returns the resulting id and the display name
     * the opener should show.
     *
     * @param array<string, mixed> $form
     * @return array{id: int, name: string}
     * @throws \RuntimeException When the underlying insert/update fails or the
     *     resulting id is not a valid integer.
     */
    public function saveFromForm(array $form): array
    {
        $isNew = (
            ($form['form_save'] ?? '') === 'Save as New'
            || ($form['form_id'] ?? null) === null
            || $form['form_id'] === ''
        );

        $data = $this->buildSaveDataFromForm($form);

        if ($isNew) {
            $id = $this->insert($data);
        } else {
            $id = $form['form_id'];
            $result = $this->update($data, $id);
            if ($result === false) {
                throw new \RuntimeException(xl('Failed to update insurance company'));
            }
        }

        $id = filter_var($id, FILTER_VALIDATE_INT);

        if ($id === false) {
            throw new \RuntimeException(xl('Invalid insurance company id'));
        }

        return [
            'id' => $id,
            'name' => $this->getInsuranceDisplayName($id),
        ];
    }

    /**
     * Validates insurance company data for a database insert or update.
     *
     * @param array<string, mixed> $insuranceCompanyData
     * @param string $context one of InsuranceCompanyValidator::DATABASE_INSERT_CONTEXT
     *                        or InsuranceCompanyValidator::DATABASE_UPDATE_CONTEXT
     */
    public function validate(array $insuranceCompanyData, string $context = InsuranceCompanyValidator::DATABASE_INSERT_CONTEXT): ProcessingResult
    {
        return $this->insuranceCompanyValidator->validate($insuranceCompanyData, $context);
    }

    public function insert($data): int|string
    {
        // insurance companies need to use sequences table since they share the
        // addresses table with pharmacies
        // I don't like actually inserting a raw id... yet if we don't allow for this
        // it makes it very hard for any kind of data import that needs to maintain the same id.
        if (!isset($data["id"]) || $data["id"] === '') {
            $data["id"] = QueryUtils::generateId();
        }
        $freshId = $data['id'];
        if (!is_int($freshId) && !is_string($freshId)) {
            throw new \RuntimeException(xl('Invalid insurance company id'));
        }

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

        // throws an exception if the record doesn't insert
        QueryUtils::sqlInsert(
            $sql,
            [
                $freshId,
                UuidRegistry::getRegistryForTable(self::INSURANCE_TABLE)->createUuid(),
                $data["name"],
                $data["attn"],
                $data["cms_id"],
                $data["ins_type_code"],
                $data["x12_receiver_id"],
                $data["x12_default_partner_id"] ?? '',
                $data["alt_cms_id"],
                $data["cqm_sop"] ?? null,
                is_array($data) ? ($data["inactive"] ?? 0) : 0,
            ]
        );

        if (($data["city"] ?? '') !== '' && ($data["state"] ?? '') !== '') {
            $this->addressService->insert(AddressData::fromArray($data), (int) $freshId);
        }

        if (($data["phone"] ?? '') !== '') {
            $this->phoneNumberService->insert($data, $freshId);
        }

        return $freshId;
    }

    public function update($data, $iid)
    {
        // Overlay the submitted fields onto the stored row so a partial payload
        // (e.g. {"name": "foo"}) narrows to a semantic PATCH on the named
        // columns rather than wiping every omitted column to NULL. Clients can
        // still clear a column by submitting an explicit null for its key.
        $existing = $this->getOneById($iid);
        if (!is_array($existing) || !is_array($data)) {
            return false;
        }
        /** @var array<string, mixed> $data */
        $data = array_merge($existing, $data);

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

        $insuranceResults = sqlStatement(
            $sql,
            [
                $data["name"],
                $data["attn"],
                $data["cms_id"],
                $data["ins_type_code"],
                $data["x12_receiver_id"],
                $data["x12_default_partner_id"] ?? null,
                $data["alt_cms_id"],
                $data["cqm_sop"] ?? null,
                $iid
            ]
        );

        if (!$insuranceResults) {
            return false;
        }

        // Only touch the stored address when the submitted payload actually
        // carries at least one address field. Merge submitted fields over the
        // stored address so a payload submitting only `line1` (with no
        // city/state) still gets applied without wiping unrelated fields,
        // and a payload submitting only `city`+`state` doesn't clobber the
        // stored `line1` with an empty string. If no addresses row exists
        // yet for this company (insert() skips address when city/state are
        // empty), fall through to an insert so the submitted address is
        // actually saved.
        if (self::payloadCarriesAddressFields($data)) {
            if (!is_numeric($iid)) {
                return false;
            }
            $iidInt = (int) $iid;
            $stored = $this->addressService->getOneByForeignId($iidInt);
            $storedFields = $stored?->toArray() ?? [];
            $submittedFields = array_intersect_key(
                $data,
                array_flip(['line1', 'line2', 'city', 'state', 'zip', 'country', 'plus_four'])
            );
            $addressData = AddressData::fromArray(array_merge($storedFields, $submittedFields));
            $addressesResults = $this->addressService->update($addressData, $iidInt);
            $addressesResults ??= $this->addressService->insert($addressData, $iidInt);
            if (!$addressesResults) {
                return false;
            }
        }

        // no point in updating the phone if there is no phone record...
        if (($data['phone'] ?? '') !== '') {
            $phoneNumberResults = $this->phoneNumberService->update($data, $iid);

            if (!$phoneNumberResults) {
                return false;
            }
        }

        return $iid;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function payloadCarriesAddressFields(array $data): bool
    {
        foreach (['line1', 'line2', 'city', 'state', 'zip', 'country', 'plus_four'] as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }
        return false;
    }

    // ---- Payer reads: the one door for company data -----------------------------------------------
    //
    // What callers outside the insurance services ask about a company (a payer), each the query they
    // ran before the boundary branch, moved here unchanged. Static: a read needs none of the
    // constructor's setup, which backfills missing uuids (a write). No permission checks: each caller
    // keeps its own. A $columns or $where argument is SQL over the aliases named; callers pass literals
    // or values they have already escaped, as before.

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

    /**
     * Return an array of insurance companies with the same payer id
     *
     * @param  $cms_id  Insurance company payer id (assigned by clearinghouses)
     * @return Array Insurance company data payload.
     */
    public function getAllByPayerID($cms_id)
    {
        $insuranceCompanyResult = $this->search(['cms_id' => $cms_id]);
        return $insuranceCompanyResult->hasData()
            ? $insuranceCompanyResult->getData()
            : [];
    }
}
