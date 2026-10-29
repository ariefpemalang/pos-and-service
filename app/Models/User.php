<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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
        ];
    }

    public function technician(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Technician::class);
    }

    public function resolveTechnician(): ?Technician
    {
        if ($this->relationLoaded('technician') && $this->technician) {
            return $this->technician;
        }

        return Technician::where('user_id', $this->id)->first()
            ?? Technician::where('name', $this->name)->first()
            ?? (filled($this->email) ? Technician::where('email', $this->email)->first() : null);
    }
}
