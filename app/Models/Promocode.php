<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promocode extends Model
{
    protected $fillable = ['code', 'is_sold'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
