<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Throwable;

final class ContentLinkChecker
{
    public function __construct(private readonly Factory $http, private array $urls = []) {}

    /** @return array{status: ?int, redirect: ?string} */
    public function check(string $url): array
    {
        if (! isset($this->urls[$url])) {
            $response = $this->fetch($url);

            if ($response?->status() === 405) {
                $response = $this->fetch($url, getMethod: true);
            }

            $effectiveUri = $response?->effectiveUri()?->__toString();
            $redirect = $effectiveUri !== $url ? $effectiveUri : null;

            $this->urls[$url] = [
                'status' => $response?->status(),
                'redirect' => $redirect,
            ];
        }

        return $this->urls[$url];
    }

    private function fetch(string $url, bool $getMethod = false): ?Response
    {
        try {
            return $this->http
                ->createPendingRequest()
                ->accept('text/html, */*;q=0.8')
                ->withUserAgent(false)
                ->withHeaders([
                    'Accept-Language' => 'fr, es;q=0.9, en;q=0.8, *;q=0.5',
                    'Accept-Encoding' => 'br, deflate, gzip',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ])
                ->send($getMethod ? Request::METHOD_GET : Request::METHOD_HEAD, $url);
        } catch (HttpClientException $e) {
            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
