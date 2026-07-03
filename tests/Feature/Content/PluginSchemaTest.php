<?php

declare(strict_types=1);

use Magna\Content\SchemaRegistry;
use Magna\Testing\PluginTestCase;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna/hello-world');
});

it('registers plugin schemas from schemas/ when the plugin is enabled', function (): void {
    /** @var SchemaRegistry $registry */
    $registry = app(SchemaRegistry::class);

    expect($registry->has('greeting'))->toBeTrue();
});

it('registers the greeting content type with correct fields', function (): void {
    /** @var SchemaRegistry $registry */
    $registry = app(SchemaRegistry::class);

    $type = $registry->get('greeting');
    expect($type)->not->toBeNull()
        ->and($type->displayName)->toBe('Greeting')
        ->and($type->draftable)->toBeFalse()
        ->and($type->getField('message'))->not->toBeNull()
        ->and($type->getField('metadata'))->not->toBeNull();
});
