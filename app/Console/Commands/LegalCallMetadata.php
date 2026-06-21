<?php

namespace App\Console\Commands;

use App\Models\CallSession;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

class LegalCallMetadata extends Command implements PromptsForMissingInput
{
    protected $signature = 'legal:call-metadata {hash_id : The hash ID (join code) of the call session}';

    protected $description = 'Retrieve the metadata for a call session.';

    public function handle(): void
    {
        try {
            $session = CallSession::findByHashID($this->argument('hash_id'));
        } catch (\Throwable) {
            $this->fail('Call session not found.');
        }

        $session->load('participants');

        $this->info('Session');
        $this->table(['Property', 'Value'], [
            ['Bridge Number', $session->hash_id],
            ['Starts At', $session->starts_at],
            ['Ends At', $session->ends_at],
            ['Max Participants', $session->max_participants],
        ]);

        $this->newLine();
        $this->info('Participants');
        $this->table(
            ['Participant ID', 'Joined At', 'Left At', 'IP Address'],
            $session->participants->map(fn ($p) => [
                $p->id,
                $p->joined_at,
                $p->left_at ?? '—',
                $p->ip_address,
            ])->toArray()
        );
    }
}
