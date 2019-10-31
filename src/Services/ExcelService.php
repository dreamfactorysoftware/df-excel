<?php namespace DreamFactory\Core\Excel\Services;

use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Excel\Resources\SpreadsheetResource;
use DreamFactory\Core\Enums\Verbs;
use ServiceManager;

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
            $name = array_get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                $list[] = $name . '/';
            }
        }

        $files = array_get($this->getContainerResponse()->getContent(), 'resource', $this->getContainerResponse()->getContent());

        foreach ($files as $resource) {
            $name = array_get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                $list[] = '_spreadsheet/' . $name . '/';
            }
        }

        return $list;
    }

    public function getContainerResponse()
    {
        $serviceConfig = $this->getConfig();
        $storageServiceId = array_get($serviceConfig, 'storage_service_id');
        $storageContainer = array_get($serviceConfig, 'storage_container', '/');
        $storageService = ServiceManager::getServiceById($storageServiceId);
        $storageServiceName = $storageService->getName();
        return ServiceManager::handleRequest(
            $storageServiceName,
            Verbs::GET,
            $storageContainer,
            [
                'include_folders' => $this->request->getParameterAsBool('include_folders', false),
                'as_list' => $this->request->getParameterAsBool('as_list')
            ]
        );
    }
}
