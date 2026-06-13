<?php
namespace Widerrufsbutton\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class WiderrufsbuttonRouteServiceProvider
 * @package Widerrufsbutton\Providers
 */
class WiderrufsbuttonRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        // Step 1: Show the withdrawal form (GET)
        $router->get(
            'widerruf',
            'Widerrufsbutton\Controllers\WiderrufController@showForm'
        );

        // Step 2: Process form input -> show confirmation page (POST)
        $router->post(
            'widerruf/confirm',
            'Widerrufsbutton\Controllers\WiderrufController@confirm'
        );

        // Step 3: Final submission -> create return + send email (POST)
        $router->post(
            'widerruf/submit',
            'Widerrufsbutton\Controllers\WiderrufController@submit'
        );

        // AJAX endpoint for order lookup (GET)
        $router->get(
            'widerruf/lookup',
            'Widerrufsbutton\Controllers\WiderrufController@lookupOrder'
        );
    }
}
