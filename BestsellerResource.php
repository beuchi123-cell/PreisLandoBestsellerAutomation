<?php

namespace PreisLandoBestsellerAutomation\Api\Resources;

use Plenty\Plugin\ApiResource;
use PreisLandoBestsellerAutomation\Services\BestsellerService;

class BestsellerResource extends ApiResource
{
    public function run()
    {
        /** @var BestsellerService $service */
        $service = pluginApp(BestsellerService::class);
        return $this->response->json($service->run(true));
    }
}
