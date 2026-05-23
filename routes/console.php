<?php

use App\Jobs\RelayMaestroProbeJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('relay:maestro-probe {--fail : Queue a failing probe job instead of a successful one}', function () {
    $probeId = (string) Str::uuid();
    $shouldFail = (bool) $this->option('fail');

    dispatch(new RelayMaestroProbeJob($probeId, $shouldFail))
        ->onQueue((string) config('relay.delivery.queue', 'relay-deliveries'));

    $this->info(sprintf(
        'Queued Relay Maestro probe job [%s] on [%s]. Mode: %s',
        $probeId,
        (string) config('relay.delivery.queue', 'relay-deliveries'),
        $shouldFail ? 'fail' : 'success'
    ));
})->purpose('Queue a small relay probe job for Maestro worker/event testing');
