<?php

namespace PreisLandoBestsellerAutomation\Api\Resources;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Response;
use PreisLandoBestsellerAutomation\Services\BestsellerService;

class BestsellerResource extends Controller
{
    /** @var Response */
    private $response;

    /** @var BestsellerService */
    private $service;

    public function __construct(Response $response, BestsellerService $service)
    {
        $this->response = $response;
        $this->service = $service;
    }

    public function ping(): Response
    {
        return $this->response->json([
            'ok' => true,
            'plugin' => 'PreisLandoBestsellerAutomation',
            'version' => '0.2.4',
            'message' => 'REST-Verbindung zum Plugin funktioniert.'
        ], 200);
    }

    public function run(): Response
    {
        return $this->runService(null);
    }

    public function runTest(): Response
    {
        return $this->runService(true);
    }

    public function runLive(): Response
    {
        return $this->runService(false);
    }

    private function runService($dryRunOverride): Response
    {
        try {
            $result = $this->service->run(true, $dryRunOverride);
            $result['pluginVersion'] = '0.2.4';
            return $this->response->json($result, 200);
        } catch (\Throwable $e) {
            return $this->response->json([
                'ok' => false,
                'pluginVersion' => '0.2.4',
                'message' => 'Service-Fehler: ' . $e->getMessage()
            ], 500);
        }
    }
}
