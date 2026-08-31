<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('partner_transfers') || !Schema::hasTable('asset_custodies')) {
            return;
        }

        $pendingTransfers = DB::table('partner_transfers')
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        foreach ($pendingTransfers as $transfer) {
            $asset = DB::table('assets')->where('id', $transfer->asset_id)->first();
            if (!$asset || $asset->final_path) {
                continue;
            }

            // Pengalihan hanya valid bila barang masih berada di mitra asal.
            $sourceCustody = DB::table('asset_custodies')
                ->where('asset_id', $transfer->asset_id)
                ->where('partner_profile_id', $transfer->from_partner_id)
                ->whereNull('released_at')
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->first();

            if (!$sourceCustody) {
                continue;
            }

            $previousCustody = DB::table('asset_custodies')
                ->where('asset_id', $transfer->asset_id)
                ->where('partner_profile_id', '!=', $transfer->from_partner_id)
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->first();

            $directBounce = $previousCustody
                && (int) $previousCustody->partner_profile_id === (int) $transfer->to_partner_id;

            $repairRuledOut = false;
            if (($transfer->required_capability ?? null) === 'repair' && Schema::hasTable('asset_assessments')) {
                $assessments = DB::table('asset_assessments')
                    ->where('asset_id', $transfer->asset_id)
                    ->where('assessment_type', 'partner')
                    ->orderByDesc('id')
                    ->get(['answers_json']);

                foreach ($assessments as $assessment) {
                    $answers = json_decode((string) $assessment->answers_json, true) ?: [];
                    $feasible = $answers['repair_feasible'] ?? 'unknown';
                    if (in_array($feasible, ['yes', 'no'], true)) {
                        $repairRuledOut = $feasible === 'no';
                        break;
                    }
                }
            }

            if (!$directBounce && !$repairRuledOut) {
                continue;
            }

            $reason = $directBounce
                ? 'Dibatalkan otomatis saat upgrade v1.0.19 karena pengalihan akan langsung mengembalikan barang ke mitra sebelumnya dan membentuk alur bolak-balik.'
                : 'Dibatalkan otomatis saat upgrade v1.0.19 karena pemeriksaan sebelumnya sudah menyatakan barang tidak layak diperbaiki.';

            DB::table('partner_transfers')->where('id', $transfer->id)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'updated_at' => now(),
            ]);

            DB::table('assets')->where('id', $transfer->asset_id)->whereNull('final_path')->update([
                'status' => 'in_processing',
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('asset_events')) {
                DB::table('asset_events')->insert([
                    'asset_id' => $transfer->asset_id,
                    'actor_user_id' => null,
                    'event_type' => 'TRANSFER_LOOP_BLOCKED',
                    'title' => 'Pengalihan bolak-balik dibatalkan sistem',
                    'description' => $reason . ' Barang tetap berada pada mitra asal untuk ditinjau ulang.',
                    'metadata_json' => json_encode([
                        'transfer_id' => $transfer->id,
                        'from_partner_id' => $transfer->from_partner_id,
                        'to_partner_id' => $transfer->to_partner_id,
                        'required_capability' => $transfer->required_capability ?? null,
                    ], JSON_UNESCAPED_UNICODE),
                    'occurred_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data correction sengaja tidak dibalik. Menghidupkan kembali transfer yang
        // sudah terbukti membentuk loop dapat mengembalikan state yang tidak valid.
    }
};
