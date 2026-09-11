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
            $api->get(
                'preislando-bestseller/v022/ping',
                'PreisLandoBestsellerAutomation\\Api\\Resources\\BestsellerResource@ping'
            );

            $api->post(
                'preislando-bestseller/v022/run',
                'PreisLandoBestsellerAutomation\\Api\\Resources\\BestsellerResource@run'
            );

            $api->post(
                'preislando-bestseller/v022/run-test',
                'PreisLandoBestsellerAutomation\\Api\\Resources\\BestsellerResource@runTest'
            );

            $api->post(
                'preislando-bestseller/v022/run-live',
                'PreisLandoBestsellerAutomation\\Api\\Resources\\BestsellerResource@runLive'
            );
        });
    }
}
