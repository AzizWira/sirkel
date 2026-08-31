<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('handover_requests') && !Schema::hasColumn('handover_requests', 'handover_type')) {
            Schema::table('handover_requests', function (Blueprint $table) {
                $table->string('handover_type', 30)->nullable()->after('method')->index();
            });

            // Snapshot tujuan penyerahan per request. Data lama hanya dapat dibackfill dari nilai terakhir pada asset.
            DB::table('handover_requests')
                ->whereNull('handover_type')
                ->orderBy('id')
                ->get(['id', 'asset_id'])
                ->each(function ($request) {
                    $type = DB::table('assets')->where('id', $request->asset_id)->value('handover_type');
                    DB::table('handover_requests')->where('id', $request->id)->update(['handover_type' => $type]);
                });
        }

        if (Schema::hasTable('partner_transfers')) {
            Schema::table('partner_transfers', function (Blueprint $table) {
                if (!Schema::hasColumn('partner_transfers', 'required_capability')) {
                    $table->string('required_capability', 40)->nullable()->after('requested_by_user_id')->index();
                }
                if (!Schema::hasColumn('partner_transfers', 'declined_at')) {
                    $table->timestamp('declined_at')->nullable()->after('received_by_user_id');
                }
                if (!Schema::hasColumn('partner_transfers', 'decline_reason')) {
                    $table->text('decline_reason')->nullable()->after('declined_at');
                }
                if (!Schema::hasColumn('partner_transfers', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('decline_reason');
                }
                if (!Schema::hasColumn('partner_transfers', 'cancel_reason')) {
                    $table->text('cancel_reason')->nullable()->after('cancelled_at');
                }
            });
        }

        // Versi lama pernah memperlakukan expiry penawaran sebagai status terminal request.
        // Di flow baru yang kedaluwarsa hanya versi penawarannya; request kembali ke tahap diterima mitra.
        if (Schema::hasTable('handover_requests')) {
            $expiredRequests = DB::table('handover_requests')->where('status', 'expired')->get(['id', 'asset_id']);
            foreach ($expiredRequests as $request) {
                DB::table('handover_requests')->where('id', $request->id)->update(['status' => 'accepted']);
                if (Schema::hasTable('assets')) {
                    $asset = DB::table('assets')->where('id', $request->asset_id)->first(['core_locked_at', 'final_path']);
                    if ($asset && !$asset->core_locked_at && !$asset->final_path) {
                        DB::table('assets')->where('id', $request->asset_id)->update(['status' => 'partner_accepted']);
                    }
                }
            }
        }

        if (Schema::hasTable('assets') && Schema::hasTable('partner_transfers')) {
            // Pada versi lama status "transferred" dapat dipasang sejak transfer baru diajukan,
            // padahal custody belum berpindah. Normalisasi pending transfer ke status yang eksplisit.
            $pendingAssetIds = DB::table('partner_transfers')->where('status', 'pending')->pluck('asset_id')->unique();
            if ($pendingAssetIds->isNotEmpty()) {
                DB::table('assets')
                    ->whereIn('id', $pendingAssetIds)
                    ->where('status', 'transferred')
                    ->whereNull('final_path')
                    ->update(['status' => 'transfer_pending']);
            }
        }

        if (Schema::hasTable('assets')) {
            $statusMap = [
                'REUSED' => 'reused',
                'REPAIRED' => 'repaired',
                'DONATED' => 'donated',
                'PARTS_RECOVERED' => 'parts_recovered',
                'RECOVERY_CONFIRMED' => 'recovery_confirmed',
                'RETURNED_TO_OWNER' => 'returned',
                'UNVERIFIED_FINAL_TREATMENT' => 'unverified',
            ];
            foreach ($statusMap as $path => $status) {
                DB::table('assets')->where('final_path', $path)->update(['status' => $status]);
            }
        }

        if (Schema::hasTable('circular_rules')) {
            // Jangan memakai model Eloquent di migration historis. Model dapat berubah pada
            // versi berikutnya (mis. v1.0.39 menambahkan event public_id) sementara fresh
            // migration masih berada pada schema v1.0.16 dan kolom tersebut belum ada.
            $name = 'Donasi untuk perangkat layak sesuai prioritas warga';
            $now = now();
            $values = [
                'priority' => 15,
                'active' => true,
                'conditions_json' => json_encode([
                    'power_status' => 'normal',
                    'damage_level' => ['none', 'minor'],
                    'user_intent' => 'donate',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'result_path' => 'DONATION',
                'explanation_template' => 'Perangkat masih layak digunakan dan warga memprioritaskan donasi, sehingga jalur guna ulang/donasi menjadi rekomendasi awal.',
                'updated_at' => $now,
            ];

            if (DB::table('circular_rules')->where('name', $name)->exists()) {
                DB::table('circular_rules')->where('name', $name)->update($values);
            } else {
                DB::table('circular_rules')->insert([
                    'name' => $name,
                    ...$values,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('circular_rules')) {
            DB::table('circular_rules')->where('name', 'Donasi untuk perangkat layak sesuai prioritas warga')->delete();
        }

        if (Schema::hasTable('handover_requests') && Schema::hasColumn('handover_requests', 'handover_type')) {
            Schema::table('handover_requests', function (Blueprint $table) {
                $table->dropColumn('handover_type');
            });
        }

        if (Schema::hasTable('partner_transfers')) {
            Schema::table('partner_transfers', function (Blueprint $table) {
                $columns = collect(['required_capability', 'declined_at', 'decline_reason', 'cancelled_at', 'cancel_reason'])
                    ->filter(fn($column) => Schema::hasColumn('partner_transfers', $column))
                    ->all();
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
