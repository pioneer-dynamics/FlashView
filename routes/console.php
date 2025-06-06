<?php

use App\Jobs\ClearExpiredSecrets;
use App\Jobs\PurgeMetadataForExpiredMessages;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new ClearExpiredSecrets)->daily();
Schedule::job(new PurgeMetadataForExpiredMessages)->daily();
Schedule::command('cloudflare:reload')->daily();
