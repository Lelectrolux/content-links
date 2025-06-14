<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks;

use Lelectrolux\ContentLinks\Enums\ContentLinkFieldType;

final readonly class ContentLinkExtractor
{
    // https://daringfireball.net/2010/07/improved_regex_for_matching_urls
    protected const URL_REGEX = '#(?i)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))#u';

    /** @return string[] */
    public function extractForFieldType(ContentLinkFieldType $fieldType, array|string $value): array
    {
        return match ($fieldType) {
            ContentLinkFieldType::Array => $this->array($value),
            ContentLinkFieldType::Text => $this->text($value),
            ContentLinkFieldType::Url => $this->url($value),
        };
    }

    /** @return string[] */
    public function array(array $array): array
    {
        $urls = [];

        array_walk_recursive($array, function ($value) use (&$urls) {
            if (is_string($value)) {
                $urls += $this->text($value);
            }
        });

        return array_unique($urls);
    }

    /** @return string[] */
    public function text(string $text): array
    {
        preg_match_all(self::URL_REGEX, $text, $matches);

        return array_unique($matches[0]);
    }

    /** @return string[] */
    public function url(string $url): array
    {
        return [$url];
    }
}
