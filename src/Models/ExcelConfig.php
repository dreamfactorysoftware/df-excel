<?php

namespace DreamFactory\Core\Excel\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

/**
 * Excel Service Configuration Model
 *
 * @package DreamFactory\Core\Excel\Models
 */
class ExcelConfig extends BaseServiceConfigModel
{
    /** @var string */
    protected $table = 'excel_config';

    /** @var array */
    protected $fillable = [
        'service_id',
        'storage_service_id',
        'storage_container'
    ];

    /** @var array */
    protected $casts = [
        'service_id' => 'integer',
        'storage_service_id' => 'integer',
    ];

    /** @var array */
    protected $rules = [
        'storage_service_id' => 'required|integer|exists:service,id',
        'storage_container' => 'nullable|string'
    ];

    /**
     * Get the configuration schema for the Excel service
     *
     * @return array
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        
        // Override the storage_service_id field to make it required and provide better description
        foreach ($schema as &$field) {
            if ($field['name'] === 'storage_service_id') {
                $field['label'] = 'Storage Service';
                $field['description'] = 'Select a file storage service that will store the Excel files. This is required for the Excel service to function.';
                $field['required'] = true;
                $field['allow_null'] = false;
                $field['type'] = 'integer';
                $field['picklist'] = 'service';
                $field['values'] = ['group' => 'file'];
                break;
            } elseif ($field['name'] === 'storage_container') {
                $field['label'] = 'Storage Container';
                $field['description'] = 'Enter the container/folder path within the storage service where Excel files will be stored. Default is "/" (root).';
                $field['required'] = false;
                $field['allow_null'] = true;
                $field['type'] = 'string';
                $field['default'] = '/';
                break;
            }
        }
        
        return $schema;
    }

    /**
     * {@inheritdoc}
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'storage_service_id':
                $schema['type'] = 'integer';
                $schema['required'] = true;
                $schema['allow_null'] = false;
                break;
            case 'storage_container':
                $schema['type'] = 'string';
                $schema['required'] = false;
                $schema['allow_null'] = true;
                $schema['default'] = '/';
                break;
        }
    }
}