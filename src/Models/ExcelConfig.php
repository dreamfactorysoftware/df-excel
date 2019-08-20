<?php

namespace DreamFactory\Core\Excel\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;
use Illuminate\Support\Arr;

/**
 * Write your model
 *
 * Write your methods, properties or override ones from the parent
 *
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

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {

        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'storage_service_id':
                $schema['label'] = 'Account/Organization';
                $schema['description'] = 'Bitbucket Account/Organization/Username for accessing a repository.';
                break;

        }

    }


}