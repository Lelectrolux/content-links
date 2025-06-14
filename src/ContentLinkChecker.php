<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Throwable;

final class ContentLinkChecker
{
    public function __construct(private readonly Factory $http, private array $urls = []) {}

    /** @return array{status: ?int, redirect: ?string} */
    public function check(string $url): array
    {
        if (! isset($this->urls[$url])) {
            $response = $this->fetch($url);

            $effectiveUri = $response?->effectiveUri()?->__toString();
            $redirect = $effectiveUri !== $url ? $effectiveUri : null;

            $this->urls[$url] = [
                'status' => $response?->status(),
                'redirect' => $redirect,
            ];
        }

        return $this->urls[$url];
    }

    private function fetch(string $url): ?Response
    {
        try {
            return $this->http
                ->createPendingRequest()
                ->withHeaders([
                    'Accept' => 'text/html, */*;q=0.8',
                    'Accept-Language' => 'fr, es;q=0.9, en;q=0.8, *;q=0.5',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ])
                ->get($url);
        } catch (HttpClientException $e) {
            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
