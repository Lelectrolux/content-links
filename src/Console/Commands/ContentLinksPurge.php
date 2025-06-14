<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lelectrolux\ContentLinks\Models\ContentLink;
use Lelectrolux\ContentLinks\Models\ContentLinkable;

final class ContentLinksPurge extends Command
{
    protected $signature = 'content-links:purge';

    protected $description = 'Purge content links with no related models';

    public function handle(): int
    {
        DB::transaction(function () {
            ContentLinkable::query()
                ->whereDoesntHaveMorph('contentLinkable', config('content-links.models'))
                ->orWhereDoesntHave('contentLink')
                ->delete();
            ContentLink::query()
                ->whereDoesntHave('contentLinkables')
                ->delete();
        });

        return 0;
    }
}
