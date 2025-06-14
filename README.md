Search for links in your models content, and check them for errors

## Getting Started

First, publish the config and migration files
```shell
php artisan vendor:publish --provider=Lelectrolux\ContentLinks\ContentLinksServiceProvider
```

Then, run the migration
```shell
php artisan migrate
```

Then, for each relevant model:
* Add 2 imports
```php
use \Lelectrolux\ContentLinks\Contracts\HasContentLinks as HasContentLinksContract;
use \Lelectrolux\ContentLinks\Models\HasContentLinks;
```
* Implement `HasContentLinksContract`
* Use `HasContentLinks`

Finally, add all the models `::class` to the `content-links.models` config key

Optionally, add the commands to your scheduler

## Available commands

```shell
# \Lelectrolux\ContentLinks\Console\Commands\ContentLinksCheck
# php artisan content-links:check {class?*} {--all}
php artisan content-links:check --all
php artisan content-links:check App\Models\MyModel
php artisan content-links:check App\Models\MyModel:1,2,3
php artisan content-links:check App\Models\MyModel:1,2,3 App\Models\MyOtherModel:1,2,3
```

```shell
# \Lelectrolux\ContentLinks\Console\Commands\ContentLinksPurge
php artisan content-links:purge
```

## Export

