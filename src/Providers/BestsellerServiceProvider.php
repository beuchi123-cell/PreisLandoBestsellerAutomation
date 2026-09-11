<?php

namespace PreisLandoBestsellerAutomation\Providers;

use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Plugin\ServiceProvider;
use PreisLandoBestsellerAutomation\Cron\BestsellerCron;
use PreisLandoBestsellerAutomation\Cron\BestsellerTestCron;

class BestsellerServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Keine Backend-Seite und keine REST-Route noetig.
    }

    public function boot(CronContainer $cronContainer)
    {
        // Fuer die normale Nutzung einmal taeglich.
        $cronContainer->add(CronContainer::DAILY, BestsellerCron::class, 0);

        // Nur fuer die Inbetriebnahme. Der Handler macht nichts, solange
        // "15-Minuten-Testlauf aktiv" in der Konfiguration auf Nein steht.
        $cronContainer->add(CronContainer::EVERY_FIFTEEN_MINUTES, BestsellerTestCron::class, 0);
    }
}
