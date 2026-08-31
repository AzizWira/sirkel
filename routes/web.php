<?php

use App\Http\Controllers\{
    AdminAiController,
    AdminAiQuotaController,
    AdminAuditController,
    AdminController,
    AdminIssueController,
    AdminMasterController,
    AdminPartnerController,
    AdminSettingsController,
    AiHelpController,
    AiQuotaController,
    AssetController,
    AuthController,
    BulkIntakeController,
    CartController,
    DonationProofController,
    HandoverController,
    HomeController,
    IntakeSessionController,
    IssueController,
    MultiHandoverController,
    NotificationController,
    OfferController,
    PartnerAssetController,
    PartnerDashboardController,
    PartnerOnboardingController,
    PartnerRequestController,
    PassportController,
    ProfileController,
    RegionController,
    SplitBatchController,
    TransferController,
    UserDashboardController
};
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/mitra', [HomeController::class, 'partners'])->name('public.partners');
Route::get('/edukasi', [HomeController::class, 'education'])->name('public.education');
Route::get('/sitemap.xml', [HomeController::class, 'sitemap'])->name('sitemap');
Route::get('/passport/{code}', [PassportController::class, 'show'])->name('passport.show');
Route::get('/passport/{code}/qr', [PassportController::class, 'qr'])->name('passport.qr');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/auth/google', [AuthController::class, 'googleRedirect'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('auth.google.callback');
    Route::get('/lupa-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/lupa-password', [AuthController::class, 'forgot'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/verifikasi-email', [AuthController::class, 'otpForm'])->name('otp.form');
    Route::post('/verifikasi-email', [AuthController::class, 'verifyOtp'])->name('otp.verify');
    Route::post('/verifikasi-email/kirim-ulang', [AuthController::class, 'resendOtp'])->name('otp.resend');
    Route::get('/lengkapi-profil', [ProfileController::class, 'completeForm'])->name('profile.complete');
    Route::post('/lengkapi-profil', [ProfileController::class, 'complete'])->name('profile.complete.store');
    Route::post('/theme', [ProfileController::class, 'theme'])->name('theme.update');
    Route::get('/pilih-akses', [AuthController::class, 'accessForm'])->name('access.choose');
    Route::post('/pilih-akses', [AuthController::class, 'chooseAccess'])->name('access.choose.store');
});

// Region endpoints are required while the user is completing their profile,
// so they must NOT be protected by the profile.complete middleware.
Route::middleware(['auth', 'email.verified.custom'])->group(function () {
    Route::get('/regions/districts', [RegionController::class, 'districts'])->name('regions.districts');
    Route::get('/regions/villages', [RegionController::class, 'villages'])->name('regions.villages');
});

Route::middleware(['auth', 'email.verified.custom', 'profile.complete'])->group(function () {
    Route::get('/r/{code}', [AdminAiQuotaController::class, 'resolve'])->name('ai-topups.resolve');
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifikasi/{id}', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware(['auth', 'email.verified.custom', 'profile.complete', 'role:user'])->prefix('app')->name('user.')->group(function () {
    Route::get('/', [UserDashboardController::class, 'index'])->name('dashboard');
    Route::get('/aktivitas', [UserDashboardController::class, 'activity'])->name('activity');
    Route::get('/dampak', [UserDashboardController::class, 'impact'])->name('impact');
    Route::get('/kuota-ai', [AiQuotaController::class, 'index'])->name('ai-quota.index');
    Route::post('/kuota-ai/topup', [AiQuotaController::class, 'store'])->name('ai-quota.store');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
    Route::post('/keranjang/proses', [CartController::class, 'process'])->name('cart.process');
    Route::get('/pemeriksaan/{session}', [IntakeSessionController::class, 'standard'])->name('intake.standard.show');
    Route::post('/pemeriksaan/{session}/item/{item}/autosave', [IntakeSessionController::class, 'autosave'])->name('intake.standard.autosave');
    Route::post('/pemeriksaan/{session}/item/{item}/simpan-keluar', [IntakeSessionController::class, 'pause'])->name('intake.standard.pause');
    Route::post('/pemeriksaan/{session}/item/{item}/selesai', [IntakeSessionController::class, 'completeItem'])->name('intake.standard.complete-item');
    Route::get('/pemeriksaan/{session}/review', [IntakeSessionController::class, 'review'])->name('intake.review');
    Route::get('/pemeriksaan/{session}/atur-penyerahan', [MultiHandoverController::class, 'form'])->name('intake.handover.form');
    Route::post('/pemeriksaan/{session}/atur-penyerahan', [MultiHandoverController::class, 'match'])->name('intake.handover.match');
    Route::get('/pemeriksaan/{session}/rencana-mitra', [MultiHandoverController::class, 'partners'])->name('intake.handover.partners');
    Route::post('/pemeriksaan/{session}/rencana-mitra', [MultiHandoverController::class, 'create'])->name('intake.handover.create');
    Route::get('/bulk-ai', [BulkIntakeController::class, 'create'])->name('bulk.create');
    Route::post('/bulk-ai', [BulkIntakeController::class, 'store'])->name('bulk.store');
    Route::get('/bulk-ai/{session}/review-barang', [BulkIntakeController::class, 'edit'])->name('bulk.edit');
    Route::post('/bulk-ai/{session}/barang', [BulkIntakeController::class, 'addManual'])->name('bulk.items.store');
    Route::put('/bulk-ai/{session}/barang/{item}', [BulkIntakeController::class, 'updateItem'])->name('bulk.items.update');
    Route::delete('/bulk-ai/{session}/barang/{item}', [BulkIntakeController::class, 'deleteItem'])->name('bulk.items.destroy');
    Route::post('/bulk-ai/{session}/simpan-keranjang', [BulkIntakeController::class, 'saveToCart'])->name('bulk.cart');
    Route::post('/bulk-ai/{session}/susun-pertanyaan', [BulkIntakeController::class, 'startQuestionnaire'])->name('bulk.questionnaire.start');
    Route::get('/bulk-ai/{session}/pertanyaan', [BulkIntakeController::class, 'questionnaire'])->name('bulk.questionnaire');
    Route::post('/bulk-ai/{session}/pertanyaan/autosave', [BulkIntakeController::class, 'saveAnswers'])->name('bulk.answers.autosave');
    Route::post('/bulk-ai/{session}/pertanyaan/simpan-keluar', [BulkIntakeController::class, 'pause'])->name('bulk.answers.pause');
    Route::post('/bulk-ai/{session}/pertanyaan/selesai', [BulkIntakeController::class, 'complete'])->name('bulk.answers.complete');
    Route::get('/barang', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/barang/tambah', [AssetController::class, 'create'])->name('assets.create');
    Route::post('/barang', [AssetController::class, 'store'])->name('assets.store');
    Route::post('/barang/ai-draft', [AssetController::class, 'aiDraft'])->name('assets.ai-draft');
    Route::get('/barang/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::delete('/barang/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::get('/barang/{asset}/cek-kondisi', [AssetController::class, 'assessment'])->name('assets.assessment');
    Route::post('/barang/{asset}/cek-kondisi', [AssetController::class, 'submitAssessment'])->name('assets.assessment.store');
    Route::post('/barang/{asset}/ai-condition-description', [AiHelpController::class, 'conditionDescription'])->name('assets.ai-condition-description');
    Route::post('/map/resolve-link', [HandoverController::class, 'resolveMapLink'])->name('map.resolve-link');
    Route::get('/barang/{asset}/pilih-penyerahan', [HandoverController::class, 'matchForm'])->name('handovers.match.form');
    Route::get('/barang/{asset}/cari-mitra', [HandoverController::class, 'partners'])->name('handovers.partners');
    Route::post('/barang/{asset}/cari-mitra', [HandoverController::class, 'match'])->name('handovers.match');
    Route::post('/barang/{asset}/request', [HandoverController::class, 'create'])->name('handovers.create');
    Route::post('/request/{handover}/jadwal/terima', [HandoverController::class, 'acceptSchedule'])->name('handovers.schedule.accept');
    Route::post('/request/{handover}/setelah-penawaran-ditolak', [HandoverController::class, 'afterOfferRejection'])->name('handovers.offer-rejected.next');
    Route::post('/request/{handover}/batal', [HandoverController::class, 'cancel'])->name('handovers.cancel');
    Route::post('/penawaran/{offer}/respon', [OfferController::class, 'respond'])->name('offers.respond');
    Route::post('/penawaran/{offer}/nilai-final', [OfferController::class, 'confirmFinal'])->name('offers.final');
    Route::post('/laporkan-masalah', [IssueController::class, 'store'])->name('issues.store');
    Route::get('/daftar-mitra', [PartnerOnboardingController::class, 'create'])->name('become-partner.create');
    Route::post('/daftar-mitra', [PartnerOnboardingController::class, 'store'])->name('become-partner.store');
    Route::post('/daftar-mitra/paham', [PartnerOnboardingController::class, 'acknowledgeApproval'])->name('become-partner.acknowledge');
});

Route::middleware(['auth', 'email.verified.custom', 'profile.complete', 'role:partner'])->prefix('partner')->name('partner.')->group(function () {
    Route::get('/', [PartnerDashboardController::class, 'index'])->name('dashboard');
    Route::post('/availability', [PartnerDashboardController::class, 'availability'])->name('availability');
    Route::post('/map/resolve-link', [HandoverController::class, 'resolveMapLink'])->name('map.resolve-link');
    Route::get('/onboarding', [PartnerOnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [PartnerOnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/requests', [PartnerRequestController::class, 'index'])->name('requests.index');
    Route::get('/barang', [PartnerAssetController::class, 'index'])->name('assets.index');
    Route::get('/barang/{asset}', [PartnerAssetController::class, 'show'])->name('assets.show');
    Route::post('/barang/{asset}/decision-options', [PartnerAssetController::class, 'decisionOptions'])->name('assets.decision-options');
    Route::post('/barang/{asset}/assess', [PartnerAssetController::class, 'assess'])->name('assets.assess');
    Route::post('/barang/{asset}/bukti-donasi', [DonationProofController::class, 'store'])->name('assets.donation-proof.store');
    Route::get('/requests/{handover}', [PartnerRequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{handover}/accept', [PartnerRequestController::class, 'accept'])->name('requests.accept');
    Route::post('/requests/{handover}/decline', [PartnerRequestController::class, 'decline'])->name('requests.decline');
    Route::post('/requests/{handover}/offer', [PartnerRequestController::class, 'offer'])->name('requests.offer');
    Route::post('/requests/{handover}/propose-time', [PartnerRequestController::class, 'proposeSchedule'])->name('requests.propose-time');
    Route::post('/requests/{handover}/receive', [PartnerRequestController::class, 'receive'])->name('requests.receive');
    Route::post('/requests/{handover}/assess', [PartnerRequestController::class, 'assess'])->name('requests.assess');
    Route::post('/requests/{handover}/cancel', [PartnerRequestController::class, 'cancel'])->name('requests.cancel');
    Route::get('/barang/{asset}/transfer', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('/barang/{asset}/transfer', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
    Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    Route::post('/transfers/{transfer}/decline', [TransferController::class, 'decline'])->name('transfers.decline');
    Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
    Route::post('/barang/{asset}/split-batch', [SplitBatchController::class, 'store'])->name('assets.split');
    Route::post('/laporkan-masalah', [IssueController::class, 'store'])->name('issues.store');
});

Route::middleware(['auth', 'email.verified.custom', 'profile.complete', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/mitra', [AdminPartnerController::class, 'index'])->name('partners.index');
    Route::get('/mitra/{partner}', [AdminPartnerController::class, 'show'])->name('partners.show');
    Route::get('/mitra/{partner}/ktp', [AdminPartnerController::class, 'identity'])->name('partners.identity');
    Route::post('/mitra/{partner}/review', [AdminPartnerController::class, 'review'])->name('partners.review');
    Route::put('/mitra/{partner}/manage', [AdminPartnerController::class, 'manage'])->name('partners.manage');
    Route::post('/mitra/{partner}/status', [AdminPartnerController::class, 'status'])->name('partners.status');
    Route::get('/laporan', [AdminIssueController::class, 'index'])->name('issues.index');
    Route::put('/laporan/{issue}', [AdminIssueController::class, 'update'])->name('issues.update');
    Route::post('/laporan/{issue}/tawarkan-mitra', [AdminIssueController::class, 'offerPartner'])->name('issues.offer-partner');
    Route::get('/master-data', [AdminMasterController::class, 'index'])->name('master.index');
    Route::post('/master-data/kelompok/{group?}', [AdminMasterController::class, 'group'])->name('master.group');
    Route::post('/master-data/kategori/{category?}', [AdminMasterController::class, 'category'])->name('master.category');
    Route::post('/master-data/template', [AdminMasterController::class, 'template'])->name('master.template');
    Route::post('/master-data/template/{template}/question', [AdminMasterController::class, 'question'])->name('master.question');
    Route::post('/master-data/rule/{rule?}', [AdminMasterController::class, 'rule'])->name('master.rule');
    Route::get('/audit-log', [AdminAuditController::class, 'index'])->name('audit.index');
    Route::get('/ai-usage', [AdminAiController::class, 'index'])->name('ai.index');
    Route::post('/ai-usage/narrative', [AdminAiController::class, 'narrative'])->name('ai.narrative');
    Route::get('/kuota-ai', [AdminAiQuotaController::class, 'index'])->name('ai-quota.index');
    Route::get('/kuota-ai/{topup}', [AdminAiQuotaController::class, 'show'])->name('ai-quota.show');
    Route::post('/kuota-ai/{topup}/approve', [AdminAiQuotaController::class, 'approve'])->name('ai-quota.approve');
    Route::post('/kuota-ai/{topup}/reject', [AdminAiQuotaController::class, 'reject'])->name('ai-quota.reject');
    Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/sync-regions', [AdminSettingsController::class, 'syncRegions'])->name('settings.sync-regions');
});
