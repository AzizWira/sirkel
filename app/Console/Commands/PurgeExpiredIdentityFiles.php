<?php
namespace App\Console\Commands;

use App\Models\{PartnerProfile, User};
use App\Notifications\SirkelNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Storage};

class PurgeExpiredIdentityFiles extends Command
{
    protected $signature = 'sirkel:purge-identity-files';
    protected $description = 'Delete partner KTP files whose retention period has expired';

    public function handle(): int
    {
        $deleted = 0;
        $failed = 0;
        PartnerProfile::whereNotNull('identity_file_path')->whereNull('identity_deleted_at')
            ->where('identity_delete_after', '<=', now())->chunkById(50, function ($profiles) use (&$deleted, &$failed) {
                foreach ($profiles as $profile) {
                    try {
                        $path = $profile->identity_file_path;
                        if (Storage::disk('local')->exists($path) && !Storage::disk('local')->delete($path)) {
                            throw new \RuntimeException('Storage delete returned false');
                        }
                        $profile->update(['identity_file_path' => null, 'identity_deleted_at' => now()]);
                        $deleted++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        if ($failed && Cache::add('sirkel:ktp-purge-failure-alert', true, now()->addHours(12))) {
            User::where('role', 'admin')->each(fn($admin) => $admin->notify(
                new SirkelNotification('Peringatan retensi KTP', "{$failed} file KTP belum berhasil dihapus dan akan dicoba lagi pada jadwal berikutnya. Tinjau data mitra terkait jika masalah berulang.", route('admin.partners.index'), false)
            ));
        }

        $this->info("Deleted: {$deleted}; failed: {$failed}");
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
