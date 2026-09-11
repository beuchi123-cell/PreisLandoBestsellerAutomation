<?php

namespace PreisLandoBestsellerAutomation\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler;
use PreisLandoBestsellerAutomation\Services\BestsellerService;

class BestsellerCron implements CronHandler
{
    public function handle()
    {
        /** @var BestsellerService $service */
        $service = pluginApp(BestsellerService::class);
        $service->run(false);
    }
}
