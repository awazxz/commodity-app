<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * Kolom yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Relasi ke model User.
     * Satu Role bisa dimiliki oleh banyak User (One-to-Many).
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}