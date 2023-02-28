<?php namespace DreamFactory\Core\Excel\Services;

use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Excel\Resources\SpreadsheetResource;
use DreamFactory\Core\Enums\Verbs;
use ServiceManager;
use Illuminate\Support\Arr;

class ExcelService extends BaseRestService
{
    /** @type array Service Resources */
    protected static $resources = [
        SpreadsheetResource::RESOURCE_NAME => [
            'name'       => SpreadsheetResource::RESOURCE_NAME,
            'class_name' => SpreadsheetResource::class,
            'label'      => 'Spreadsheet'
        ]
    ];

    /**
     * {@inheritdoc}
     */
    public function getAccessList()
    {
        $list = parent::getAccessList();
        $nameField = static::getResourceIdentifier();

        foreach ($this->getResources() as $resource) {
            $name = Arr::get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                $list[] = $name . '/';
            }
        }

        $files = Arr::get($this->getContainerResponse()->getContent(), 'resource', $this->getContainerResponse()->getContent());

        foreach ($files as $resource) {
            $name = Arr::get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                $list[] = '_spreadsheet/' . $name . '/';
            }
        }

        return $list;
    }

    public function getContainerResponse()
    {
        $serviceConfig = $this->getConfig();
        $storageServiceId = Arr::get($serviceConfig, 'storage_service_id');
        $storageContainer = Arr::get($serviceConfig, 'storage_container', '/');
        $storageService = ServiceManager::getServiceById($storageServiceId);
        $storageServiceName = $storageService->getName();
        $response =  ServiceManager::handleRequest(
            $storageServiceName,
            Verbs::GET,
            $storageContainer,
            [
                'include_folders' => request()->get('include_folders', false),
                'as_list' => request()->get('as_list'),
            ]
        );
        return $response;
    }
}
