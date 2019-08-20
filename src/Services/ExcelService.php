<?php namespace DreamFactory\Core\Excel\Services;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Excel\Components\ExampleComponent;
use DreamFactory\Core\Excel\Models\ExcelConfig;
use DreamFactory\Core\Excel\Resources\ExcelResource;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Enums\Verbs;

class ExcelService extends BaseRestService
{
    /**
     * @var \DreamFactory\Core\Excel\Models\ExcelConfig
     */
    protected $excelModel = null;


    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Create a new ExcelService
     *
     * Create your methods, properties or override ones from the parent
     *
     * @param array $settings settings array
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($settings)
    {
        $this->excelModel = new ExcelConfig();
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

        if (ExampleComponent::getExample() !== "example") {
            throw new BadRequestException('Something went wrong in Excel Component');
        }

        $storageServiceId = array_get($this->config, 'storage_service_id');
        $storageContainer = array_get($this->config, 'storage_container', '/');
        if (!empty($storageServiceId) && !empty($storageContainer)) {
            try {
                $service = \ServiceManager::getServiceById($storageServiceId);
                $serviceName = $service->getName();
                $result = \ServiceManager::handleRequest(
                    $serviceName,
                    Verbs::GET,
                    $storageContainer,
                    []
                );
            } catch (\Exception $e) {
                \Log::error('Failed to fetch from storage service . ' . $e->getMessage());
            }
        }

        return $result;
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
        return ["You sent a POST request to " . $this->getName() . " DF service!"];
    }
}
