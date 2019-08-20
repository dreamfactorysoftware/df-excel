<?php

namespace DreamFactory\Core\Excel\Resources;

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Excel\Models\ExcelConfig;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Utility\Session;

// Resource can extend BaseRestResource,BaseSystemResource, ReadOnlySystemResource, or any newly created

class ExcelResource extends BaseRestResource
{
    /**
     * The url would be /api/v2/{service_name}/excel_resource
    */
    const RESOURCE_NAME = 'excel_resource';

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

        $this->excelModel = new static::$model;
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
        $user = Session::user();

        if (empty($user)) {
            throw new UnauthorizedException('There is no valid session for the current request.');
        }

        $content = $this->excelModel->all();

        return ["message" => "You sent a GET request to DF!",
                "content" => $content];
    }

    /**
     * Updates user profile.
     *
     * @return array
     * @throws NotFoundException
     * @throws \Exception
     */
    protected function handlePOST()
    {
        $user = Session::user();

        if (empty($user)) {
            throw new NotFoundException('No user session found.');
        }
        return ["You sent a POST request to DF!"];
    }
}