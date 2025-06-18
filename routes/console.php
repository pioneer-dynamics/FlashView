<?php

use App\Jobs\ClearExpiredSecrets;
use App\Jobs\PurgeMetadataForExpiredMessages;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ClearExpiredSecrets)->daily();
Schedule::job(new PurgeMetadataForExpiredMessages)->daily();
Schedule::command('cloudflare:reload')->daily();
