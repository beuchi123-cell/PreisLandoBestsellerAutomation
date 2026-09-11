<?php

namespace PreisLandoBestsellerAutomation\Providers;

use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Plugin\ServiceProvider;
use PreisLandoBestsellerAutomation\Cron\BestsellerCron;

class BestsellerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->getApplication()->register(BestsellerRouteServiceProvider::class);
    }

    public function boot(CronContainer $cronContainer)
    {
        $cronContainer->add(CronContainer::DAILY, BestsellerCron::class, 0);
    }
}
