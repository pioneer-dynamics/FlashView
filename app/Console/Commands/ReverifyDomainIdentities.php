<?php

namespace App\Console\Commands;

use App\Models\SenderIdentity;
use App\Services\DomainVerificationService;
use Illuminate\Console\Command;

class ReverifyDomainIdentities extends Command
{
    protected $signature = 'sender-identity:reverify';

    protected $description = 'Re-verify domain sender identities that have not been checked in 3 months';

    public function __construct(private DomainVerificationService $verificationService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $passed = 0;
        $failed = 0;

        SenderIdentity::query()
            ->where('type', 'domain')
            ->whereNotNull('verified_at')
            ->where('verified_at', '<', now()->subMonths(3))
            ->each(function (SenderIdentity $identity) use (&$passed, &$failed) {
                if ($this->verificationService->verify($identity)) {
                    $identity->update(['verified_at' => now()]);
                    $passed++;
                } else {
                    $identity->update(['verified_at' => null]);
                    $failed++;
                }
            });

        $this->info("Re-verification complete: {$passed} passed, {$failed} failed.");
    }
}
