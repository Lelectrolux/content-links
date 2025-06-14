<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo as MorphToAlias;
use Lelectrolux\ContentLinks\Contracts\HasContentLinks;

class ContentLinkable extends MorphPivot
{
    protected $table = 'content_linkables';

    protected $fillable = [];

    /** @return BelongsTo<ContentLink> */
    public function contentLink(): BelongsTo
    {
        return $this->belongsTo(ContentLink::class, 'content_link_id');
    }

    /** @return MorphToAlias<Model&HasContentLinks> */
    public function contentLinkable(): MorphToAlias
    {
        return $this->morphTo('content_linkable');
    }

    protected function casts(): array
    {
        return [
            'content_linkable_id' => 'int',
            'content_linkable_type' => 'string',
            'content_link_id' => 'int',
            'field' => 'string',
            'created_at' => 'timestamp',
        ];
    }
}
