<?php

namespace PreisLandoBestsellerAutomation\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\Routing\Router;

class BestsellerRouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router, ApiRouter $api)
    {
        $api->version(['v1'], ['middleware' => ['oauth']], function (ApiRouter $api) {
            $api->post(
                'preislando-bestseller/run',
                'PreisLandoBestsellerAutomation\\Api\\Resources\\BestsellerResource@run'
            );
        });
    }
}
