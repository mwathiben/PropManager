<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'question',
        'answer',
        'category',
        'roles',
        'order',
        'is_published',
    ];

    protected $casts = [
        'roles' => 'array',
        'is_published' => 'boolean',
    ];

    public function scopeForRole($query, ?string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('roles')
                ->orWhereJsonContains('roles', $role);
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
