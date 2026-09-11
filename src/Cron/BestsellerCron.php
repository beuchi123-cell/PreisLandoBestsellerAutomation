<?php

namespace PreisLandoBestsellerAutomation\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use PreisLandoBestsellerAutomation\Services\BestsellerService;

class BestsellerCron implements CronHandler
{
    use Loggable;

    public function handle()
    {
        /** @var ConfigRepository $config */
        $config = pluginApp(ConfigRepository::class);

        // Solange der 15-Minuten-Test aktiv ist, wird der Tageslauf bewusst uebersprungen.
        if ($this->isEnabled($config->get('bestseller.testCronEnabled', '0'))) {
            return;
        }

        try {
            /** @var BestsellerService $service */
            $service = pluginApp(BestsellerService::class);
            $service->run(false);
        } catch (\Throwable $e) {
            $this->getLogger(__CLASS__ . '::handle')->error(
                'PreisLandoBestsellerAutomation::dailyCronError',
                ['message' => $e->getMessage()]
            );
        }
    }

    private function isEnabled($value)
    {
        return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
    }
}
