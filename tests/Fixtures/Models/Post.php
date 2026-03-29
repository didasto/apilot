<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = ['title', 'body', 'status'];

    protected $casts = [
        'status' => 'string',
    ];

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    public function scopeStatus(Builder $query, string $value): Builder
    {
        return $query->where('status', '=', $value);
    }
}
