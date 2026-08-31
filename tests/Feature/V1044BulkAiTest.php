<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AiTopupRequest, Asset, DeviceCategory, IntakeSession, IntakeSessionItem, User};
use App\Services\{AiQuotaService, AiService};
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1044BulkAiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Warga Bulk',
            'email' => 'bulk-v1044@example.test',
            'password' => 'password123',
            'role' => UserRole::USER,
            'whatsapp' => '6281212345678',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
        ]);
    }

    #[Test]
    public function bulk_detection_merges_same_category_and_session_consumes_only_one_bulk_quota(): void
    {
        Storage::fake('public');
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        config()->set('sirkel.ai.api_key', 'test-key');

        Http::fake(['*' => Http::response([
            'output_text' => json_encode([
                'eligibility_status' => 'supported',
                'eligibility_reason' => 'Terlihat beberapa elektronik fisik.',
                'items' => [
                    ['detected_name' => 'Kabel Charger', 'category_code' => 'charger', 'quantity' => 2, 'description' => 'Dua kabel terlihat.', 'unit_observations' => ['Satu terkelupas', 'Satu tampak putus'], 'confidence' => .93],
                    ['detected_name' => 'Kabel Charger', 'category_code' => 'charger', 'quantity' => 1, 'description' => 'Satu kabel lain terlihat.', 'unit_observations' => ['Konektor tampak bengkok'], 'confidence' => .89],
                    ['detected_name' => 'Powerbank', 'category_code' => 'powerbank', 'quantity' => 1, 'description' => 'Bodi powerbank tampak aus.', 'unit_observations' => [], 'confidence' => .91],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'usage' => [],
        ], 200)]);

        $response = $this->actingAs($user)->post(route('user.bulk.store'), [
            'photos' => [UploadedFile::fake()->image('bulk.jpg', 900, 700)],
        ]);

        $session = IntakeSession::where('user_id', $user->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('user.bulk.edit', $session));
        $this->assertNotNull($session->quota_consumed_at);
        $this->assertSame(2, $session->items()->count());

        $charger = $session->items()->with('asset.category')->get()->first(fn ($item) => $item->asset->category->code === 'charger');
        $this->assertNotNull($charger);
        $this->assertSame(3, $charger->asset->quantity);
        $this->assertSame('batch', $charger->asset->tracking_type);
        $this->assertStringContainsString('terkelupas', strtolower($charger->asset->description));
        $this->assertSame(1, app(AiQuotaService::class)->status($user, AiQuotaService::BULK_AI)['used']);
        $this->assertSame(2, app(AiQuotaService::class)->status($user, AiQuotaService::BULK_AI)['remaining']);
    }

    #[Test]
    public function adaptive_questionnaire_is_server_capped_at_fifteen_without_using_fifteen_as_a_target(): void
    {
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        config()->set('sirkel.ai.api_key', 'test-key');
        $charger = DeviceCategory::where('code', 'charger')->firstOrFail();

        $session = IntakeSession::create([
            'user_id' => $user->id,
            'mode' => IntakeSession::MODE_BULK_AI,
            'status' => IntakeSession::STATUS_DRAFT,
            'quota_consumed_at' => now(),
        ]);
        foreach (range(1, 2) as $i) {
            $asset = Asset::create([
                'passport_code' => 'SRK-BULK-Q-'.$i.'-'.strtoupper(str()->random(4)),
                'owner_user_id' => $user->id,
                'device_category_id' => $charger->id,
                'tracking_type' => 'individual',
                'description' => 'Kabel demo '.$i,
                'quantity' => 1,
                'status' => 'bulk_draft',
            ]);
            IntakeSessionItem::create(['intake_session_id' => $session->id, 'asset_id' => $asset->id, 'source' => 'bulk_ai', 'sort_order' => $i]);
        }
        $session->load('items.asset.category.group');
        $targets = $session->items->pluck('asset.public_id')->all();

        $questions = [];
        foreach (range(1, 20) as $i) {
            $questions[] = [
                'text' => 'Pertanyaan fungsi '.$i,
                'type' => 'matrix_single',
                'targets' => $targets,
                'required' => true,
                'signal_key' => 'power_status',
                'signal_map' => (object) [],
                'options' => [
                    ['value' => 'normal', 'label' => 'Berfungsi normal'],
                    ['value' => 'off', 'label' => 'Tidak menyala'],
                    ['value' => 'unknown', 'label' => 'Tidak tahu'],
                ],
            ];
        }

        Http::fake(['*' => Http::response(['output_text' => json_encode(['questions' => $questions], JSON_UNESCAPED_UNICODE), 'usage' => []], 200)]);
        $this->actingAs($user, 'web');
        $result = app(AiService::class)->bulkAdaptiveQuestionnaire($session);

        $this->assertCount(15, $result);
        $this->assertTrue(collect($result)->contains(fn ($question) => ($question['signal_key'] ?? null) === 'hazard_sign'));
        $this->assertTrue(collect($result)->contains(fn ($question) => ($question['signal_key'] ?? null) === 'damage_level'));
        $this->assertTrue(collect($result)->contains(fn ($question) => ($question['signal_key'] ?? null) === 'user_intent'));
        $source = file_get_contents(app_path('Services/AiService.php'));
        $this->assertStringContainsString('JANGAN mengejar jumlah tertentu', $source);
        $this->assertStringContainsString('hard maximum 15', $source);
    }


    #[Test]
    public function adaptive_questionnaire_stays_below_fifteen_when_fewer_questions_are_sufficient(): void
    {
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        config()->set('sirkel.ai.api_key', 'test-key');
        $charger = DeviceCategory::where('code', 'charger')->firstOrFail();

        $session = IntakeSession::create([
            'user_id' => $user->id,
            'mode' => IntakeSession::MODE_BULK_AI,
            'status' => IntakeSession::STATUS_DRAFT,
            'quota_consumed_at' => now(),
        ]);
        $asset = Asset::create([
            'passport_code' => 'SRK-BULK-MIN-'.strtoupper(str()->random(5)),
            'owner_user_id' => $user->id,
            'device_category_id' => $charger->id,
            'tracking_type' => 'batch',
            'description' => 'Tiga kabel charger terlihat dalam satu kelompok.',
            'quantity' => 3,
            'status' => 'bulk_draft',
        ]);
        IntakeSessionItem::create([
            'intake_session_id' => $session->id,
            'asset_id' => $asset->id,
            'source' => 'bulk_ai',
            'sort_order' => 1,
        ]);

        Http::fake(['*' => Http::response([
            'output_text' => json_encode(['questions' => [[
                'text' => 'Apakah kelompok kabel masih dapat digunakan untuk mengisi daya?',
                'type' => 'matrix_single',
                'targets' => [$asset->public_id],
                'required' => true,
                'signal_key' => 'power_status',
                'signal_map' => (object) [],
                'options' => [
                    ['value' => 'normal', 'label' => 'Berfungsi normal'],
                    ['value' => 'partial', 'label' => 'Sebagian / bermasalah'],
                    ['value' => 'off', 'label' => 'Tidak berfungsi'],
                    ['value' => 'unknown', 'label' => 'Tidak tahu'],
                ],
            ]]], JSON_UNESCAPED_UNICODE),
            'usage' => [],
        ], 200)]);

        $this->actingAs($user, 'web');
        $result = app(AiService::class)->bulkAdaptiveQuestionnaire($session);

        $this->assertNotEmpty($result);
        $this->assertLessThan(15, count($result));
        $this->assertTrue(collect($result)->contains(fn ($question) => ($question['signal_key'] ?? null) === 'power_status'));
        $this->assertTrue(collect($result)->contains(fn ($question) => ($question['signal_key'] ?? null) === 'damage_level'));
        $this->assertTrue(collect($result)->contains(fn ($question) => ($question['signal_key'] ?? null) === 'user_intent'));
        $this->assertTrue(collect($result)->contains(fn ($question) => ($question['signal_key'] ?? null) === 'hazard_sign'));
    }

    #[Test]
    public function approved_bulk_topup_extends_session_quota_and_unconsumed_draft_does_not_count(): void
    {
        $user = $this->user();
        IntakeSession::create(['user_id' => $user->id, 'mode' => IntakeSession::MODE_BULK_AI, 'status' => IntakeSession::STATUS_DRAFT]);
        IntakeSession::create(['user_id' => $user->id, 'mode' => IntakeSession::MODE_BULK_AI, 'status' => IntakeSession::STATUS_DRAFT, 'quota_consumed_at' => now()]);

        $quota = app(AiQuotaService::class);
        $this->assertSame(1, $quota->status($user, AiQuotaService::BULK_AI)['used']);
        $this->assertSame(2, $quota->status($user, AiQuotaService::BULK_AI)['remaining']);

        AiTopupRequest::create([
            'user_id' => $user->id,
            'status' => AiTopupRequest::STATUS_APPROVED,
            'bulk_ai_quantity' => 4,
            'bulk_ai_unit_price_idr' => 5000,
            'total_amount_idr' => 20000,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);
        $this->assertSame(6, $quota->status($user, AiQuotaService::BULK_AI)['remaining']);
    }

    #[Test]
    public function bulk_detection_groups_same_category_even_when_standard_catalog_marks_it_as_non_batch(): void
    {
        Storage::fake('public');
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        config()->set('sirkel.ai.api_key', 'test-key');

        Http::fake(['*' => Http::response([
            'output_text' => json_encode([
                'eligibility_status' => 'supported',
                'eligibility_reason' => 'Terlihat dua powerbank.',
                'items' => [[
                    'detected_name' => 'Powerbank',
                    'category_code' => 'powerbank',
                    'quantity' => 2,
                    'description' => 'Dua powerbank terlihat berdampingan.',
                    'unit_observations' => ['Unit pertama tampak aus', 'Unit kedua casing tergores'],
                    'confidence' => .91,
                ]],
            ], JSON_UNESCAPED_UNICODE),
            'usage' => [],
        ], 200)]);

        $this->actingAs($user)->post(route('user.bulk.store'), [
            'photos' => [UploadedFile::fake()->image('powerbank.jpg', 900, 700)],
        ])->assertRedirect();

        $session = IntakeSession::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(1, $session->items()->count());
        $asset = $session->items()->with('asset')->firstOrFail()->asset;
        $this->assertSame(2, $asset->quantity);
        $this->assertSame('batch', $asset->tracking_type);
        $this->assertStringContainsString('unit pertama', strtolower((string) $asset->description));
        $this->assertStringContainsString('unit kedua', strtolower((string) $asset->description));
    }

    #[Test]
    public function manual_bulk_input_merges_same_batch_category_without_spending_another_group_slot(): void
    {
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        $charger = DeviceCategory::where('code', 'charger')->firstOrFail();
        $session = IntakeSession::create([
            'user_id' => $user->id,
            'mode' => IntakeSession::MODE_BULK_AI,
            'status' => IntakeSession::STATUS_DRAFT,
            'quota_consumed_at' => now(),
        ]);
        $asset = Asset::create([
            'passport_code' => 'SRK-MERGE-'.strtoupper(str()->random(6)),
            'owner_user_id' => $user->id,
            'device_category_id' => $charger->id,
            'tracking_type' => 'batch',
            'description' => 'Dua kabel dari foto.',
            'quantity' => 2,
            'status' => 'bulk_draft',
        ]);
        IntakeSessionItem::create([
            'intake_session_id' => $session->id,
            'asset_id' => $asset->id,
            'source' => 'bulk_ai',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->post(route('user.bulk.items.store', $session), [
            'device_category_id' => $charger->id,
            'quantity' => 1,
            'description' => 'Satu kabel tambahan dengan konektor bengkok.',
        ])->assertRedirect();

        $this->assertSame(1, $session->items()->count());
        $asset->refresh();
        $this->assertSame(3, $asset->quantity);
        $this->assertStringContainsString('konektor bengkok', strtolower((string) $asset->description));
    }


    #[Test]
    public function bulk_duplicate_refrigerator_detections_are_collapsed_into_one_group_with_unit_details(): void
    {
        Storage::fake('public');
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        config()->set('sirkel.ai.api_key', 'test-key');

        Http::fake(['*' => Http::response([
            'output_text' => json_encode([
                'eligibility_status' => 'supported',
                'eligibility_reason' => 'Terlihat dua kulkas.',
                'items' => [
                    [
                        'detected_name' => 'Kulkas',
                        'category_code' => 'refrigerator',
                        'quantity' => 1,
                        'description' => 'Kulkas besar dua pintu dengan dispenser di bagian depan.',
                        'unit_observations' => [],
                        'confidence' => .94,
                    ],
                    [
                        'detected_name' => 'Kulkas',
                        'category_code' => 'refrigerator',
                        'quantity' => 1,
                        'description' => 'Kulkas besar dengan kompartemen atas dan bawah serta panel kecil di bagian depan.',
                        'unit_observations' => [],
                        'confidence' => .92,
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'usage' => [],
        ], 200)]);

        $this->actingAs($user)->post(route('user.bulk.store'), [
            'photos' => [UploadedFile::fake()->image('dua-kulkas.jpg', 900, 700)],
        ])->assertRedirect();

        $session = IntakeSession::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(1, $session->items()->count());
        $asset = $session->items()->with('asset.category')->firstOrFail()->asset;
        $this->assertSame('refrigerator', $asset->category->code);
        $this->assertSame(2, $asset->quantity);
        $this->assertSame('batch', $asset->tracking_type);
        $this->assertStringContainsString('1. Kulkas besar dua pintu', $asset->description);
        $this->assertStringContainsString('2. Kulkas besar dengan kompartemen', $asset->description);
    }

}
