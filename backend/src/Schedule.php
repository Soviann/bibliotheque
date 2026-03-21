<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as ScheduleConfig;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Centralise toutes les tâches planifiées de l'application.
 *
 * Remplace le planificateur de tâches du NAS Synology.
 */
#[AsSchedule('default')]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): ScheduleConfig
    {
        $schedule = new ScheduleConfig();
        $schedule->stateful($this->cache);

        // Quotidien — avant 9h pour épuiser le quota Gemini (reset à minuit PT = 9h Paris)
        // auto-enrich : mar-sam (exclut dim+lun réservés aux tâches hebdo Gemini)
        $schedule->add(RecurringMessage::cron('0 3-8 * * 2-6', new RunCommandMessage('app:auto-enrich')));
        $schedule->add(RecurringMessage::cron('0 4 * * *', new RunCommandMessage('app:check-new-releases')));
        $schedule->add(RecurringMessage::cron('0 5 * * *', new RunCommandMessage('app:download-covers')));

        // Hebdomadaire — dimanche et lundi, quota Gemini dédié
        $schedule->add(RecurringMessage::cron('0 3-8 * * 0', new RunCommandMessage('app:detect-missing-tomes')));
        $schedule->add(RecurringMessage::cron('0 3-8 * * 1', new RunCommandMessage('app:check-author-releases')));

        // Mensuel (1er du mois)
        $schedule->add(RecurringMessage::cron('0 1 1 * *', new RunCommandMessage('app:purge-deleted')));
        $schedule->add(RecurringMessage::cron('0 2 1 * *', new RunCommandMessage('app:purge-notifications --days=90')));

        return $schedule;
    }
}
