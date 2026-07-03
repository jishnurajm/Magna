<?php

declare(strict_types=1);

namespace Magna\Testing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Plugins\PluginManager;
use Tests\TestCase;

/**
 * Base test case for Magna plugin tests.
 *
 * Usage (Pest):
 *   uses(PluginTestCase::class);
 *   beforeEach(fn () => $this->enablePlugin('acme/my-plugin'));
 *
 * Usage (PHPUnit class-based):
 *   class MyPluginTest extends PluginTestCase {
 *       protected string $plugin = 'acme/my-plugin';
 *   }
 */
abstract class PluginTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Override in subclasses to automatically enable a plugin before each test.
     */
    protected string $plugin = '';

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->plugin !== '') {
            $this->enablePlugin($this->plugin);
        }
    }

    /**
     * Enable a plugin for the current test. Call this from Pest's beforeEach().
     */
    public function enablePlugin(string $name): void
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);
        $manager->enable($name);
    }
}
