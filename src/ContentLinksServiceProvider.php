<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks;

use Illuminate\Support\ServiceProvider;
use Lelectrolux\ContentLinks\Console\Commands\ContentLinksCheck;
use Lelectrolux\ContentLinks\Console\Commands\ContentLinksPurge;
use Lelectrolux\ContentLinks\Models\ContentLink;

final class ContentLinksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/content-links.php' => config_path('content-links.php')]);
        $this->mergeConfigFrom(__DIR__.'/../config/content-links.php', 'content-links');

        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')]);

        if ($this->app->runningInConsole()) {
            $this->commands([ContentLinksCheck::class, ContentLinksPurge::class]);
        }

        ContentLink::resolveRelations();
    }
}
