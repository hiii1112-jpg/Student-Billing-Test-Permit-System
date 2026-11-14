<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['username', 'name', 'email', 'password', 'role', 'cashier_type', 'is_active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function student() { return $this->hasOne(Student::class); }

    public function isHead(): bool
    {
        return $this->role === 'cashier' && $this->cashier_type === 'head';
    }
}