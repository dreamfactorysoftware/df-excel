<?php
namespace DreamFactory\Core\Excel;

use DreamFactory\Core\Excel\Models\ExcelConfig;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use DreamFactory\Core\Enums\LicenseLevel;
use DreamFactory\Core\Excel\Services\ExcelService;
use Illuminate\Routing\Router;

use Route;
use Event;


class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        // add migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {

        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'                  => 'excel',
                    'label'                 => 'Excel Service',
                    'description'           => 'Service to manage XLS, XLSX, CSV files.',
                    'group'                 => 'Excel',
                    'subscription_required' => LicenseLevel::SILVER,
                    'config_handler'        => ExcelConfig::class,
                    'factory'               => function ($config) {
                        return new ExcelService($config);
                    },
                ])
            );
        });
    }
}
