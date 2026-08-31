<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Asset, DeviceCategory, IntakeSession, User};
use App\Services\QuestionnaireService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1044CartAndStandardIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Warga Keranjang',
            'email' => 'cart-v1044@example.test',
            'password' => 'password123',
            'role' => UserRole::USER,
            'whatsapp' => '6281212345678',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'district' => 'Gunung Anyar',
            'village' => 'Gunung Anyar Tambak',
        ]);
    }

    private function cartAsset(User $user, DeviceCategory $category, int $index): Asset
    {
        return Asset::create([
            'passport_code' => 'SRK-CART-'.$index.'-'.strtoupper(str()->random(5)),
            'owner_user_id' => $user->id,
            'device_category_id' => $category->id,
            'tracking_type' => 'individual',
            'description' => 'Barang demo di keranjang untuk pengujian pemeriksaan bersama.',
            'quantity' => 1,
            'status' => 'cart',
            'origin_district' => 'Gunung Anyar',
            'origin_village' => 'Gunung Anyar Tambak',
        ]);
    }

    private function validAnswers(Asset $asset): array
    {
        $template = app(QuestionnaireService::class)->forAsset($asset, 'citizen');
        $answers = [];
        foreach ($template->questions as $question) {
            if (! $question->required) continue;
            if ($question->type === 'text') {
                $answers[$question->code] = 'Kondisi sudah dijelaskan untuk kebutuhan pemeriksaan.';
            } elseif ($question->type === 'multi') {
                $answers[$question->code] = [(string) $question->options->first()->value];
            } else {
                $answers[$question->code] = (string) $question->options->first()->value;
            }
        }
        return $answers;
    }

    #[Test]
    public function cart_is_unlimited_but_standard_process_accepts_at_most_three_groups(): void
    {
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        $category = DeviceCategory::where('code', 'toaster')->firstOrFail();
        $assets = collect(range(1, 4))->map(fn ($i) => $this->cartAsset($user, $category, $i));

        $this->actingAs($user)
            ->from(route('user.cart.index'))
            ->post(route('user.cart.process'), ['asset_ids' => $assets->pluck('id')->all()])
            ->assertRedirect(route('user.cart.index'))
            ->assertSessionHasErrors('asset_ids');

        $this->assertSame(4, Asset::where('owner_user_id', $user->id)->where('status', 'cart')->count());

        $chosen = $assets->take(3)->pluck('id')->all();
        $response = $this->actingAs($user)->post(route('user.cart.process'), ['asset_ids' => $chosen]);
        $session = IntakeSession::where('user_id', $user->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('user.intake.standard.show', $session));
        $this->assertSame(IntakeSession::MODE_STANDARD, $session->mode);
        $this->assertSame(3, $session->items()->count());
        $this->assertSame(1, Asset::where('owner_user_id', $user->id)->where('status', 'cart')->count());
        $this->assertSame(3, Asset::whereIn('id', $chosen)->where('status', 'registered')->count());
    }

    #[Test]
    public function completed_and_partial_answers_remain_persisted_when_moving_between_items(): void
    {
        $this->seed(MasterDataSeeder::class);
        $user = $this->user();
        $category = DeviceCategory::where('code', 'toaster')->firstOrFail();
        $assets = collect(range(1, 3))->map(fn ($i) => $this->cartAsset($user, $category, $i));

        $this->actingAs($user)->post(route('user.cart.process'), ['asset_ids' => $assets->pluck('id')->all()]);
        $session = IntakeSession::where('user_id', $user->id)->latest('id')->firstOrFail();
        $items = $session->items()->with('asset')->orderBy('sort_order')->get();

        $firstAnswers = $this->validAnswers($items[0]->asset);
        $this->actingAs($user)
            ->post(route('user.intake.standard.complete-item', [$session, $items[0]]), ['answers' => $firstAnswers])
            ->assertRedirect(route('user.intake.standard.show', $session));

        $this->assertNotNull($items[0]->fresh()->assessment_completed_at);
        $this->assertSame($firstAnswers, $items[0]->fresh()->draft_answers_json);

        $template = app(QuestionnaireService::class)->forAsset($items[1]->asset, 'citizen');
        $question = $template->questions->first(fn ($q) => $q->type !== 'text' && $q->options->isNotEmpty());
        $partial = [$question->code => (string) $question->options->first()->value];

        $this->actingAs($user)
            ->postJson(route('user.intake.standard.autosave', [$session, $items[1]]), ['answers' => $partial])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame($partial, $items[1]->fresh()->draft_answers_json);
        $this->assertSame($firstAnswers, $items[0]->fresh()->draft_answers_json);
        $this->assertNotNull($items[0]->fresh()->assessment_completed_at);
    }
}
