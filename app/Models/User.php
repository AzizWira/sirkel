<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'whatsapp',
        'google_id',
        'email_verified_at',
        'profile_completed_at',
        'theme_preference',
        'district',
        'village',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function partnerProfile()
    {
        return $this->hasOne(PartnerProfile::class);
    }
    public function assets()
    {
        return $this->hasMany(Asset::class, 'owner_user_id');
    }
    public function aiTopupRequests()
    {
        return $this->hasMany(AiTopupRequest::class);
    }
    public function intakeSessions()
    {
        return $this->hasMany(IntakeSession::class);
    }

    /**
     * Akses akun berbeda dengan mode sesi aktif. Seorang warga yang pengajuan
     * mitranya disetujui tetap memiliki akses Warga dan mendapat akses Mitra.
     */
    public function hasCitizenAccess(): bool
    {
        return $this->role !== UserRole::ADMIN;
    }

    public function hasPartnerAccess(): bool
    {
        if ($this->role === UserRole::ADMIN)
            return false;

        $profile = $this->relationLoaded('partnerProfile')
            ? $this->partnerProfile
            : $this->partnerProfile()->first();

        return $profile?->partner_access_granted_at !== null || $profile?->verification_status === 'approved';
    }

    public function availableAccessRoles(): array
    {
        if ($this->role === UserRole::ADMIN)
            return ['admin'];

        $roles = ['user'];
        if ($this->hasPartnerAccess())
            $roles[] = 'partner';
        return $roles;
    }

    /**
     * Saat login normal, active_role disimpan di session. Fallback ke role lama
     * dipertahankan agar command/test lama yang memakai actingAs() tetap kompatibel.
     */
    public function activeAccessRole(): string
    {
        $sessionRole = null;
        try {
            if (request()->hasSession())
                $sessionRole = request()->session()->get('active_role');
        } catch (\Throwable) {
            $sessionRole = null;
        }

        if (is_string($sessionRole) && in_array($sessionRole, $this->availableAccessRoles(), true)) {
            return $sessionRole;
        }

        if ($this->role === UserRole::ADMIN)
            return 'admin';
        if ($this->role === UserRole::PARTNER && $this->hasPartnerAccess())
            return 'partner';
        return 'user';
    }

    public function isAdmin(): bool
    {
        return $this->activeAccessRole() === 'admin';
    }
    public function isPartner(): bool
    {
        return $this->activeAccessRole() === 'partner';
    }
    public function isUser(): bool
    {
        return $this->activeAccessRole() === 'user';
    }

    public function shouldChooseAccess(): bool
    {
        return count($this->availableAccessRoles()) > 1;
    }
}
