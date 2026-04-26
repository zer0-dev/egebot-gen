<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = ['name', 'price'];

    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class, 'cart')->withTimestamps();
    }

    public function promocodes(): HasMany {
        return $this->hasMany(Promocode::class);
    }
}
