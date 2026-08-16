<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /*
    | privilege, groups and STATUS are mass-assignable because Kosada's account
    | management creates users with all three. Without them here, User::create()
    | silently drops the values and every new account lands with no role and no
    | app scope — a failure that looks like it worked.
    |
    | Note the column is `groups`, plural. Authenticate@generateAdmin used to pass
    | `group`, which was dropped for exactly this reason.
    */
    protected $fillable = [
        'name',
        'email',
        'password',
        'privilege',
        'groups',
        'STATUS',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
}
