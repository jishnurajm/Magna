<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Magna\Content\Events\EntryCreated;
use Magna\Content\Events\EntryDeleted;
use Magna\Content\Events\EntryPublished;
use Magna\Content\Events\EntryUnpublished;
use Magna\Content\Events\EntryUpdated;
use Magna\Content\Exceptions\SchemaException;
use Magna\Content\FieldTypes\SlugField;
use Magna\Content\Models\Revision;

class EntryManager
{
    public function __construct(
        private readonly SchemaRegistry $registry,
        private readonly SchemaValidator $validator,
        private readonly SlugGenerator $slugs,
    ) {}

    /**
     * Create a new entry for the given content type.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws SchemaException
     * @throws ValidationException
     */
    public function create(string $typeHandle, array $data, ?string $authorId = null): Entry
    {
        $type = $this->registry->get($typeHandle);
        if ($type === null) {
            throw new SchemaException("Unknown content type: \"{$typeHandle}\".");
        }

        $data = $this->applyAutoSlugs($type, $data);
        $validated = $this->validator->validate($type, $data);

        $entry = Entry::makeInstance($typeHandle, $this->registry);
        $entry->fill($validated);
        $entry->locale = '';
        $entry->author_id = $authorId;
        $entry->draft_of = null;

        if ($type->draftable) {
            $entry->status = EntryStatus::Draft;
        } else {
            $entry->status = EntryStatus::Published;
            $entry->published_at = now();
        }

        $entry->save();

        event(new EntryCreated($entry, $authorId));

        if (! $type->draftable) {
            event(new EntryPublished($entry, $authorId));
        }

        return $entry;
    }

    /**
     * Update an existing entry's field values.
     *
     * Only the fields present in $data are validated and updated (partial update).
     * If the entry is currently published, a revision of the pre-update state is
     * saved first.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws SchemaException
     * @throws ValidationException
     */
    public function update(Entry $entry, array $data, ?string $actorId = null): Entry
    {
        $type = $this->getType($entry);

        $data = $this->applyAutoSlugs($type, $data);
        $validated = $this->validator->validate($type, $data, partial: true);

        if ($entry->status === EntryStatus::Published) {
            $this->createRevision($entry, $type->handle, $actorId);
        }

        $entry->fill($validated);
        $entry->save();

        event(new EntryUpdated($entry, $actorId));

        return $entry;
    }

    /**
     * Publish an entry.
     *
     * - If $at is a future time, the entry is scheduled (status = scheduled).
     * - If the entry's draft_of is set, the published original is updated from
     *   this draft and the draft is deleted.
     * - Otherwise the entry is published immediately.
     *
     * @throws SchemaException
     */
    public function publish(Entry $entry, ?Carbon $at = null, ?string $actorId = null): Entry
    {
        if ($entry->draft_of !== null) {
            return $this->publishDraftOf($entry, $actorId);
        }

        if ($at !== null && $at->isFuture()) {
            $entry->status = EntryStatus::Scheduled;
            $entry->published_at = $at;
            $entry->save();

            return $entry;
        }

        if ($entry->status === EntryStatus::Published) {
            $this->createRevision($entry, $this->getType($entry)->handle, $actorId);
        }

        $entry->status = EntryStatus::Published;
        $entry->published_at = $entry->published_at ?? now();
        $entry->save();

        event(new EntryPublished($entry, $actorId));

        return $entry;
    }

    /**
     * Unpublish (archive) a published entry.
     *
     * @throws SchemaException
     */
    public function unpublish(Entry $entry, ?string $actorId = null): Entry
    {
        $entry->status = EntryStatus::Archived;
        $entry->save();

        event(new EntryUnpublished($entry, $actorId));

        return $entry;
    }

    /**
     * Delete an entry permanently.
     */
    public function delete(Entry $entry, ?string $actorId = null): void
    {
        $copy = clone $entry;
        $entry->delete();
        event(new EntryDeleted($copy, $actorId));
    }

    /**
     * Create a draft copy of a published entry.
     *
     * The draft starts with the same field values as the published entry.
     * There should be only one pending draft per published entry at a time;
     * callers are responsible for enforcing this.
     *
     * @throws SchemaException
     */
    public function createDraftOf(Entry $published): Entry
    {
        $handle = $published->getHandle();
        if ($handle === null) {
            throw new SchemaException('Entry is not bound to a content type.');
        }

        $type = $this->registry->get($handle);
        if ($type === null) {
            throw new SchemaException("Unknown content type: \"{$handle}\".");
        }

        $attrs = [];
        foreach ($type->columnFields() as $field) {
            $attrs[$field->handle] = $published->getAttribute($field->handle);
        }

        $draft = Entry::makeInstance($handle, $this->registry);
        $draft->fill($attrs);

        $locale = $published->getAttribute('locale');
        $draft->locale = is_string($locale) ? $locale : '';

        $authorId = $published->getAttribute('author_id');
        $draft->author_id = is_string($authorId) ? $authorId : null;

        $draft->draft_of = $published->id;
        $draft->status = EntryStatus::Draft;
        $draft->save();

        return $draft;
    }

    /**
     * Restore an entry to the state captured in a revision.
     *
     * Before restoring, the current state is itself snapshotted as a revision
     * so the restore is reversible.
     *
     * @throws SchemaException
     */
    public function restore(string $revisionId, ?string $actorId = null): Entry
    {
        /** @var Revision $revision */
        $revision = Revision::query()->findOrFail($revisionId);

        $handle = $revision->entry_type;
        $type = $this->registry->get($handle);
        if ($type === null) {
            throw new SchemaException("Unknown content type: \"{$handle}\".");
        }

        /** @var Entry $entry */
        $entry = Entry::type($handle)->findOrFail($revision->entry_id);

        // Snapshot current state before restoring.
        $this->createRevision($entry, $handle, $actorId);

        $payload = $revision->payload;
        foreach ($type->columnFields() as $field) {
            if (array_key_exists($field->handle, $payload)) {
                $entry->setAttribute($field->handle, $payload[$field->handle]);
            }
        }
        $entry->save();

        event(new EntryUpdated($entry, $actorId));

        return $entry;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function publishDraftOf(Entry $draft, ?string $actorId): Entry
    {
        $handle = $draft->getHandle();
        if ($handle === null) {
            throw new SchemaException('Draft entry is not bound to a content type.');
        }

        $type = $this->registry->get($handle);
        if ($type === null) {
            throw new SchemaException("Unknown content type: \"{$handle}\".");
        }

        $draftOf = $draft->draft_of;
        if (! is_string($draftOf)) {
            throw new SchemaException('Draft entry has no valid draft_of reference.');
        }

        /** @var Entry $published */
        $published = Entry::type($handle)->findOrFail($draftOf);

        // Snapshot the published state before overwriting it.
        $this->createRevision($published, $handle, $actorId);

        // Copy all field values from the draft to the published entry.
        foreach ($type->columnFields() as $field) {
            $published->setAttribute($field->handle, $draft->getAttribute($field->handle));
        }
        $published->published_at = now();
        $published->save();

        // Remove the draft.
        $draft->delete();

        event(new EntryPublished($published, $actorId));

        return $published;
    }

    private function createRevision(Entry $entry, string $typeHandle, ?string $actorId): void
    {
        $type = $this->registry->get($typeHandle);
        if ($type === null) {
            return;
        }

        $payload = [];
        foreach ($type->columnFields() as $field) {
            $payload[$field->handle] = $entry->getAttribute($field->handle);
        }

        Revision::create([
            'entry_type' => $typeHandle,
            'entry_id' => $entry->getKey(),
            'payload' => $payload,
            'author_id' => $actorId,
        ]);
    }

    private function getType(Entry $entry): ContentType
    {
        $handle = $entry->getHandle();
        if ($handle === null) {
            throw new SchemaException('Entry is not bound to a content type.');
        }

        $type = $this->registry->get($handle);
        if ($type === null) {
            throw new SchemaException("Unknown content type: \"{$handle}\".");
        }

        return $type;
    }

    /**
     * Auto-populate slug fields from their configured source field.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyAutoSlugs(ContentType $type, array $data): array
    {
        foreach ($type->fields as $field) {
            if (! ($field->type instanceof SlugField)) {
                continue;
            }

            $rawSlug = $data[$field->handle] ?? null;
            $currentSlug = is_string($rawSlug) ? $rawSlug : '';
            if ($currentSlug !== '') {
                continue;
            }

            $fromHandle = $field->rawData['from'] ?? null;
            if (! is_string($fromHandle) || $fromHandle === '') {
                continue;
            }

            $sourceValue = $data[$fromHandle] ?? null;
            if (! is_string($sourceValue) || $sourceValue === '') {
                continue;
            }

            $rawLocale = $data['locale'] ?? null;
            $locale = is_string($rawLocale) ? $rawLocale : '';

            $data[$field->handle] = $this->slugs->generate(
                $type,
                $field->handle,
                $sourceValue,
                $locale !== '' ? $locale : null,
            );
        }

        return $data;
    }
}
