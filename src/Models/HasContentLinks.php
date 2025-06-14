<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lelectrolux\ContentLinks\Contracts\HasContentLinks as HasContentLinksContract;
use Lelectrolux\ContentLinks\Enums\ContentLinkFieldType;
use Lelectrolux\ContentLinks\Jobs\CheckForContentLinks;

/** @mixin (Model&HasContentLinksContract)|SoftDeletes */
trait HasContentLinks
{
    /** @return array<string, ContentLinkFieldType> */
    abstract public static function contentLinkDefinitions(): array;

    public static function bootHasContentLinks(): void
    {
        static::saved(static function (Model&HasContentLinksContract $model) {
            $changedContentLinkFields = array_intersect_key($model->getChanges(), static::contentLinkDefinitions());

            if ($changedContentLinkFields !== []) {
                dispatch(new CheckForContentLinks($model))->afterResponse();
            }
        });
    }

    /** @return MorphToMany<ContentLink> */
    public function contentLinks(): MorphToMany
    {
        return $this->morphToMany(ContentLink::class, 'content_linkable')
            ->using(ContentLinkable::class)
            ->withPivot('field');
    }

    public function contentLinkables(): MorphMany
    {
        return $this->morphMany(ContentLinkable::class, 'content_linkable')->orderBy('field');
    }
}
