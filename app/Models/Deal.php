<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function votes()
    {
        return $this->hasMany(DealVote::class);
    }

    public function comments()
    {
        return $this->hasMany(DealComment::class);
    }
}