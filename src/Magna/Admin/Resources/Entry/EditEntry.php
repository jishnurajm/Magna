<?php

declare(strict_types=1);

namespace Magna\Admin\Resources\Entry;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Magna\Admin\Resources\EntryResource;
use Magna\Content\Entry;
use Magna\Content\EntryManager;
use Magna\Content\EntryStatus;
use Magna\Content\Models\Revision;

class EditEntry extends EditRecord
{
    protected static string $resource = EntryResource::class;

    #[Url(as: 'type')]
    public string $type = '';

    protected function resolveRecord(int|string $key): Model
    {
        $handle = EntryResource::getTypeHandleFromRequest();

        return Entry::type($handle)->findOrFail($key);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Entry $record */
        $record = $this->getRecord();

        return 'Edit '.(string) ($record->getAttribute('title') ?? $record->getAttribute('name') ?? 'Entry');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Entry $record */
        /** @var EntryManager $manager */
        $manager = app(EntryManager::class);

        return $manager->update($record, $data, auth()->id());
    }

    protected function getHeaderActions(): array
    {
        /** @var Entry $record */
        $record = $this->getRecord();

        return [
            // Save as draft
            Action::make('save_draft')
                ->label('Save draft')
                ->icon('heroicon-m-pencil')
                ->color('gray')
                ->action(fn () => $this->save())
                ->visible(fn (): bool => $record->status !== EntryStatus::Published),

            // Publish immediately
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-m-arrow-up-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->save();
                    app(EntryManager::class)->publish($this->getRecord());
                    $this->fillForm();
                })
                ->visible(fn (): bool => $record->status !== EntryStatus::Published),

            // Schedule publishing
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-m-clock')
                ->color('info')
                ->form([
                    DateTimePicker::make('publish_at')
                        ->label('Publish at')
                        ->required()
                        ->native(false)
                        ->minDate(now()),
                ])
                ->action(function (array $data): void {
                    $this->save();
                    $publishAt = Carbon::parse($data['publish_at']);
                    app(EntryManager::class)->publish($this->getRecord(), $publishAt);
                    $this->fillForm();
                }),

            // Unpublish
            Action::make('unpublish')
                ->label('Unpublish')
                ->icon('heroicon-m-arrow-down-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(EntryManager::class)->unpublish($this->getRecord());
                    $this->fillForm();
                })
                ->visible(fn (): bool => $record->status === EntryStatus::Published),

            // View revisions
            Action::make('revisions')
                ->label('Revisions')
                ->icon('heroicon-m-arrows-pointing-in')
                ->color('gray')
                ->modalHeading('Revision History')
                ->modalContent(function (): View {
                    /** @var Entry $entry */
                    $entry = $this->getRecord();
                    $revisions = Revision::query()
                        ->where('entry_id', $entry->getKey())
                        ->where('entry_type', $this->type)
                        ->orderByDesc('created_at')
                        ->limit(20)
                        ->get();

                    return view('magna::admin.entry-revisions', [
                        'entry' => $entry,
                        'revisions' => $revisions,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            DeleteAction::make()
                ->action(function (): void {
                    app(EntryManager::class)->delete($this->getRecord(), auth()->id());
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return EntryResource::getUrl('index', ['type' => $this->type]);
    }
}
