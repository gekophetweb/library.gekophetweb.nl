<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prunable models: used login codes and lapsed share links. Runs on the `scheduler`
// service (guidelines §8).
Schedule::command('model:prune')->daily();
