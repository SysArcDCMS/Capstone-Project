<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Category model — capstone tbl_categories.
 *
 * Registry of complaint categories shown on the web portal "Categories"
 * screen. `category_name` matches the canonical ai-nlp classifier label
 * (DFD 3.2 department mapping); `label` is the prettified display title.
 */
class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $table = 'tbl_categories';

    /** @var list<string> */
    protected $fillable = [
        'category_name',
        'label',
        'description',
        'color',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function displayLabel(): string
    {
        return $this->label ?: $this->category_name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}