<?php

namespace App\Console\Commands;

use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Vinkla\Hashids\Facades\Hashids;

class LegalMetadata extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legal:metadata {message : The message id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve the metadata for a message.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $secret = rescue(fn () => Secret::withoutGlobalScope(ActiveScope::class)->find(Hashids::connection('Secret')->decode($this->argument('message'))[0]));

        if (! $secret) {
            $this->fail('Message not found.');
        }

        $this->table([
            'Property', 'Value',
        ], [
            ['Message ID', $secret->hash_id],
            ['Created At', $secret->created_at],
            ['Retreived At', $secret->retrieved_at],
            ['Expires At', $secret->expires_at],
            ['Sent From', $secret->ip_address_sent],
            ['Retrieved From', $secret->ip_address_retrieved],
            ['User ID', $secret->user_id],
        ]);
    }
}
