<?php

namespace DreamFactory\Core\Excel\Resources;

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Excel\Components\PHPSpreadsheetWrapper;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Excel\Models\ExcelConfig;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Utility\ResponseFactory;
use ServiceManager;

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
     */
    protected function handleGET()
    {
        $resourceArray = $this->resourceArray;
        $spreadsheetName = array_get($resourceArray, 0);
        $worksheetName = array_get($resourceArray, 1);

        $serviceConfig = $this->getService()->getConfig();
        $storageServiceId = array_get($serviceConfig, 'storage_service_id');
        $storageContainer = array_get($serviceConfig, 'storage_container', '/');
        $service = ServiceManager::getServiceById($storageServiceId);
        $firstRowHeaders = $this->request->getParameterAsBool('first_row_headers');
        $serviceName = $service->getName();

        try {
            $content = ServiceManager::handleRequest(
                $serviceName,
                Verbs::GET,
                $storageContainer,
                []
            );
            $spreadsheetWrapper = new PHPSpreadsheetWrapper($content, $serviceName, $storageContainer, $spreadsheetName, $firstRowHeaders);

            if (empty($spreadsheetName)) {
                return $content;
            } elseif (!empty($worksheetName)) {
                return ResponseFactory::create($spreadsheetWrapper->getWorksheet($worksheetName), 'application/json');
            } else {
                return ResponseFactory::create($spreadsheetWrapper->getSpreadsheet(), 'application/json');
            }
        } catch (\Exception $e) {
            \Log::error('Failed to fetch from storage service . ' . $e->getMessage());
            throw new RestException($e->getCode(), $e->getMessage());
        } catch (RuntimeException $e) {
            throw new RestException($e->getCode(), $e->getMessage());
        }
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
                            'name' => 'first_row_headers',
                            'in' => 'query',
                            'schema' => ['type' => 'boolean'],
                            'description' => 'Set true if headers located in the first row',
                        ],
                        [
                            'name' => 'spreadsheet_name',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                            'description' => 'Spreadsheet name',
                            'required' => true,
                        ],
                    ],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/SpreadsheetResponse'],
                    ],
                ],
            ],
            $path . '/{spreadsheet_name}/{worksheet}' => [
                'get' => [
                    'summary' => 'Get Spreadsheet Worksheet',
                    'description' => 'Fetches a spreadsheet worksheet data',
                    'operationId' => 'get' . $capitalized . 'SpreadsheetWorksheet',
                    'parameters' => [
                        [
                            'name' => 'first_row_headers',
                            'in' => 'query',
                            'schema' => ['type' => 'boolean'],
                            'description' => 'Set true if headers located in the first row',
                        ],
                        [
                            'name' => 'spreadsheet_name',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                            'description' => 'Repo name',
                            'required' => true,
                        ],
                        [
                            'name' => 'worksheet',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                            'description' => 'A worksheet name',
                            'required' => true,
                        ],
                    ],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/SpreadsheetWorksheetResponse'],
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
            'SpreadsheetWorksheetResponse' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/SpreadsheetWorksheet'
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
            'SpreadsheetWorksheet' => [
                'type' => 'object',
                'properties' => [
                    'resource' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/SpreadsheetWorksheet']
                    ],
                ],
            ],
        ];
    }
}