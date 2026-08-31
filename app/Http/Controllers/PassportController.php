<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class PassportController extends Controller
{
    private const PUBLIC_EVENT_TYPES = [
        'REGISTERED',
        'PRELIMINARY_ASSESSMENT',
        'RECEIVED',
        'TRANSFER_RECEIVED',
        'DONATION_READY',
        'DONATION_PROOF_RECORDED',
        'VERIFIED_OUTCOME',
        'BATCH_SPLIT',
        'BATCH_SPLIT_CREATED',
        'FINAL_VALUE_CONFIRMED',
    ];

    public function show(string $code)
    {
        $asset = Asset::with(['category.group', 'events' => fn($q) => $q->whereIn('event_type', self::PUBLIC_EVENT_TYPES)])
            ->where('passport_code', $code)->whereNotIn('status', ['cart', 'bulk_draft'])->firstOrFail();
        return view('public.passport', ['asset' => $asset]);
    }

    public function qr(string $code)
    {
        $asset = Asset::where('passport_code', $code)->whereNotIn('status', ['cart', 'bulk_draft'])->firstOrFail();
        $qr = new QrCode(route('passport.show', $asset->passport_code));
        $result = (new SvgWriter())->write($qr);
        return response($result->getString(), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }
}
