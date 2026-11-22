<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PortfolioRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'published' => 'boolean', 'featured' => 'boolean'];
    }

    public static function forSection(string $section): self
    {
        return (new self)->setTable(Str::replace('-', '_', $section));
    }
}
