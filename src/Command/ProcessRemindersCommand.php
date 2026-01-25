<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReminderSchedulerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reminders:process',
    description: 'Process and send task reminders (due soon, overdue, due today)',
)]
final class ProcessRemindersCommand extends Command
{
    public function __construct(
        private readonly ReminderSchedulerService $reminderSchedulerService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'due-today',
                null,
                InputOption::VALUE_NONE,
                'Only process due-today notifications (typically run once in the morning)'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be done without actually sending notifications'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $dueTodayOnly = $input->getOption('due-today');

        if ($dryRun) {
            $io->note('Dry run mode - no notifications will be sent');
        }

        if ($dueTodayOnly) {
            $io->section('Processing due-today notifications');

            if ($dryRun) {
                $io->success('Dry run complete - no notifications sent');

                return Command::SUCCESS;
            }

            $count = $this->reminderSchedulerService->processDueTodayNotifications();
            $io->success(sprintf('Sent %d due-today notification(s)', $count));

            return Command::SUCCESS;
        }

        $io->section('Processing all reminders');

        if ($dryRun) {
            $io->success('Dry run complete - no notifications sent');

            return Command::SUCCESS;
        }

        $stats = $this->reminderSchedulerService->processReminders();

        $io->table(
            ['Type', 'Notifications Sent'],
            [
                ['Due Soon', $stats['dueSoon']],
                ['Overdue', $stats['overdue']],
                ['Due Today', $stats['dueToday']],
            ]
        );

        $total = array_sum($stats);
        $io->success(sprintf('Processed reminders: %d notification(s) sent', $total));

        return Command::SUCCESS;
    }
}
