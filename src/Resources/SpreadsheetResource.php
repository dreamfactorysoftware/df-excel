<?php

namespace DreamFactory\Core\Excel\Resources;

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Excel\Models\ExcelConfig;
use DreamFactory\Core\Utility\Curl;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Utility\ResponseFactory;
use ServiceManager;

// Resource can extend BaseRestResource,BaseSystemResource, ReadOnlySystemResource, or any newly created

class SpreadsheetResource extends BaseRestResource
{
    /**
     * The url would be /api/v2/{service_name}/excel_resource
     */
    const RESOURCE_NAME = '_spreadsheet';

    /** A resource identifier used in swagger doc. */
    const RESOURCE_IDENTIFIER = 'name';

    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return static::RESOURCE_IDENTIFIER;
    }

    /**
     * @var string DreamFactory\Core\Models\User Model Class name.
     */
    protected static $model = ExcelConfig::class;

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT => Verbs::PATCH
        ];
        $settings["verbAliases"] = $verbAliases;

        parent::__construct($settings);
    }

    /**
     * Fetches spreadsheet as a json.
     *
     * @return array
     * @throws UnauthorizedException
     */
    protected function handleGET()
    {
        $resourceArray = $this->resourceArray;
        $spreadsheetName = array_get($resourceArray, 0);
        $tabName = array_get($resourceArray, 1);

        $serviceConfig = $this->getService()->getConfig();
        $storageServiceId = array_get($serviceConfig, 'storage_service_id');
        $storageContainer = array_get($serviceConfig, 'storage_container', '/') . '/';
        $service = \ServiceManager::getServiceById($storageServiceId);
        $serviceName = $service->getName();

        try {
            $content = \ServiceManager::handleRequest(
                $serviceName,
                Verbs::GET,
                $storageContainer,
                []
            );
//
            if (empty($spreadsheetName)) {
                return $content;
            } else {
                if (!$this->doesSpreadsheetExist($content, $spreadsheetName)) {
                    throw new NotFoundException("Spreadsheet '{$spreadsheetName}' not found.");
                };
                $content = [];
                $spreadsheetFile = $this->getSpreadsheet($serviceName, $storageContainer, $spreadsheetName);
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($spreadsheetFile);
                foreach ($spreadsheet->getSheetNames() as $worksheetName) {
                    $content[$worksheetName] = $this->mapSpreadsheetContent($spreadsheet->getSheetByName($worksheetName)->toArray());
                };
                return json_encode($content);
//
            }
        } catch (\Exception $e) {
            \Log::error('Failed to fetch from storage service . ' . $e->getMessage());
            throw new RestException($e->getCode(), $e->getMessage());
        } catch (RuntimeException $e) {
            throw new RestException($e->getCode(), $e->getMessage());
        } catch (\Gitlab\Exception\RuntimeException $e) {
            throw new RestException($e->getCode(), $e->getMessage());
        }

        return $content;
    }


    /**
     * Map spreadsheet content
     *
     * @param $data
     * @return array
     */
    protected function mapSpreadsheetContent($data)
    {
        $result = [];

        $keys = $data[0];
        array_shift($data);
        foreach ($data as $item) {
            $newItem = [];
            foreach ($item as $key => $value) {
                $newItem [$keys[$key]] = $value;
            }

            $result [] = $newItem;
        }
        return $result;
    }


    /**
     * Does spreadsheet exists in the given container
     *
     * @param $list
     * @param $spreadsheetName
     * @return bool
     */
    protected function doesSpreadsheetExist($list, $spreadsheetName)
    {
        // TODO: maybe use file service's fileExists method ?
        if (isset($list->getContent()['resource'])) {
            $list = $list->getContent()['resource'];
        } else {
            $list = $list->getContent();
        }
        if ($list) {
            foreach ($list as $file) {
                if (isset($file['name']) && $file['name'] === $spreadsheetName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get Spreadsheet using file storage service
     *
     * @param $storageServiceName
     * @param $path
     * @return
     */
    protected function getSpreadsheet($storageServiceName, $containerPath, $spreadsheetname)
    {
        $result = \ServiceManager::handleRequest(
            $storageServiceName,
            Verbs::GET,
            $containerPath . $spreadsheetname,
            ['download' => 1, 'content' => 1, 'include_properties' => 1]
        );
        $tmpFile = tempnam(sys_get_temp_dir(), 'tempexcel');
        file_put_contents($tmpFile, base64_decode($result->getContent()['content']));
        return $tmpFile;
    }

    /** {@inheritdoc} */
    protected function getApiDocPaths()
    {
        $service = $this->getServiceName();
        $capitalized = camelize($service);
        $resourceName = strtolower($this->name);
        $path = '/' . $resourceName;

        $paths = [
            $path . '/{spreadsheet_name}' => [
                'get' => [
                    'summary' => 'Get Spreadsheet data as a json',
                    'description' => 'Fetches a spreadsheet data as a json array where keys is header names',
                    'operationId' => 'get' . $capitalized . 'Spreadsheet',
                    'parameters' => [
                        [
                            'name' => 'spreadsheet_name',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                            'description' => 'Spreadsheet name',
                            'required' => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::AS_LIST),
                    ],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/SpreadsheetResponse'],
                    ],
                ],
            ],
            $path . '/{spreadsheet_name}/{tab}' => [
                'get' => [
                    'summary' => 'Get Spreadsheet Tab',
                    'description' => 'Fetches a spreadsheet tab data',
                    'operationId' => 'get' . $capitalized . 'SpreadsheetTab',
                    'parameters' => [
                        [
                            'name' => 'spreadsheet_name',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                            'description' => 'Repo name',
                            'required' => true,
                        ],
                        [
                            'name' => 'tab',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                            'description' => 'A tab name',
                            'required' => true,
                        ],
                    ],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/SpreadsheetTabResponse'],
                    ],
                ],
            ],
        ];

        return $paths;
    }

    /** {@inheritdoc} */
    protected function getApiDocResponses()
    {
        return [
            'SpreadsheetResponse' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Spreadsheet'
                        ]
                    ]
                ]
            ],
            'SpreadsheetTabResponse' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/SpreadsheetTab'
                        ]
                    ]
                ]
            ],
        ];
    }

    /** {@inheritdoc} */
    protected function getApiDocSchemas()
    {
        return [
            'Spreadsheet' => [
                'type' => 'object',
                'properties' => [
                    'resource' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Spreadsheet']
                    ],
                ]
            ],
            'SpreadsheetTab' => [
                'type' => 'object',
                'properties' => [
                    'resource' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/SpreadsheetTab']
                    ],
                ],
            ],
        ];
    }
}