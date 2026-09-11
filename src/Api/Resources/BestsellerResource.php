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

    /**
     * Manueller Lauf entsprechend der Plugin-Einstellung "Testmodus".
     */
    public function run(): Response
    {
        return $this->response->json($this->service->run(true), 200);
    }

    /**
     * Sicherer Testlauf: wertet aus, aendert aber niemals Tags.
     */
    public function runTest(): Response
    {
        return $this->response->json($this->service->run(true, true), 200);
    }

    /**
     * Manueller Live-Lauf: setzt und entfernt Bestseller-Tags.
     */
    public function runLive(): Response
    {
        return $this->response->json($this->service->run(true, false), 200);
    }
}
