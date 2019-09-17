<?php

namespace DreamFactory\Core\Database\Testing;

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Testing\TestCase;
use DreamFactory\Core\Models\Service;
use ServiceManager;
use Config;

class ExcelTest extends TestCase
{
    const RESOURCE = '_spreadsheet';

    protected $serviceId = 'test-excel';

    protected $storageContainer = '/folder/';

    protected $types = [];

    protected $spreadsheetArray = [
        'Worksheet' => [
            ['id' => 1, 'prefix' => 'acme1554','some_date' => '5/23/2019 13:01'],
            ['id' => 2, 'prefix' => 'adecco','some_date' => '5/23/2019 13:02'],
            ['id' => 3, 'prefix' => 'cmtecnologia','some_date' => '5/23/2019 13:02'],
            ['id' => 4, 'prefix' => 'dealerclick','some_date' => '5/23/2019 13:16'],
        ]
    ];

    public function tearDown()
    {
        Service::whereName('test-excel')->delete();

        $this->deleteTestSpreadsheet();

        parent::tearDown();
    }

    public function testSpreadsheet()
    {

        $this->uploadTestSpreadsheet();
        $user = User::find(1);
        $token = JWTUtilities::makeJWTByUser($user->id, $user->email);
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE . '/testunit.xlsx', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);
        $spreadsheetData = $rs->getContent();
        $rs->assertSuccessful();
        $this->assertTrue($this->is_json($spreadsheetData));
        $this->assertEquals($this->spreadsheetArray, $rs->decodeResponseJson());
        $rs = $this->call(Verbs::GET, '/api/v2/' . $this->serviceId . '/' . self::RESOURCE . '/unittest.xlsx', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);
        $rs->assertStatus(404);
        $this->assertEquals($rs->decodeResponseJson()['error']['message'], 'Spreadsheet \'unittest.xlsx\' not found.');
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
        $this->makeRequest(Verbs::POST, $this->storageContainer . 'testunit.xlsx', [], $payload);
        $this->serviceId = 'test-excel';
        $this->setService();
    }

    private function deleteTestSpreadsheet()
    {
        $this->serviceId = 'files';
        $this->setService();
        $this->makeRequest(Verbs::DELETE,  $this->storageContainer . 'testunit.xlsx', []);
    }

    private function is_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function stage()
    {
        parent::stage();

        if (!$this->serviceExists('test-excel')) {
            \DreamFactory\Core\Models\Service::create(
                [
                    'name' => 'test-excel',
                    'label' => 'Excel Service',
                    'description' => 'Service to manage XLS, XLSX, CSV files.',
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