<?php

use Illuminate\Support\Facades\{Artisan, Log, Schedule};

Artisan::command('sirkel:about', function () {
    $this->info('SIRKEL - Sistem Sirkular Elektronik Kota');
});

Schedule::command('sirkel:purge-identity-files')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('Scheduled KTP deletion failed.');
    });

Schedule::call(function () {
    app(\App\Services\OfferLifecycleService::class)->expireOverdue();
})
    ->name('sirkel-expire-offers')
    ->everyFiveMinutes()
    ->withoutOverlapping();
