<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

final class ContentLink extends Model
{
    protected $guarded = [];

    public static function resolveRelations(): void
    {
        foreach (config('content-links.models') as $class) {
            self::resolveRelationUsing(
                self::relationNameForClass($class),
                static fn (self $link) => $link->relationForClass($class));
        }
    }

    /** @param class-string<Model&HasContentLinks> $class */
    public static function relationNameForClass(string $class): string
    {
        return Str::plural(mb_lcfirst(class_basename($class)));
    }

    /** @param class-string<Model&HasContentLinks> $class */
    public function relationForClass(string $class): MorphToMany
    {
        return $this->morphedByMany($class, 'content_linkable')
            ->using(ContentLinkable::class)
            ->withPivot('field')
            ->orderByPivot('field');
    }

    public function contentLinkables(): HasMany
    {
        return $this->hasMany(ContentLinkable::class, 'content_link_id')->orderBy('field');
    }

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'url' => 'string',
            'redirect' => 'string',
            'status' => 'int',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
