<?php

namespace DreamFactory\Core\Database\Testing;

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Testing\TestCase;
use Illuminate\Http\File;
use Illuminate\Support\Arr;
use ServiceManager;
use Config;
use Storage;

class ExcelTest extends TestCase
{
    const RESOURCE = '_spreadsheet';

    protected $serviceId = 'test-excel';

    protected $storageContainer = '/';

    protected $types = [];

    protected $spreadsheetArray = [
        'Worksheet' => [
            ['id' => 1, 'prefix' => 'acme1554', 'some_date' => '5/23/2019 13:01'],
            ['id' => 2, 'prefix' => 'adecco', 'some_date' => '5/23/2019 13:02'],
            ['id' => 3, 'prefix' => 'cmtecnologia', 'some_date' => '5/23/2019 13:02'],
            ['id' => 4, 'prefix' => 'dealerclick', 'some_date' => '5/23/2019 13:16'],
        ]
    ];

    public function tearDown()
    {
        $this->deleteTestSpreadsheet();
        Service::whereName('test-excel')->delete();

        parent::tearDown();
    }

    public function testSpreadsheetList()
    {
        $this->createTestExcelService();
        $user = User::find(1);
        Storage::putFileAs('/', new File(__DIR__ . '/pivot-tables.xlsx'), 'pivot-tables.xlsx');
        $token = JWTUtilities::makeJWTByUser($user->id, $user->email);
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE, [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);

        $spreadsheetData = Arr::get($rs->decodeResponseJson(), 'resource');

        $spreadsheetExists = false;
        foreach ($spreadsheetData as $spreadsheetFile) {
            if ($spreadsheetFile['name'] === 'pivot-tables.xlsx')  $spreadsheetExists = true;
        }

        $this->assertTrue($spreadsheetExists );


    }

    public function testSpreadsheet()
    {
        $this->createTestExcelService();
        $this->uploadTestSpreadsheet();
        $user = User::find(1);
        $token = JWTUtilities::makeJWTByUser($user->id, $user->email);
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE . $this->storageContainer .'testunit.xlsx', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);
        $spreadsheetData = $rs->getContent();
        $rs->assertSuccessful();
        $this->assertTrue($this->is_json($spreadsheetData));
        $this->assertEquals($this->spreadsheetArray, $rs->decodeResponseJson());
        $this->assertTrue(isset($rs->decodeResponseJson()['Worksheet']));
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE . '/unittest.xlsx', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);
        $rs->assertStatus(404);
        $this->assertEquals($rs->decodeResponseJson()['error']['message'], 'Spreadsheet \'unittest.xlsx\' not found.');
    }

    public function testWorksheet()
    {
        $this->createTestExcelService();
        Storage::putFileAs('/', new File(__DIR__ . '/pivot-tables.xlsx'), 'pivot-tables.xlsx');
        $user = User::find(1);
        $token = JWTUtilities::makeJWTByUser($user->id, $user->email);

        $jsonPart = ['Order ID' => 1, 'Product' => 'Carrots', 'Category' => 'Vegetables', 'Amount' => '$4,270', 'Date' => '1/6/2016', 'Country' => 'United States'];
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE . '/pivot-tables.xlsx/Sheet1', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);
        $rs->assertJsonFragment($jsonPart);

        $jsonPart = ['Order ID' => 3, 'Product' => 'Banana', 'Category' => 'Fruit', 'Amount' => '$8,384', 'Date' => '1/10/2016', 'Country' => 'Canada'];
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE . '/pivot-tables.xlsx/Sheet1', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);
        $rs->assertJsonFragment($jsonPart);
        $this->assertFalse(isset($rs->decodeResponseJson()['Sheet1']));

        $jsonPart = ['Segment' => 'Government', 'Country' => 'Germany', 'Product' => 'Velo'];
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE . '/pivot-tables.xlsx/Sheet2', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);
        $rs->assertJsonFragment($jsonPart);

        $this->assertFalse(isset($rs->decodeResponseJson()['Sheet2']));
    }

    public function stage()
    {
        parent::stage();

        $this->createTestExcelService();
    }

    private function uploadTestSpreadsheet()
    {
        $this->serviceId = 'files';
        $this->setService();
        $payload = 'id,prefix,some_date
1,acme1554,5/23/2019 13:01
2,adecco,5/23/2019 13:02
3,cmtecnologia,5/23/2019 13:02
4,dealerclick,5/23/2019 13:16';
        $rs = $this->makeRequest(Verbs::POST, $this->storageContainer . 'testunit.xlsx', [], $payload);
        $this->serviceId = 'test-excel';
        $this->setService();
    }

    private function deleteTestSpreadsheet()
    {
        $this->serviceId = 'files';
        $this->setService();
        $this->makeRequest(Verbs::DELETE, $this->storageContainer . 'testunit.xlsx', []);
        $this->serviceId = 'test-excel';
        $this->setService();
    }

    private function is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    private function createTestExcelService()
    {
        if (!$this->serviceExists('test-excel')) {
            \DreamFactory\Core\Models\Service::create(
                [
                    'name' => 'test-excel',
                    'label' => 'Excel Service',
                    'description' => 'Service to manage excel files.',
                    'is_active' => true,
                    'type' => 'excel',
                    'config' => [
                        'storage_service_id' => ServiceManager::getServiceIdByName('files'),
                        'storage_container' => $this->storageContainer
                    ]
                ]
            );
        }
    }
}