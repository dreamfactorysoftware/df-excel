<?php namespace DreamFactory\Core\Excel\Services;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Excel\Resources\SpreadsheetResource;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Enums\Verbs;

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
     * Fetches spreadsheet as a json.
     *
     * @return array
     * @throws UnauthorizedException
     */
    /*protected function handleGET()
    {
        $user = Session::user();

        if (empty($user)) {
            throw new UnauthorizedException('There is no valid session for the current request.');
        }

        if (ExampleComponent::getExample() !== "example") {
            throw new BadRequestException('Something went wrong in Excel Component');
        }



        return $result;
    }*/
}
