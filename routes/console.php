<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Walk past-due invoices once a day and enqueue the next due dunning step.
Schedule::command('dunning:advance')->dailyAt('08:00');
