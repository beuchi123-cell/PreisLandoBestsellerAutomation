<?php

namespace PreisLandoBestsellerAutomation\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use PreisLandoBestsellerAutomation\Services\BestsellerService;

class BestsellerTestCron implements CronHandler
{
    use Loggable;

    public function handle()
    {
        /** @var ConfigRepository $config */
        $config = pluginApp(ConfigRepository::class);

        if (!$this->isEnabled($config->get('bestseller.testCronEnabled', '0'))) {
            return;
        }

        try {
            /** @var BestsellerService $service */
            $service = pluginApp(BestsellerService::class);

            // true = erzwungener Lauf. Dadurch kann die normale Automatik waehrend des Tests AUS bleiben.
            $service->run(true);
        } catch (\Throwable $e) {
            $this->getLogger(__CLASS__ . '::handle')->error(
                'PreisLandoBestsellerAutomation::testCronError',
                ['message' => $e->getMessage()]
            );
        }
    }

    private function isEnabled($value)
    {
        return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
    }
}
