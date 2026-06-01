<?php

namespace App\Models;

use Database\Factories\SpecialtyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    /** @use HasFactory<SpecialtyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }
}
