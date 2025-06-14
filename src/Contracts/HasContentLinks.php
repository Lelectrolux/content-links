<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lelectrolux\ContentLinks\Enums\ContentLinkFieldType;
use Lelectrolux\ContentLinks\Models\ContentLink;
use Lelectrolux\ContentLinks\Models\ContentLinkable;

interface HasContentLinks
{
    /** @return array<string, ContentLinkFieldType> */
    public static function contentLinkDefinitions(): array;

    /** @return MorphToMany<ContentLink> */
    public function contentLinks(): MorphToMany;

    /** @return MorphMany<ContentLinkable> */
    public function contentLinkables(): MorphMany;
}
