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
use Illuminate\Support\Arr;

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
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws RestException
     */
    protected function handleGET()
    {
        $resourceArray = $this->resourceArray;
        $spreadsheetName = Arr::get($resourceArray, 0);
        $worksheetName = Arr::get($resourceArray, 1);

        $serviceConfig = $this->getService()->getConfig();
        $storageServiceId = Arr::get($serviceConfig, 'storage_service_id');
        $storageContainer = Arr::get($serviceConfig, 'storage_container', '/');
        
        // Check if storage service is configured
        if (empty($storageServiceId)) {
            throw new RestException(
                400, 
                'Excel service is not properly configured. Please configure a storage service (storage_service_id) in the service configuration.'
            );
        }
        
        $storageService = ServiceManager::getServiceById($storageServiceId);
        
        // Check if storage service exists
        if (empty($storageService)) {
            throw new RestException(
                400, 
                'Configured storage service (ID: ' . $storageServiceId . ') not found. Please check your service configuration.'
            );
        }
        
        $storageServiceName = $storageService->getName();

        try {
            $content = $this->getService()->getContainerResponse();

            if (empty($spreadsheetName)) {
                return $content;
            } else {
                $spreadsheetWrapper = new PHPSpreadsheetWrapper($content, $storageServiceName, $storageContainer, $spreadsheetName, $this->request->getParameters());

                if (!empty($worksheetName)) {
                    return ResponseFactory::create($spreadsheetWrapper->getWorksheetData($worksheetName), 'application/json');
                }

                return ResponseFactory::create($spreadsheetWrapper->getSpreadsheetData(), 'application/json');
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
            $path => [
                'get' => [
                    'summary' => 'Get folder content as list',
                    'description' => 'Content of a folder as a list',
                    'operationId' => 'get' . $capitalized . ' folder',
                    'parameters' => [
                        [
                            'name' => 'include_folders',
                            'in' => 'query',
                            'schema' => ['type' => 'boolean'],
                            'description' => 'Include folders in the returned listing. Default is false.',
                        ],
                        ApiOptions::documentOption(ApiOptions::AS_LIST),
                    ],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/SpreadsheetListResponse'],
                    ],
                ],
            ],
            $path . '/{spreadsheet_name}' => [
                'get' => [
                    'summary' => 'Get Spreadsheet data as a json',
                    'description' => 'Fetches a spreadsheet data as a json array where keys is header names',
                    'operationId' => 'get' . $capitalized . 'Spreadsheet',
                    'parameters' => array_merge($this->getSpreadsheetApiDocsParameters(), [
                        [
                            'name' => 'with_worksheet_names',
                            'in' => 'query',
                            'schema' => ['type' => 'boolean'],
                            'description' => 'Do you want to separate data by Worksheets?',
                        ],
                        [
                            'name' => 'spreadsheet_name',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                            'description' => 'Spreadsheet name',
                            'required' => true,
                        ],
                    ]),
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
                    'parameters' => array_merge($this->getSpreadsheetApiDocsParameters(), [
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
                    ]),
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/SpreadsheetWorksheetResponse'],
                    ],
                ],
            ],
        ];

        return $paths;
    }

    protected function getSpreadsheetApiDocsParameters()
    {
        return [
            [
                'name' => 'first_row_headers',
                'in' => 'query',
                'schema' => ['type' => 'boolean'],
                'description' => 'Headers located in the first row. Default is true.',
            ],
            [
                'name' => 'skip_empty_rows',
                'in' => 'query',
                'schema' => ['type' => 'boolean'],
                'description' => 'Determines whether to skip empty rows. Default is false.',
            ],
            [
                'name' => 'calculate_formulas',
                'in' => 'query',
                'schema' => ['type' => 'boolean'],
                'description' => 'Calculate formulas. Default is false.',
            ],
            [
                'name' => 'formatted_values',
                'in' => 'query',
                'schema' => ['type' => 'boolean'],
                'description' => 'Format data. Default is true.',
            ],
            [
                'name' => 'memory_limit',
                'in' => 'query',
                'schema' => ['type' => 'string'],
                'description' => 'Extend php.ini\'s memory limit in case of \'Allowed memory size of xxx bytes exhausted\' error. Default is 128M (-1 = no limit).',
            ]
        ];
    }

    /** {@inheritdoc} */
    protected function getApiDocResponses()
    {
        return [
            'SpreadsheetListResponse' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/SpreadsheetList'
                        ]
                    ]
                ]
            ],
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
            'SpreadsheetList' => [
                'type' => 'object',
                'properties' => [
                    'resource' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/SpreadsheetList']
                    ],
                ]
            ],
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