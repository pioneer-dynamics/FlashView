<?php

namespace App\Console\Commands;

use App\Models\Secret;
use Illuminate\Console\Command;
use App\Models\Scopes\ActiveScope;
use Vinkla\Hashids\Facades\Hashids;

class LegalMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legal:metadata {message_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve the metadata for a message.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $secret = Secret::withoutGlobalScope(ActiveScope::class)->findOrFail(Hashids::connection('Secret')->decode($this->argument('message_id'))[0]);

        $this->table([
            'Property', 'Value'
        ],[
            ['Message ID', $secret->hash_id],
            ['Created At', $secret->created_at],
            ['Retreived At', $secret->retrieved_at],
            ['Expires At', $secret->expires_at],
            ['Sent From', $secret->ip_address_sent],
            ['Retrieved From', $secret->ip_address_retreived],
            ['User ID', $secret->user_id],
        ]);
    }
}
