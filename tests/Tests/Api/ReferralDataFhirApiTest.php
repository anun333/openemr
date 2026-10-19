<?php

/**
 * What a referral's fields say through FHIR (#14710's Data items): its Risk Level as the
 * ServiceRequest's and the Task's priority, its Referral Category as categories, and its
 * coded Referrer Diagnosis linked to the patient's problem list.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Api;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Services\Globals\GlobalConnectorsEnum;
use OpenEMR\Services\ReferralTaskService;
use OpenEMR\Services\TaskService;
use OpenEMR\Tests\Fixtures\ConditionFixtureManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ReferralDataFhirApiTest extends TestCase
{
    private const FHIR_URL = '/apis/default/fhir/';

    private ApiTestClient $testClient;
    private ConditionFixtureManager $fixtures;
    private mixed $globalBefore;
    private ?string $codesBefore = null;

    /** @var array{uuid: string, pid: int} */
    private array $patient;

    /** @var array<string, array{uuid: string, id: int}> */
    private array $people = [];

    /** @var list<int> transaction ids */
    private array $transactions = [];

    /** @var list<int> lists ids */
    private array $problems = [];

    protected function setUp(): void
    {
        $baseUrl = getenv("OPENEMR_BASE_URL_API", true) ?: "https://localhost";
        $this->testClient = new ApiTestClient($baseUrl, false);
        $this->testClient->setAuthToken(ApiTestClient::OPENEMR_AUTH_ENDPOINT);
        $this->fixtures = new ConditionFixtureManager();
        $patient = $this->fixtures->createTestPatient();
        $this->assertIsString($patient['uuid'] ?? null);
        $this->assertIsInt($patient['pid'] ?? null);
        $this->patient = ['uuid' => $patient['uuid'], 'pid' => $patient['pid']];
        $this->globalBefore = OEGlobalsBag::getInstance()->get(GlobalConnectorsEnum::FHIR_REFERRAL_TASKS->value);
        OEGlobalsBag::getInstance()->set(GlobalConnectorsEnum::FHIR_REFERRAL_TASKS->value, '1');
        $this->people['pantry'] = $this->insertPerson(['abook_type' => 'external_org', 'organization' => 'Zq Data Pantry']);
    }

    protected function tearDown(): void
    {
        OEGlobalsBag::getInstance()->set(GlobalConnectorsEnum::FHIR_REFERRAL_TASKS->value, $this->globalBefore);
        if ($this->codesBefore !== null) {
            QueryUtils::sqlStatementThrowException("UPDATE list_options SET codes = ? WHERE list_id = 'referral_category' AND option_id = 'behavioral_health'", [$this->codesBefore]);
        }
        foreach ($this->transactions as $id) {
            $uuid = QueryUtils::fetchSingleValue('SELECT uuid FROM transactions WHERE id = ?', 'uuid', [$id]);
            if (is_string($uuid)) {
                QueryUtils::sqlStatementThrowException('DELETE FROM task WHERE focus_uuid = ?', [$uuid]);
            }
            QueryUtils::sqlStatementThrowException('DELETE FROM lbt_data WHERE form_id = ?', [$id]);
            QueryUtils::sqlStatementThrowException('DELETE FROM transactions WHERE id = ?', [$id]);
        }
        foreach ($this->problems as $problem) {
            QueryUtils::sqlStatementThrowException("DELETE FROM uuid_registry WHERE table_name = 'lists' AND uuid IN (SELECT uuid FROM lists WHERE id = ?)", [$problem]);
            QueryUtils::sqlStatementThrowException('DELETE FROM lists WHERE id = ?', [$problem]);
        }
        foreach ($this->people as $person) {
            QueryUtils::sqlStatementThrowException('DELETE FROM users WHERE id = ?', [$person['id']]);
        }
        $this->fixtures->removeFixtures();
        $this->testClient->cleanupRevokeAuth();
        $this->testClient->cleanupClient();
    }

    public function testTheRiskLevelIsThePriority(): void
    {
        foreach (['high' => 'urgent', 'medium' => 'routine', 'low' => 'routine', '' => null] as $risk => $priority) {
            $fields = ['refer_to' => $this->people['pantry']['id'], 'body' => "risk $risk"] + ($risk === '' ? [] : ['refer_risk_level' => $risk]);
            $id = $this->saveReferral($fields);
            $taskUuid = $this->saved($id);
            $this->assertIsString($taskUuid);

            $this->assertSame($priority, $this->read('ServiceRequest', $this->referralUuid($id))['priority'] ?? null, "ServiceRequest, risk '$risk'");
            $this->assertSame($priority, $this->read('Task', $taskUuid)['priority'] ?? null, "Task, risk '$risk'");
        }
    }

    public function testAnOpenTaskFollowsAChangedRiskLevelAndAClosedOneDoesNot(): void
    {
        $id = $this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_risk_level' => 'low', 'body' => 'x']);
        $taskUuid = $this->saved($id);
        $this->assertIsString($taskUuid);

        $this->saveFields($id, ['refer_risk_level' => 'high']);
        $this->assertNull($this->saved($id), 'no second Task');
        $this->assertSame('urgent', $this->read('Task', $taskUuid)['priority'] ?? null);

        (new TaskService())->update($taskUuid, ['status' => 'completed'], null);
        $this->saveFields($id, ['refer_risk_level' => 'low']);
        $this->saved($id);
        $this->assertSame('urgent', $this->read('Task', $taskUuid)['priority'] ?? null, 'a completed Task keeps its priority');
        $this->assertSame('routine', $this->read('ServiceRequest', $this->referralUuid($id))['priority'] ?? null, 'while the referral says what it says now');
    }

    public function testAnSdohCategoryIsUsCoresSdohAndItsDomain(): void
    {
        $id = $this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_category' => 'food_insecurity', 'body' => 'x']);

        $this->assertSame([
            ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '3457005', 'display' => 'Patient referral']]],
            ['coding' => [['system' => 'http://hl7.org/fhir/us/core/CodeSystem/us-core-category', 'code' => 'sdoh', 'display' => 'SDOH']]],
            ['coding' => [['system' => 'http://hl7.org/fhir/us/sdoh-clinicalcare/CodeSystem/SDOHCC-CodeSystemTemporaryCodes', 'code' => 'food-insecurity', 'display' => 'Food Insecurity']], 'text' => 'Food Insecurity'],
        ], $this->read('ServiceRequest', $this->referralUuid($id))['category'] ?? null);
    }

    public function testAnotherCategoryIsItsTitleWithTheCodesTheListGivesIt(): void
    {
        $specialty = $this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_category' => 'specialty', 'body' => 'x']);
        $codes = QueryUtils::fetchSingleValue("SELECT codes FROM list_options WHERE list_id = 'referral_category' AND option_id = 'behavioral_health'", 'codes', []);
        $this->codesBefore = is_string($codes) ? $codes : '';
        QueryUtils::sqlStatementThrowException("UPDATE list_options SET codes = 'SNOMED-CT:306136006' WHERE list_id = 'referral_category' AND option_id = 'behavioral_health'");
        $behavioral = $this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_category' => 'behavioral_health', 'body' => 'x']);

        $this->assertSame(['text' => 'Specialty Care'], self::at($this->read('ServiceRequest', $this->referralUuid($specialty)), 'category', 1));
        $category = self::at($this->read('ServiceRequest', $this->referralUuid($behavioral)), 'category', 1);
        $this->assertIsArray($category);
        $this->assertSame('Behavioral Health', $category['text'] ?? null);
        $this->assertSame('http://snomed.info/sct', self::at($category, 'coding', 0, 'system'));
        $this->assertSame('306136006', self::at($category, 'coding', 0, 'code'));
    }

    public function testACategorySearchFindsReferralsByTheirCategory(): void
    {
        $food = $this->referralUuid($this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_category' => 'food_insecurity', 'body' => 'x']));
        $specialty = $this->referralUuid($this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_category' => 'specialty', 'body' => 'x']));
        $none = $this->referralUuid($this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'body' => 'x']));
        $patient = ['patient' => $this->patient['uuid']];

        $this->assertSame([$food], $this->ids($patient + ['category' => 'http://hl7.org/fhir/us/core/CodeSystem/us-core-category|sdoh']));
        $this->assertSame([$food], $this->ids($patient + ['category' => 'food-insecurity']));
        $this->assertSame([], $this->ids($patient + ['category' => 'http://snomed.info/sct|food-insecurity']), 'the right code in another system');
        $this->assertEqualsCanonicalizing([$food, $specialty, $none], $this->ids($patient + ['category' => '3457005']));
        $this->assertSame([], $this->ids($patient + ['category' => 'http://snomed.info/sct|108252007']), 'a laboratory category reads only orders');
    }

    public function testACodedDiagnosisLinksThePatientsMatchingActiveProblem(): void
    {
        $this->assertEquals(15, QueryUtils::fetchSingleValue("SELECT data_type FROM layout_options WHERE form_id = 'LBTref' AND field_id = 'refer_diag'", 'data_type', []), 'Referrer Diagnosis takes codes');
        $hypertension = $this->insertProblem($this->patient['pid'], 'Hypertension', 'ICD10:I10', 1);
        $this->insertProblem($this->patient['pid'], 'Hypertension, resolved', 'ICD10:I10', 0);
        $this->insertProblem($this->patient['pid'], 'Diabetes', 'ICD10:E11.9', 1);
        $other = $this->fixtures->createTestPatient();
        $this->assertIsInt($other['pid'] ?? null);
        $this->insertProblem($other['pid'], 'Someone else', 'ICD10:I10', 1);
        $coded = $this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_diag' => 'ICD10:I10; Chest pain', 'body' => 'x']);
        $words = $this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_diag' => 'Hypertension', 'body' => 'x']);

        $references = $this->read('ServiceRequest', $this->referralUuid($coded))['reasonReference'] ?? null;
        $this->assertIsArray($references);
        $this->assertCount(1, $references, 'the active problem with the code, not the resolved one, another code, or another patient\'s');
        $uuid = QueryUtils::fetchSingleValue('SELECT uuid FROM lists WHERE id = ?', 'uuid', [$hypertension]);
        $this->assertIsString($uuid);
        $this->assertSame(['reference' => 'Condition/' . UuidRegistry::uuidToString($uuid), 'type' => 'Condition', 'display' => 'Hypertension'], $references[0]);
        $this->assertSame('Condition', $this->read('Condition', UuidRegistry::uuidToString($uuid))['resourceType'] ?? null, 'the Condition reads');
        $this->assertArrayNotHasKey('reasonReference', $this->read('ServiceRequest', $this->referralUuid($words)), 'words link nothing');
    }

    public function testASnomedRequestedServiceIsCodedAsSnomed(): void
    {
        // Requested Service's picker offers every active code type, SNOMED CT among them once a
        // site has activated it, and stores the pick as TYPE:code.
        $id = $this->saveReferral(['refer_to' => $this->people['pantry']['id'], 'refer_related_code' => 'SNOMED-CT:306206005', 'body' => 'x']);

        $coding = self::at($this->read('ServiceRequest', $this->referralUuid($id)), 'code', 'coding', 0);
        $this->assertIsArray($coding);
        $this->assertSame('http://snomed.info/sct', $coding['system'] ?? null);
        $this->assertSame('306206005', $coding['code'] ?? null);
    }

    /**
     * The value at a path of keys in a decoded resource, or null.
     *
     * @param array<mixed> $resource
     */
    private static function at(array $resource, string|int ...$path): mixed
    {
        $value = $resource;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    private function insertProblem(int $pid, string $title, string $diagnosis, int $activity): int
    {
        $id = (int)QueryUtils::sqlInsert(
            "INSERT INTO lists (date, type, title, diagnosis, activity, pid, begdate, user, groupname) VALUES (NOW(), 'medical_problem', ?, ?, ?, ?, '2026-01-01', 'admin', 'Default')",
            [$title, $diagnosis, $activity, $pid]
        );
        $this->problems[] = $id;
        return $id;
    }

    /**
     * @param array<string, string> $query
     * @return list<string>
     */
    private function ids(array $query): array
    {
        $response = $this->testClient->get(self::FHIR_URL . 'ServiceRequest', $query);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string)$response->getBody());
        $bundle = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($bundle);
        $ids = [];
        foreach (is_array($bundle['entry'] ?? null) ? $bundle['entry'] : [] as $entry) {
            $id = is_array($entry) && is_array($entry['resource'] ?? null) ? ($entry['resource']['id'] ?? null) : null;
            if (is_string($id)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Hands a saved referral to ReferralTaskService as add_transaction.php does.
     *
     * @phpstan-impure it saves a Task
     */
    private function saved(int $transactionId): ?string
    {
        return (new ReferralTaskService())->createForReferral($transactionId, null);
    }

    private function referralUuid(int $transactionId): string
    {
        // A FHIR search would give the referral its uuid; this does it for the one row.
        UuidRegistry::createMissingUuidForRow('transactions', 'id', $transactionId);
        $uuid = QueryUtils::fetchSingleValue('SELECT uuid FROM transactions WHERE id = ?', 'uuid', [$transactionId]);
        $this->assertIsString($uuid);
        return UuidRegistry::uuidToString($uuid);
    }

    /**
     * @return array<mixed>
     * @phpstan-impure a resource can change between reads
     */
    private function read(string $type, string $uuid): array
    {
        $response = $this->testClient->get(self::FHIR_URL . $type . '/' . $uuid);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string)$response->getBody());
        $resource = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($resource);
        return $resource;
    }

    /**
     * A referral as add_transaction.php saves a new one: no uuid, and only the fields with a value.
     *
     * @param array<string, int|string> $fields
     */
    private function saveReferral(array $fields): int
    {
        $id = (int)QueryUtils::sqlInsert(
            "INSERT INTO transactions SET title = 'LBTref', user = 'admin', groupname = 'Default', authorized = 1, date = NOW(), pid = ?",
            [$this->patient['pid']]
        );
        $this->transactions[] = $id;
        $this->saveFields($id, $fields);
        return $id;
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function saveFields(int $transactionId, array $fields): void
    {
        foreach ($fields as $field => $value) {
            QueryUtils::sqlStatementThrowException('REPLACE INTO lbt_data SET field_value = ?, form_id = ?, field_id = ?', [(string)$value, $transactionId, $field]);
        }
    }

    /**
     * @param array<string, string> $columns
     * @return array{uuid: string, id: int}
     */
    private function insertPerson(array $columns): array
    {
        $uuid = UuidRegistry::getRegistryForTable('users')->createUuid();
        $row = $columns + ['username' => '', 'active' => '1', 'authorized' => '0'];
        $row['uuid'] = $uuid;
        $id = QueryUtils::sqlInsert(
            'INSERT INTO users (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')',
            array_values($row)
        );
        return ['uuid' => UuidRegistry::uuidToString($uuid), 'id' => (int)$id];
    }
}
