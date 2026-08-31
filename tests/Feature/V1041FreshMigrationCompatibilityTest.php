<?php

namespace Tests\Feature;

use App\Models\CircularRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V1041FreshMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fresh_database_reaches_opaque_id_schema_without_historical_model_event_collision(): void
    {
        $this->assertTrue(Schema::hasColumn('circular_rules', 'public_id'));

        $rules = CircularRule::query()->orderBy('id')->get();

        $this->assertGreaterThan(0, $rules->count());
        $this->assertSame(0, CircularRule::query()->whereNull('public_id')->count());

        foreach ($rules as $rule) {
            $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $rule->public_id);
        }

        // Setelah migration v1.0.39 selesai, event model versi terbaru memang harus aktif.
        // Ini membuktikan fix v1.0.41 hanya mengisolasi migration historis, bukan mematikan
        // opaque ID untuk record baru.
        $created = CircularRule::query()->create([
            'name' => 'Regression rule opaque ID v1.0.41',
            'priority' => 999,
            'active' => false,
            'conditions_json' => ['user_intent' => 'safe_handover'],
            'result_path' => 'TECHNICAL_ASSESSMENT',
            'explanation_template' => 'Rule khusus regression test.',
        ]);

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $created->public_id);
    }
}
