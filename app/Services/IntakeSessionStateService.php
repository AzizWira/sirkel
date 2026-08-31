<?php

namespace App\Services;

use App\Models\{Asset, IntakeSession, IntakeSessionItem};
use Illuminate\Support\Collection;

class IntakeSessionStateService
{
    public const ITEM_ACTIONABLE = 'actionable';
    public const ITEM_IN_PROGRESS = 'in_progress';
    public const ITEM_FINISHED = 'finished';
    public const ITEM_UNAVAILABLE = 'unavailable';

    /**
     * Return review items that still need their first handover request.
     * Once an asset has entered a handover lifecycle, the intake session must
     * not offer the old review/location flow again.
     */
    public function actionableItems(IntakeSession $session): Collection
    {
        $session->loadMissing(['items.asset.requests']);

        return $session->items
            ->filter(fn (IntakeSessionItem $item) => $this->stateForItem($item) === self::ITEM_ACTIONABLE)
            ->values();
    }

    public function stateForItem(IntakeSessionItem $item): string
    {
        $item->loadMissing('asset.requests');
        $asset = $item->asset;

        if (! $asset) {
            return self::ITEM_UNAVAILABLE;
        }

        if ($asset->final_path) {
            return self::ITEM_FINISHED;
        }

        // A session review is only for creating the FIRST handover. Any asset
        // that has already created a request continues from Asset/Activity,
        // even if that request later becomes terminal (declined/cancelled/etc.).
        if ($asset->core_locked_at || $asset->requests->isNotEmpty()) {
            return self::ITEM_IN_PROGRESS;
        }

        if (! $item->assessment_completed_at || ! $asset->preliminary_path) {
            return self::ITEM_UNAVAILABLE;
        }

        return self::ITEM_ACTIONABLE;
    }

    /**
     * Repair stale review sessions left behind by earlier single-item handover
     * flows. A review session is complete when none of its items still needs a
     * first handover request.
     */
    public function reconcile(IntakeSession $session): IntakeSession
    {
        if ($session->status !== IntakeSession::STATUS_REVIEW) {
            return $session;
        }

        // Re-read item/request relations: a handover may have been created earlier
        // in the same HTTP request while these relations were already loaded.
        $session->load(['items.asset.requests']);
        if ($session->items->isEmpty()) {
            return $session;
        }

        $states = $session->items->map(fn (IntakeSessionItem $item) => $this->stateForItem($item));
        $allAlreadyContinued = $states->every(fn (string $itemState) => in_array($itemState, [
            self::ITEM_IN_PROGRESS,
            self::ITEM_FINISHED,
        ], true));

        if ($allAlreadyContinued) {
            $session->forceFill([
                'status' => IntakeSession::STATUS_COMPLETED,
                'completed_at' => $session->completed_at ?: now(),
            ])->save();
        }

        return $session;
    }

    public function reconcileForAsset(Asset $asset): void
    {
        IntakeSession::query()
            ->where('status', IntakeSession::STATUS_REVIEW)
            ->whereHas('items', fn ($query) => $query->where('asset_id', $asset->id))
            ->get()
            ->each(fn (IntakeSession $session) => $this->reconcile($session));
    }

}
