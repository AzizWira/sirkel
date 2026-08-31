<?php
namespace App\Services;
use App\Models\Asset;
use App\Models\AssetEvent;
class AssetEventService
{
    public function add(Asset $asset, string $type, string $title, ?string $description = null, array $meta = []): AssetEvent
    {
        return AssetEvent::create(['asset_id' => $asset->id, 'actor_user_id' => auth()->id(), 'event_type' => $type, 'title' => $title, 'description' => $description, 'metadata_json' => $meta ?: null, 'occurred_at' => now()]);
    }
}
