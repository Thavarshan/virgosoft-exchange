<?php

use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Process realtime broadcasts before the lower-priority email queue locally.
DevCommands::artisan(
    'queue:listen --queue=default,emails --tries=3 --timeout=60',
    'queue',
);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
