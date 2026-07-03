<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Magna\Content\Console\SchemaDiffCommand;
use Magna\Content\Console\SchemaSyncCommand;
use Magna\Content\FieldTypes\BlocksField;
use Magna\Content\FieldTypes\BooleanField;
use Magna\Content\FieldTypes\ColorField;
use Magna\Content\FieldTypes\DateField;
use Magna\Content\FieldTypes\DatetimeField;
use Magna\Content\FieldTypes\EmailField;
use Magna\Content\FieldTypes\JsonField;
use Magna\Content\FieldTypes\MarkdownField;
use Magna\Content\FieldTypes\MediaField;
use Magna\Content\FieldTypes\NumberField;
use Magna\Content\FieldTypes\RelationField;
use Magna\Content\FieldTypes\RichtextField;
use Magna\Content\FieldTypes\SelectField;
use Magna\Content\FieldTypes\SlugField;
use Magna\Content\FieldTypes\TextareaField;
use Magna\Content\FieldTypes\TextField;
use Magna\Content\FieldTypes\UrlField;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FieldTypeRegistry::class, function (): FieldTypeRegistry {
            $registry = new FieldTypeRegistry;
            $registry->register('text', TextField::class);
            $registry->register('textarea', TextareaField::class);
            $registry->register('richtext', RichtextField::class);
            $registry->register('markdown', MarkdownField::class);
            $registry->register('number', NumberField::class);
            $registry->register('boolean', BooleanField::class);
            $registry->register('date', DateField::class);
            $registry->register('datetime', DatetimeField::class);
            $registry->register('select', SelectField::class);
            $registry->register('media', MediaField::class);
            $registry->register('relation', RelationField::class);
            $registry->register('blocks', BlocksField::class);
            $registry->register('json', JsonField::class);
            $registry->register('slug', SlugField::class);
            $registry->register('email', EmailField::class);
            $registry->register('url', UrlField::class);
            $registry->register('color', ColorField::class);

            return $registry;
        });

        $this->app->singleton(SchemaRegistry::class, function (Application $app): SchemaRegistry {
            return new SchemaRegistry($app->make(FieldTypeRegistry::class));
        });

        $this->app->singleton(TableGenerator::class);

        $this->app->singleton(SchemaDiffer::class, function (Application $app): SchemaDiffer {
            return new SchemaDiffer(
                $app->make(TableGenerator::class),
                $app->make(FieldTypeRegistry::class),
            );
        });

        $this->app->singleton(SchemaSyncer::class, function (Application $app): SchemaSyncer {
            return new SchemaSyncer(
                $app->make(TableGenerator::class),
                $app->make(SchemaDiffer::class),
            );
        });
    }

    public function boot(): void
    {
        /** @var SchemaRegistry $registry */
        $registry = $this->app->make(SchemaRegistry::class);

        $schemasDir = $this->app->basePath('schemas');
        if (is_dir($schemasDir)) {
            $registry->loadFromDirectory($schemasDir);
        }

        if (Schema::hasTable('content_types')) {
            $registry->loadFromDatabase();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SchemaDiffCommand::class,
                SchemaSyncCommand::class,
            ]);
        }
    }
}
