<?php

namespace App\Jobs;

use App\Services\MessageCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMessageQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 minutes max

    public function __construct(
        public string $userId,
        public ?string $messageId = null,
        public ?int $tenantId = null,
    ) {}

    public function handle(MessageCoordinator $coordinator): void
    {
        if ($this->tenantId) {
            $tenant = \App\Models\Tenant::find($this->tenantId);
            if ($tenant) {
                app()->instance(\App\Models\Tenant::class, $tenant);
            }
        }
        
        $coordinator->processQueue($this->userId, $this->messageId);
    }
}
