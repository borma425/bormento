<?php

namespace App\Console\Commands;

use App\Models\PendingTask;
use App\Jobs\ProcessPendingTask;
use Illuminate\Console\Command;

class HydratePendingTasks extends Command
{
    protected $signature = 'agent:hydrate-tasks';
    protected $description = 'Restore pending tasks from DB and schedule them for processing';

    public function handle(): int
    {
        $tasks = PendingTask::where('resume_at', '>', now())->get();

        if ($tasks->isEmpty()) {
            $this->info('No pending tasks to hydrate.');
            return 0;
        }

        $this->info("Found {$tasks->count()} pending tasks. Scheduling...");

        foreach ($tasks as $task) {
            $delayMs = max(0, $task->resume_at->diffInMilliseconds(now()));

            ProcessPendingTask::dispatch($task->id, $task->user_id, $task->message, $task->tenant_id)
                ->delay(now()->addMilliseconds($delayMs));

            $this->line("  Task #{$task->id} for user {$task->user_id} scheduled in {$delayMs}ms");
        }

        $this->info('All pending tasks hydrated successfully.');
        return 0;
    }
}
