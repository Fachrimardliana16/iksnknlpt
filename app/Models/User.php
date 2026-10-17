<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Models\Concerns\PunyaIndeksButa;
use App\Models\Concerns\PunyaUlid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PunyaIndeksButa, PunyaUlid;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'role',
        'is_blocked',
        'last_login_at',
        'last_login_ip_hash',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @var array<string, string> */
    protected array $indeksButa = [
        'email' => 'email_bidx',
    ];

    protected function casts(): array
    {
        return [
            // PII terenkripsi di tingkat aplikasi. DBSCHEMA.md Bagian 9.2.
            'name' => 'encrypted',
            'email' => 'encrypted',
            'phone_number' => 'encrypted',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',

            'password' => 'hashed',
            'role' => PeranPengguna::class,
            'is_blocked' => 'boolean',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    // ── Relasi ──────────────────────────────────────────────────────

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Cart, $this> */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    // ── Peran ───────────────────────────────────────────────────────

    public function admin(): bool
    {
        return $this->role instanceof PeranPengguna
            ? $this->role->bisaMasukPanel()
            : false;
    }

    public function superAdmin(): bool
    {
        return $this->role === PeranPengguna::SuperAdmin;
    }

    public function punyaDuaFaktor(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    // ── Indeks buta ─────────────────────────────────────────────────

    public function scopeDenganEmail($query, ?string $email)
    {
        return $this->scopeCariIndeksButa($query, 'email', $email);
    }
}
