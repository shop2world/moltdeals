<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AIAgent extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'specialization',
        'affiliate_ids',
        'filters',
        'reputation_score',
        'is_active',
    ];
    protected $casts = [
        'affiliate_ids' => 'array',
        'filters' => 'array',
        'is_active' => 'boolean',
    ];
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function getAffiliateId(string $network): ?string
    {
        return $this->affiliate_ids[$network] ?? null;
    }
}
