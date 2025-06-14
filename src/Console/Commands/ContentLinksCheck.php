<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lelectrolux\ContentLinks\ContentLinkChecker;
use Lelectrolux\ContentLinks\ContentLinkExtractor;
use Lelectrolux\ContentLinks\Contracts\HasContentLinks;
use Lelectrolux\ContentLinks\Enums\ContentLinkFieldType;
use Lelectrolux\ContentLinks\Exceptions\InvalidArgumentException;
use Lelectrolux\ContentLinks\Exceptions\InvalidConfigurationException;
use Lelectrolux\ContentLinks\Models\ContentLink;

/**
 * @template TModel of Model&HasContentLinks
 */
final class ContentLinksCheck extends Command
{
    // https://daringfireball.net/2010/07/improved_regex_for_matching_urls
    protected const URL_REGEX = '#(?i)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))#u';

    protected $signature = 'content-links:check {class?*} {--all}';

    protected $description = 'Check for content links in models';

    /** @var array<string, ContentLink> */
    private array $urls = [];

    private array $totals = [];

    /**
     * @throws InvalidArgumentException
     * @throws InvalidConfigurationException
     */
    public function handle(ContentLinkExtractor $extractor, ContentLinkChecker $checker): int
    {
        $this->newLine();
        $this->validateConfig();
        $arguments = $this->parseArguments();
        $this->newLine();

        foreach ($arguments as [$class, $instance, $ids]) {
            $contentLinkDefinitions = $instance::contentLinkDefinitions();
            $fields = array_keys($contentLinkDefinitions);
            $maxFieldLength = max(array_map(mb_strlen(...), $fields));
            $modelBaseName = class_basename($class);
            $modelCount = (string) $this->modelQuery($instance, $fields, $ids)->count();
            $i = 1;
            $this->totals[$class] = [
                'class' => $class,
                'models' => 0,
                'urls' => 0,
                'oks' => 0,
                'redirects' => 0,
                'errors' => 0,
            ];

            $this->newLine();
            $this->newLine();
            $this->line("<bg=white;fg=black>[{$class}]</>");
            $this->line('- Searching in fields: '.Arr::join(array_map(fn ($field) => "<fg=magenta>{$field}</>", $fields), ', ', ' and '));
            $this->newLine();

            foreach ($this->modelQuery($instance, $fields, $ids)->cursor() as $model) {
                $this->totals[$class]['models']++;

                $n = Str::padLeft($i, mb_strlen($modelCount), '0');
                $this->output->writeln("[<fg=blue>{$n}</>/{$modelCount}] {$modelBaseName} <fg=cyan>{$model->id}</>");

                $pivots = [];
                foreach ($contentLinkDefinitions as $field => $type) {
                    $value = $model->{$field};

                    if ($value === null) {
                        continue;
                    }

                    $urls = $extractor->extractForFieldType($type, $value);

                    foreach ($urls as $url) {
                        // Allow non-absolute url for ContentLinkFieldType::Url
                        $fetchUrl = $type === ContentLinkFieldType::Url ? url($url) : $url;

                        $check = $checker->check($fetchUrl);

                        $linkKey = ContentLink::query()
                            ->updateOrCreate(['url' => $fetchUrl], $check)
                            ->getKey();

                        $pivots[$linkKey] = ['field' => $field];

                        ['status' => $status, 'redirect' => $redirect] = $check;
                        $padLeft = Str::padLeft('', $maxFieldLength - mb_strlen($field), '.');
                        if ($status !== 200) {
                            $this->totals[$class]['errors']++;
                            $this->output->writeln("<fg=gray>> {$padLeft}</><fg=magenta>{$field}</> <fg=red>".($status ?? 'ERR')."</> {$fetchUrl}");
                        } elseif ($redirect === null) {
                            $this->totals[$class]['oks']++;
                            $this->output->writeln("<fg=gray>> {$padLeft}</><fg=magenta>{$field}</> <fg=green>{$status}</> {$fetchUrl}");
                        } else {
                            $this->totals[$class]['redirects']++;
                            $this->output->writeln("<fg=gray>> {$padLeft}</><fg=magenta>{$field}</> <fg=yellow>{$status}</> {$redirect}");
                        }
                    }
                }

                $model->contentLinks()->sync($pivots);

                $idsByField = [];
                foreach ($pivots as $linkId => ['field' => $field]) {
                    $idsByField[$field] ??= "for <fg=magenta>{$field}</> to";
                    $idsByField[$field] .= " {$linkId}";
                }

                $this->line('<fg=gray>></> Synced '.Arr::join($idsByField, ', ', ' and '));
                $this->newLine();
                $i++;
            }
        }

        $this->results();

        return 0;
    }

    protected function modelQuery(mixed $instance, array $fields, mixed $ids)
    {
        return $instance::query()
            ->addSelect($instance->getKeyName())
            ->addSelect($fields)
            ->when($ids, fn (Builder $query) => $query->whereIn($instance->getKeyName(), $ids));
    }

    private function results(): void
    {
        $this->newLine();
        $this->table(
            [
                '<fg=blue>N</> <fg=default>Model</>',
                '<fg=green>O</>',
                '<fg=yellow>R</>',
                '<fg=red>E</>',
            ],
            array_map(static fn (array $counts) => [
                "<fg=blue>{$counts['models']}</> {$counts['class']}",
                "<fg=green>{$counts['oks']}</>",
                "<fg=yellow>{$counts['redirects']}</>",
                "<fg=red>{$counts['errors']}</>",
            ], $this->totals),
            'box-double');
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function validateConfig(): void
    {
        $this->line('<fg=gray>Validating config...</>');

        $modelClasses = config('content-links.models');

        $errors = array_reduce($modelClasses, static function ($errors, $class) {
            if (! is_a($class, Model::class, allow_string: true)) {
                $errors[] = "{$class} is not an eloquent model.";
            }
            if (! is_a($class, HasContentLinks::class, allow_string: true)) {
                $errors[] = "{$class} does not implements the HasContentLinks interface.";
            }

            return $errors;
        }, []);

        if ($errors === []) {
            return;
        }

        throw new InvalidConfigurationException(implode(PHP_EOL, $errors));
    }

    /**
     * @return array{class-string<TModel>, TModel, ?string[]}[]
     *
     * @throws InvalidArgumentException
     */
    private function parseArguments(): array
    {
        $this->line('<fg=gray>Parsing arguments...</>');

        $modelClasses = config('content-links.models');
        $arguments = $this->argument('class');

        if ($this->option('all')) {
            $arguments = $modelClasses;
        }

        if ($arguments === []) {
            throw new InvalidArgumentException('Please specify a model or use the --all option.');
        }

        return array_map(static function ($value) use ($modelClasses) {
            $exploded = explode(':', $value, 2);

            $class = array_shift($exploded);

            if (! in_array($class, $modelClasses, true)) {
                throw new InvalidArgumentException("{$class} is not a model included in the content-links config");
            }

            /** @var Model&HasContentLinks $instance */
            $instance = new $class();

            if (isset($exploded[0])) {
                $ids = explode(',', $exploded[0]);
            }

            return [$class, $instance, $ids ?? null];
        }, $arguments);
    }
}
