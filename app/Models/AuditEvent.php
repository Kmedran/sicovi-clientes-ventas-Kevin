<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'action', 'entity_type', 'entity_id', 'before_values', 'after_values', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
