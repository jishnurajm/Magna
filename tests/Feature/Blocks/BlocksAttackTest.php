<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Auth\Role;
use Magna\Blocks\Livewire\BlockEditor;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Stage 7: /magna-preview/blocks now requires the blocks.preview permission.
function blocksPreviewUser(): User
{
    $role = Role::factory()->create();
    $role->grant('blocks.preview');
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

// Test 1: removeSection with negative index removes last section (data loss attack)
it('removeSection with negative index silently removes the last section', function (): void {
    $editor = new BlockEditor;
    $editor->mount(json_encode([
        ['id' => 'sec-A', 'type' => 'section', 'settings' => ['tokenOverrides' => []], 'columns' => [['id' => 'c1', 'span' => 12, 'settings' => [], 'blocks' => []]]],
        ['id' => 'sec-B', 'type' => 'section', 'settings' => ['tokenOverrides' => []], 'columns' => [['id' => 'c2', 'span' => 12, 'settings' => [], 'blocks' => []]]],
        ['id' => 'sec-C', 'type' => 'section', 'settings' => ['tokenOverrides' => []], 'columns' => [['id' => 'c3', 'span' => 12, 'settings' => [], 'blocks' => []]]],
    ]));

    $editor->removeSection(-1);  // attacker sends index -1

    // Does sec-C get deleted?
    expect(count($editor->sections))->toBe(3); // should still be 3 if guarded
});

// Test 2: updateSectionSettings with dot-traversal key
it('updateSectionSettings with dot-traversal key cannot overwrite section id', function (): void {
    $editor = new BlockEditor;
    $editor->mount(json_encode([
        ['id' => 'original-id', 'type' => 'section', 'settings' => ['tokenOverrides' => []], 'columns' => [['id' => 'c1', 'span' => 12, 'settings' => [], 'blocks' => []]]],
    ]));

    // Attempt to traverse up from settings to overwrite id
    $editor->updateSectionSettings(0, '../id', 'injected');

    expect($editor->sections[0]['id'])->toBe('original-id');
});

// Test 3: applyColumnLayout with negative span values that sum to 12
it('applyColumnLayout rejects column spans that include negative values', function (): void {
    $editor = new BlockEditor;
    $editor->mount(json_encode([
        ['id' => 'sec-1', 'type' => 'section', 'settings' => ['tokenOverrides' => []], 'columns' => [['id' => 'c1', 'span' => 12, 'settings' => [], 'blocks' => []]]],
    ]));

    $editor->applyColumnLayout(0, [-1, 13]); // sum=12 but has negative

    // The column with span=-1 should NOT be stored
    $spans = array_column($editor->sections[0]['columns'], 'span');
    expect(min($spans))->toBeGreaterThanOrEqual(1);
});

// Test 4: preview endpoint with huge payload (DoS simulation)
it('preview endpoint handles a large blocks_data payload without OOM', function (): void {
    $user = blocksPreviewUser();

    // 100 sections each with 50 blocks
    $sections = [];
    for ($s = 0; $s < 100; $s++) {
        $blocks = [];
        for ($b = 0; $b < 50; $b++) {
            $blocks[] = ['id' => "blk-$s-$b", 'block' => 'heading', 'settings' => [], 'data' => ['text' => str_repeat('a', 1000), 'level' => 'h2', 'align' => 'left']];
        }
        $sections[] = ['id' => "sec-$s", 'type' => 'section', 'settings' => ['tokenOverrides' => []], 'columns' => [['id' => "col-$s", 'span' => 12, 'settings' => [], 'blocks' => $blocks]]];
    }

    $response = $this->actingAs($user)->post(route('magna.blocks.preview'), [
        'blocks_data' => json_encode($sections),
    ]);

    expect($response->status())->toBe(200);
});

// Test 5: tokenOverrides CSS injection attempt
it('tokenOverrides with CSS-breaking value is safely escaped in style attribute', function (): void {
    $user = blocksPreviewUser();

    $sections = [[
        'id' => 'sec-1', 'type' => 'section',
        'settings' => ['tokenOverrides' => ['color' => 'red" onmouseover="evil()']],
        'columns' => [['id' => 'c1', 'span' => 12, 'settings' => [], 'blocks' => []]],
    ]];

    $response = $this->actingAs($user)->post(route('magna.blocks.preview'), [
        'blocks_data' => json_encode($sections),
    ]);

    // The style attribute value should be HTML-entity encoded
    expect($response->getContent())->not->toContain('onmouseover="evil()"');
    expect($response->status())->toBe(200);
});

// Test 6: serialise() with malformed UTF-8 throws instead of silently returning ""
it('serialise() throws on malformed UTF-8 rather than returning empty string', function (): void {
    $editor = new BlockEditor;
    $editor->mount('[]');

    // Inject invalid UTF-8 into block data
    $editor->sections = [[
        'id' => 'sec-1', 'type' => 'section',
        'settings' => ['tokenOverrides' => []],
        'columns' => [['id' => 'c1', 'span' => 12, 'settings' => [], 'blocks' => [
            ['id' => 'b1', 'block' => 'text', 'settings' => [], 'data' => ['content' => "\x80\x81\x82"]], // invalid UTF-8
        ]]],
    ]];

    expect(fn () => $editor->serialise())->toThrow(RuntimeException::class);
});
