<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;
use Spatie\SchemalessAttributes\SchemalessAttributes;

class User extends Authenticatable
{
    use HasRoles,
        HasPermissions;

    protected $guarded = [];

    protected $attributes = [
        'extra_attributes' => '{}',
    ];

    protected $casts = [
        'extra_attributes' => 'array',
    ];

    protected $guard_name = 'web';

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function getAllPosts()
    {
        return $this->posts;
    }

    public function getExtraAttributesAttribute(): SchemalessAttributes
    {
        return SchemalessAttributes::createForModel($this, 'extra_attributes');
    }

    public function scopeWithExtraAttributes(): Builder
    {
        return SchemalessAttributes::scopeWithSchemalessAttributes('extra_attributes');
    }
}
