<?php

use App\Jobs\ClearExpiredCallSessions;
use App\Jobs\ClearExpiredLockers;
use App\Jobs\ClearExpiredPipeDevices;
use App\Jobs\ClearExpiredPipeSessions;
use App\Jobs\ClearExpiredSecrets;
use App\Jobs\PurgeCallParticipantMetadata;
use App\Jobs\PurgeMetadataForExpiredMessages;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ClearExpiredSecrets)->daily();
Schedule::job(new ClearExpiredLockers)->hourly();
Schedule::job(new PurgeMetadataForExpiredMessages)->daily();
Schedule::job(new ClearExpiredCallSessions)->daily();
Schedule::job(new PurgeCallParticipantMetadata)->daily();
Schedule::job(new ClearExpiredPipeSessions)->daily();
Schedule::job(new ClearExpiredPipeDevices)->daily();
Schedule::command('cloudflare:reload')->daily();
Schedule::command('sender-identity:reverify')->daily();
