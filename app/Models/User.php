<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Fillable(['name', 'email', 'password', 'restaurant_id', 'branch_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Permission grants are deliberately explicit and deny by default.
     * The `admin` grant is the sole wildcard and should only be assigned to
     * trusted administrators through a controlled provisioning process.
     */
    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities) && Schema::hasTable('user_permissions')) {
            $query = DB::table('user_permissions')->where('user_id', $this->getKey());
            if ($query->whereIn('ability', [$abilities, 'admin'])->exists()) {
                return true;
            }
        }

        return parent::can($abilities, $arguments);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /** Holder of the `admin` wildcard: only manageable through reviewed CLI provisioning. */
    public function isWildcardAdmin(): bool
    {
        return DB::table('user_permissions')->where('user_id', $this->getKey())->where('ability', 'admin')->exists();
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }
}
