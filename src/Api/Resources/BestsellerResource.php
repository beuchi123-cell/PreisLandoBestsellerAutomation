<?php

namespace PreisLandoBestsellerAutomation\Api\Resources;

use Plenty\Plugin\ApiResource;
use PreisLandoBestsellerAutomation\Services\BestsellerService;

class BestsellerResource extends ApiResource
{
    /**
     * Manueller Lauf entsprechend der Plugin-Einstellung "Testmodus".
     */
    public function run()
    {
        /** @var BestsellerService $service */
        $service = pluginApp(BestsellerService::class);
        return $this->response->json($service->run(true));
    }

    /**
     * Sicherer Testlauf: wertet aus, aendert aber niemals Tags.
     */
    public function runTest()
    {
        /** @var BestsellerService $service */
        $service = pluginApp(BestsellerService::class);
        return $this->response->json($service->run(true, true));
    }

    /**
     * Manueller Live-Lauf: setzt und entfernt Bestseller-Tags.
     */
    public function runLive()
    {
        /** @var BestsellerService $service */
        $service = pluginApp(BestsellerService::class);
        return $this->response->json($service->run(true, false));
    }
}
