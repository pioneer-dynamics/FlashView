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
            'message_id', 'created_at', 'expires_at', 'retrieved_at', 'ip_address_sent', 'ip_address_retrieved', 'user_id'
        ],[
            $secret->hash_id, $secret->created_at, $secret->expires_at, $secret->retrieved_at, $secret->ip_address_sent, $secret->ip_address_retrieved, $secret->user_id
        ]);
    }
}
