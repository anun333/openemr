<?php

/**
 * ServiceRequest resources for referrals, read from the Transactions screen's referral
 * form (LBTref). FhirServiceRequestService serves orders from procedure_order and
 * routes the referral category here (D8 of #13266).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Services\FHIR\ServiceRequest;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\FHIR\R4\FHIRDomainResource\FHIRProvenance;
use OpenEMR\FHIR\R4\FHIRDomainResource\FHIRServiceRequest;
use OpenEMR\FHIR\R4\FHIRElement\FHIRAnnotation;
use OpenEMR\FHIR\R4\FHIRElement\FHIRCanonical;
use OpenEMR\FHIR\R4\FHIRElement\FHIRCodeableConcept;
use OpenEMR\FHIR\R4\FHIRElement\FHIRDateTime;
use OpenEMR\FHIR\R4\FHIRElement\FHIRId;
use OpenEMR\FHIR\R4\FHIRElement\FHIRMarkdown;
use OpenEMR\FHIR\R4\FHIRElement\FHIRReference;
use OpenEMR\FHIR\R4\FHIRElement\FHIRRequestIntent;
use OpenEMR\FHIR\R4\FHIRElement\FHIRRequestPriority;
use OpenEMR\FHIR\R4\FHIRElement\FHIRRequestStatus;
use OpenEMR\FHIR\R4\FHIRElement\FHIRString;
use OpenEMR\FHIR\R4\FHIRResource\FHIRDomainResource;
use OpenEMR\Services\CodeTypesService;
use OpenEMR\Services\FHIR\FhirCodeSystemConstants;
use OpenEMR\Services\FHIR\FhirProvenanceService;
use OpenEMR\Services\FHIR\FhirServiceBase;
use OpenEMR\Services\FHIR\IPatientCompartmentResourceService;
use OpenEMR\Services\FHIR\Traits\PatientSearchTrait;
use OpenEMR\Services\FHIR\UtilsService;
use OpenEMR\Services\ReferralService;
use OpenEMR\Services\Search\FhirSearchParameterDefinition;
use OpenEMR\Services\Search\ISearchField;
use OpenEMR\Services\Search\SearchFieldType;
use OpenEMR\Services\Search\ServiceField;
use OpenEMR\Services\Search\TokenSearchField;
use OpenEMR\Services\Search\TokenSearchValue;
use OpenEMR\Validators\ProcessingResult;

class FhirServiceRequestReferralService extends FhirServiceBase implements IPatientCompartmentResourceService
{
    use PatientSearchTrait;

    /**
     * SNOMED CT 3457005 "Patient referral (procedure)". It is the category, and the code
     * when the referral names no requested service. US Core 6.1 permits a category code
     * outside its ServiceRequest category value set, and 3457005 is a procedure code, so
     * within US Core Procedure Codes for ServiceRequest.code.
     */
    public const CATEGORY_REFERRAL = '3457005';
    public const CATEGORY_REFERRAL_DISPLAY = 'Patient referral';

    private const PROFILE = 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-servicerequest';

    private const US_CORE_CATEGORY_SYSTEM = 'http://hl7.org/fhir/us/core/CodeSystem/us-core-category';

    private const SDOHCC_SYSTEM = 'http://hl7.org/fhir/us/sdoh-clinicalcare/CodeSystem/SDOHCC-CodeSystemTemporaryCodes';

    /**
     * The Referral Category list's SDOH domains, by option id, as SDOH Clinical Care
     * categories: the same domains and codes HistorySdohService gives the SDOH assessment.
     */
    private const SDOH_DOMAINS = [
        'food_insecurity' => ['food-insecurity', 'Food Insecurity'],
        'housing_instability' => ['housing-instability', 'Housing Instability'],
        'transportation_insecurity' => ['transportation-insecurity', 'Transportation Insecurity'],
        'utilities_insecurity' => ['utility-insecurity', 'Utility Insecurity'],
        'financial_strain' => ['financial-insecurity', 'Financial Insecurity'],
        'social_isolation' => ['social-connection', 'Social Connection'],
        'childcare_needs' => ['material-hardship', 'Material Hardship'],
        'digital_access' => ['digital-access', 'Digital Access'],
        'interpersonal_safety' => ['intimate-partner-violence', 'Intimate Partner Violence'],
    ];

    private ?ReferralService $referralService = null;

    private function getReferralService(): ReferralService
    {
        return $this->referralService ??= new ReferralService();
    }

    public function setReferralService(ReferralService $referralService): void
    {
        $this->referralService = $referralService;
    }

    /**
     * The same search parameters the order side supports, so no filter is silently
     * ignored when a search reaches both.
     */
    protected function loadSearchParameters(): array
    {
        return [
            'patient' => $this->getPatientContextSearchField(),
            '_id' => new FhirSearchParameterDefinition('_id', SearchFieldType::TOKEN, [new ServiceField('uuid', ServiceField::TYPE_UUID)]),
            'category' => new FhirSearchParameterDefinition('category', SearchFieldType::TOKEN, ['category']),
            'code' => new FhirSearchParameterDefinition('code', SearchFieldType::TOKEN, ['code']),
            'authored' => new FhirSearchParameterDefinition('authored', SearchFieldType::DATETIME, ['authored']),
            'status' => new FhirSearchParameterDefinition('status', SearchFieldType::TOKEN, ['status']),
            'intent' => new FhirSearchParameterDefinition('intent', SearchFieldType::TOKEN, ['intent']),
            '_lastUpdated' => $this->getLastModifiedSearchField(),
        ];
    }

    public function getLastModifiedSearchField(): FhirSearchParameterDefinition
    {
        return new FhirSearchParameterDefinition('_lastUpdated', SearchFieldType::DATETIME, ['last_modified']);
    }

    /**
     * The category is always the referral one, so a category search either keeps every
     * referral or none. The code can be any of a referral's requested services, kept as
     * "TYPE:CODE" text, so it is matched after mapping, against each coding's system and code.
     *
     * @param array<mixed> $openEMRSearchParameters
     */
    protected function searchForOpenEMRRecords($openEMRSearchParameters): ProcessingResult
    {
        $category = $openEMRSearchParameters['category'] ?? null;
        $code = $openEMRSearchParameters['code'] ?? null;
        $fields = [];
        foreach ($openEMRSearchParameters as $name => $field) {
            if (is_string($name) && $name !== 'category' && $name !== 'code' && $field instanceof ISearchField) {
                $fields[$name] = $field;
            }
        }

        $result = $this->getReferralService()->search($fields);
        if (!$result->isValid() || (!$code instanceof TokenSearchField && !$category instanceof TokenSearchField)) {
            return $result;
        }
        $filtered = new ProcessingResult();
        $records = $result->getData();
        foreach (is_array($records) ? $records : [] as $record) {
            if (!is_array($record)) {
                continue;
            }
            $categoryCodings = [];
            foreach (self::categories($record) as $concept) {
                array_push($categoryCodings, ...self::codings($concept));
            }
            if (
                (!$code instanceof TokenSearchField || self::anyTokenMatches($code, self::codings(self::requestedService($record))))
                && (!$category instanceof TokenSearchField || self::anyTokenMatches($category, $categoryCodings))
            ) {
                $filtered->addData($record);
            }
        }
        return $filtered;
    }

    /**
     * A ReferralService::search() record as a US Core ServiceRequest.
     *
     * @param array<mixed> $dataRecord
     */
    public function parseOpenEMRRecord($dataRecord = [], $encode = false): FHIRServiceRequest
    {
        $record = $dataRecord;
        $serviceRequest = new FHIRServiceRequest();
        $lastModified = self::text($record, 'last_modified');
        $meta = UtilsService::createFhirMeta('1', $lastModified === null ? UtilsService::getDateFormattedAsUTC() : self::utc($lastModified));
        $meta->addProfile(new FHIRCanonical(self::PROFILE));
        $serviceRequest->setMeta($meta);

        $id = new FHIRId();
        $id->setValue(self::text($record, 'uuid') ?? '');
        $serviceRequest->setId($id);
        $serviceRequest->setStatus(new FHIRRequestStatus(['value' => self::text($record, 'status') ?? 'active']));
        $serviceRequest->setIntent(new FHIRRequestIntent(['value' => 'order']));
        $priority = ReferralService::priorityForRisk($record['refer_risk_level'] ?? null);
        if ($priority !== null) {
            $serviceRequest->setPriority(new FHIRRequestPriority(['value' => $priority]));
        }
        foreach (self::categories($record) as $category) {
            $serviceRequest->addCategory($category);
        }
        $serviceRequest->setCode(self::requestedService($record));

        $puuid = self::text($record, 'puuid');
        if ($puuid !== null) {
            $serviceRequest->setSubject(UtilsService::createRelativeReference('Patient', $puuid));
        }
        // The referral date is a date, which FHIR's dateTime allows as it is; a time would
        // only be invented by converting midnight to UTC.
        $authored = self::text($record, 'authored');
        if ($authored !== null) {
            $serviceRequest->setAuthoredOn(new FHIRDateTime(preg_match('/^\d{4}-\d{2}-\d{2}$/D', $authored) === 1 ? $authored : self::utc($authored)));
        }
        $requester = self::person($record, 'requester');
        if ($requester !== null) {
            $serviceRequest->setRequester($requester);
        }
        $performer = self::person($record, 'performer');
        if ($performer !== null) {
            $serviceRequest->addPerformer($performer);
        }
        foreach (self::reasons(self::text($record, 'refer_diag')) as $reason) {
            $serviceRequest->addReasonCode($reason);
        }
        $patientId = $record['patient_id'] ?? null;
        if (is_numeric($patientId)) {
            foreach (self::problems((int)$patientId, self::text($record, 'refer_diag')) as $problem) {
                $serviceRequest->addReasonReference($problem);
            }
        }
        $body = self::text($record, 'body');
        if ($body !== null) {
            $note = new FHIRAnnotation();
            $note->setText(new FHIRMarkdown($body));
            $serviceRequest->addNote($note);
        }
        return $serviceRequest;
    }

    /**
     * @return string[]
     */
    public function getProfileURIs(): array
    {
        return [self::PROFILE];
    }

    /**
     * Referrals are read-only here: a POST or PUT goes to the order service, so the
     * three write methods are never reached.
     *
     * @return array<mixed>
     */
    public function parseFhirResource(FHIRDomainResource $fhirResource): array
    {
        throw new \BadMethodCallException('Referral ServiceRequests are read-only');
    }

    /**
     * @param mixed $openEmrRecord
     */
    protected function insertOpenEMRRecord($openEmrRecord): ProcessingResult
    {
        throw new \BadMethodCallException('Referral ServiceRequests are read-only');
    }

    /**
     * @param mixed $fhirResourceId
     * @param mixed $updatedOpenEMRRecord
     */
    protected function updateOpenEMRRecord($fhirResourceId, $updatedOpenEMRRecord): ProcessingResult
    {
        throw new \BadMethodCallException('Referral ServiceRequests are read-only');
    }

    /**
     * As for orders, with the requester as author when it is a Practitioner reference;
     * otherwise the organization is.
     *
     * @param mixed $dataRecord
     */
    public function createProvenanceResource($dataRecord, $encode = false): FHIRProvenance|string|null
    {
        if (!($dataRecord instanceof FHIRServiceRequest)) {
            throw new \BadMethodCallException('Data record should be correct instance class');
        }
        $requester = $dataRecord->getRequester();
        $author = self::isReference($requester) ? $requester : null;
        $provenance = (new FhirProvenanceService())->createProvenanceForDomainResource($dataRecord, $author);
        if (!($provenance instanceof FHIRProvenance)) {
            return null;
        }
        if ($encode) {
            $json = json_encode($provenance);
            return is_string($json) ? $json : null;
        }
        return $provenance;
    }

    private static function referralConcept(): FHIRCodeableConcept
    {
        return UtilsService::createCodeableConcept([
            self::CATEGORY_REFERRAL => ['code' => self::CATEGORY_REFERRAL, 'description' => self::CATEGORY_REFERRAL_DISPLAY, 'system' => FhirCodeSystemConstants::SNOMED_CT],
        ]);
    }

    /**
     * The referral category (3457005), and the referral's Referral Category when it has one.
     * An SDOH domain adds US Core's sdoh category and the domain's SDOH Clinical Care code;
     * any other option gives its title, with the codes the option names in the list.
     *
     * @param array<mixed> $record
     * @return list<FHIRCodeableConcept>
     */
    private static function categories(array $record): array
    {
        $categories = [self::referralConcept()];
        $option = self::text($record, 'refer_category');
        if ($option === null) {
            return $categories;
        }
        $domain = self::SDOH_DOMAINS[$option] ?? null;
        if ($domain !== null) {
            $categories[] = UtilsService::createCodeableConcept(['sdoh' => ['code' => 'sdoh', 'description' => 'SDOH', 'system' => self::US_CORE_CATEGORY_SYSTEM]]);
            $concept = UtilsService::createCodeableConcept([$domain[0] => ['code' => $domain[0], 'description' => $domain[1], 'system' => self::SDOHCC_SYSTEM]]);
        } else {
            $codes = array_filter(
                (new CodeTypesService())->parseCodesIntoCodeableConcepts(self::text($record, 'category_codes') ?? ''),
                static fn(mixed $code): bool => is_array($code) && is_string($code['system'] ?? null) && $code['system'] !== ''
            );
            $concept = UtilsService::createCodeableConcept($codes);
        }
        $concept->setText(new FHIRString(self::text($record, 'category_title') ?? $option));
        $categories[] = $concept;
        return $categories;
    }

    /**
     * Referrer Diagnosis as reason codes. Each coded entry (a code type whose system OpenEMR
     * knows, such as ICD10:I10) is a coding. Anything else is the reason's text: on a stock
     * install the field is free text, and "chest pain" is not a code.
     *
     * @return list<FHIRCodeableConcept>
     */
    private static function reasons(?string $diagnosis): array
    {
        $reasons = [];
        $text = [];
        $codeTypes = new CodeTypesService();
        foreach (explode(';', $diagnosis ?? '') as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $parsed = str_contains($entry, ':') ? $codeTypes->parseCodesIntoCodeableConcepts($entry) : [];
            $code = reset($parsed);
            if (is_array($code) && is_string($code['system'] ?? null) && $code['system'] !== '') {
                $reasons[] = UtilsService::createCodeableConcept($parsed);
            } else {
                $text[] = $entry;
            }
        }
        if ($text !== []) {
            $concept = new FHIRCodeableConcept();
            $concept->setText(new FHIRString(implode('; ', $text)));
            $reasons[] = $concept;
        }
        return $reasons;
    }

    /**
     * The patient's active problems (problem list items, served as Conditions) that carry one
     * of the Referrer Diagnosis codes, as references.
     *
     * @return list<FHIRReference>
     */
    private static function problems(int $patientId, ?string $diagnosis): array
    {
        $references = [];
        $seen = [];
        foreach (explode(';', $diagnosis ?? '') as $entry) {
            $entry = trim($entry);
            if (!str_contains($entry, ':')) {
                continue;
            }
            $rows = QueryUtils::fetchRecords(
                "SELECT id, uuid, title FROM lists WHERE pid = ? AND type = 'medical_problem' AND activity = 1"
                    . " AND CONCAT(';', REPLACE(diagnosis, ' ', ''), ';') LIKE ?",
                [$patientId, '%;' . str_replace(' ', '', $entry) . ';%']
            );
            foreach ($rows as $row) {
                if (!is_numeric($row['id'] ?? null) || isset($seen[(int)$row['id']])) {
                    continue;
                }
                $seen[(int)$row['id']] = true;
                // The problem list gives rows no uuid until something asks for one.
                UuidRegistry::createMissingUuidForRow('lists', 'id', (int)$row['id']);
                $uuid = QueryUtils::fetchSingleValue('SELECT uuid FROM lists WHERE id = ?', 'uuid', [(int)$row['id']]);
                if (is_string($uuid)) {
                    $title = is_string($row['title'] ?? null) && $row['title'] !== '' ? $row['title'] : null;
                    $references[] = UtilsService::createRelativeReference('Condition', UuidRegistry::uuidToString($uuid), $title);
                }
            }
        }
        return $references;
    }

    /**
     * The requested services, or the referral code when none is named.
     *
     * @param array<mixed> $record
     */
    private static function requestedService(array $record): FHIRCodeableConcept
    {
        $codes = array_filter(
            (new CodeTypesService())->parseCodesIntoCodeableConcepts(self::text($record, 'refer_related_code') ?? ''),
            static fn(mixed $code): bool => is_array($code) && is_string($code['system'] ?? null) && $code['system'] !== ''
        );
        return $codes === [] ? self::referralConcept() : UtilsService::createCodeableConcept($codes);
    }

    /**
     * A reference to the user as a Practitioner, or an address-book entry as an
     * Organization, when FHIR serves them as one; else their name alone, so the reference
     * never points at a resource that isn't there.
     *
     * @param array<mixed> $record
     */
    private static function person(array $record, string $prefix): ?FHIRReference
    {
        $uuid = self::text($record, $prefix . '_uuid');
        $name = self::text($record, $prefix . '_name');
        if ($uuid !== null && in_array($record[$prefix . '_is_practitioner'] ?? null, [1, '1', true], true)) {
            return UtilsService::createRelativeReference('Practitioner', $uuid, $name);
        }
        if ($uuid !== null && in_array($record[$prefix . '_is_organization'] ?? null, [1, '1', true], true)) {
            return UtilsService::createRelativeReference('Organization', $uuid, $name);
        }
        if ($name === null) {
            return null;
        }
        $reference = new FHIRReference();
        $reference->setDisplay(new FHIRString($name));
        return $reference;
    }

    /**
     * @return list<array{string, string}> [system, code] of each coding
     */
    private static function codings(FHIRCodeableConcept $concept): array
    {
        $out = [];
        foreach ($concept->getCoding() as $coding) {
            $out[] = [(string)$coding->getSystem(), (string)$coding->getCode()];
        }
        return $out;
    }

    /**
     * Whether any searched token matches one of the codings: the code always, and the
     * system when the token names one.
     *
     * @param list<array{string, string}> $codings
     */
    private static function anyTokenMatches(TokenSearchField $field, array $codings): bool
    {
        foreach ($field->getValues() as $value) {
            if (!$value instanceof TokenSearchValue) {
                continue;
            }
            $system = $value->getSystem();
            foreach ($codings as [$codingSystem, $codingCode]) {
                if ((string)$value->getCode() === $codingCode && ($system === null || $system === '' || $system === $codingSystem)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Whether a requester or performer points at a resource, rather than being a name alone
     * or absent (the generated getters return null when the element isn't set).
     */
    private static function isReference(mixed $reference): bool
    {
        return $reference instanceof FHIRReference && trim((string)$reference->getReference()) !== '';
    }

    /**
     * @param array<mixed> $record
     */
    private static function text(array $record, string $column): ?string
    {
        $value = $record[$column] ?? null;
        if (is_int($value) || is_float($value)) {
            $value = (string)$value;
        }
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** A local date and time from the database, as FHIR's UTC form. */
    private static function utc(string $localDateTime): string
    {
        $utc = UtilsService::getLocalDateAsUTC($localDateTime);
        return is_string($utc) ? $utc : $localDateTime;
    }
}
