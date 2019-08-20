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
        'label',
        'description'
    ];

    /** @var array */
    protected $casts = [
        'service_id' => 'integer',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['created_date'];

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);
    }


}