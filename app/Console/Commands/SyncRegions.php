<?php
namespace App\Console\Commands;
use App\Services\RegionService;
use Illuminate\Console\Command;
class SyncRegions extends Command
{
    protected $signature = 'sirkel:sync-regions';
    protected $description = 'Synchronize Indonesian administrative regions using the configured BinderByte adapter';
    public function handle(): int
    {
        $r = app(RegionService::class)->syncFromBinderByte();
        $r['ok'] ? $this->info($r['message']) : $this->warn($r['message']);
        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
