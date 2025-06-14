<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Lelectrolux\ContentLinks\ContentLinkChecker;
use Lelectrolux\ContentLinks\ContentLinkExtractor;
use Lelectrolux\ContentLinks\Contracts\HasContentLinks;
use Lelectrolux\ContentLinks\Enums\ContentLinkFieldType;
use Lelectrolux\ContentLinks\Models\ContentLink;

readonly class CheckForContentLinks implements ShouldQueue
{
    public function __construct(private Model&HasContentLinks $model) {}

    public function handle(ContentLinkExtractor $extractor, ContentLinkChecker $checker): void
    {
        $model = $this->model->fresh();

        if ($model === null) {
            return;
        }

        $pivots = [];
        foreach ($model::contentLinkDefinitions() as $field => $type) {
            $value = $model->{$field};

            if ($value === null) {
                continue;
            }

            $urls = $extractor->extractForFieldType($type, $value);

            foreach ($urls as $url) {
                // Allow non-absolute url for ContentLinkFieldType::Url
                $fetchUrl = $type === ContentLinkFieldType::Url ? url($url) : $url;

                $linkKey = ContentLink::query()
                    ->updateOrCreate(
                        ['url' => $fetchUrl],
                        $checker->check($fetchUrl))
                    ->getKey();

                $pivots[$linkKey] = ['field' => $field];
            }
        }

        $model->contentLinks()->sync($pivots);
    }
}
