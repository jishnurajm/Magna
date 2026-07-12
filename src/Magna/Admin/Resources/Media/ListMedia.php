<?php

declare(strict_types=1);

namespace Magna\Admin\Resources\Media;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Magna\Admin\Resources\MediaResource;
use Magna\Admin\Widgets\MediaStatsWidget;
use Magna\Media\Media;
use Magna\Media\MediaFolder;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected string $view = 'magna::admin.media-list';

    /** Live-search value wired from the blade search input. */
    public string $gallerySearch = '';

    /** Active category filter (images|pdf|video|others) from the stats widget. */
    public ?string $categoryFilter = null;

    /** Holds data for the in-grid preview modal; null = closed. */
    public ?array $galleryPreview = null;

    public function getHeading(): string
    {
        return 'Media Library';
    }

    public function getSubheading(): ?string
    {
        return 'Manage and monitor your digital assets allocation';
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [MediaStatsWidget::class];
    }

    /** Single column so the stats widget spans the full content width. */
    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /** Set by the MediaStatsWidget when a category card is clicked. */
    #[On('media-category-selected')]
    public function onCategorySelected(?string $category): void
    {
        $this->categoryFilter = $category;
        $this->resetPage('mpage');
    }

    /**
     * Constrain a media query to a mime-type category. Public so the resource
     * table (list view) can call it via modifyQueryUsing.
     *
     * @param  Builder<Media>  $query
     */
    public function applyCategory(Builder $query): void
    {
        match ($this->categoryFilter) {
            'images' => $query->where('mime_type', 'like', 'image/%'),
            'pdf' => $query->where('mime_type', 'application/pdf'),
            'video' => $query->where('mime_type', 'like', 'video/%'),
            'others' => $query->where('mime_type', 'not like', 'image/%')
                ->where('mime_type', '!=', 'application/pdf')
                ->where('mime_type', 'not like', 'video/%'),
            default => null,
        };
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Upload media')
                ->icon('heroicon-m-arrow-up-tray'),

            Action::make('recycleBin')
                ->label('Recycle Bin')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->url(fn (): string => MediaResource::getUrl('trash')),

            Action::make('newFolder')
                ->label('New folder')
                ->icon('heroicon-m-folder-plus')
                ->color('gray')
                ->modalWidth('sm')
                ->form([
                    TextInput::make('name')
                        ->label('Folder name')
                        ->required()
                        ->maxLength(255),

                    Select::make('parent_id')
                        ->label('Parent folder')
                        ->options(fn (): array => MediaFolder::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->nullable()
                        ->placeholder('— Root —'),
                ])
                ->action(function (array $data): void {
                    MediaFolder::create([
                        'name' => $data['name'],
                        'parent_id' => $data['parent_id'] ?? null,
                        'path' => $data['name'],
                    ]);

                    Notification::make()
                        ->title('Folder "'.$data['name'].'" created.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function previewGalleryItem(string $id): void
    {
        $item = Media::withoutTrashed()->find($id);
        if (! $item) {
            return;
        }

        $this->galleryPreview = [
            'url' => self::mediaUrl($item),
            'mime_type' => $item->mime_type,
            'filename' => filled($item->title) ? $item->title : $item->original_filename,
            'alt' => $item->alt ?? '',
            'width' => $item->width,
            'height' => $item->height,
        ];
    }

    public function closeGalleryPreview(): void
    {
        $this->galleryPreview = null;
    }

    public function deleteGalleryItem(string $id): void
    {
        $media = Media::withoutTrashed()->findOrFail($id);
        $media->delete();

        Notification::make()->title('Media moved to recycle bin.')->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $query = Media::query()
            ->withoutTrashed()
            ->latest()
            ->when($this->gallerySearch !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('original_filename', 'like', "%{$this->gallerySearch}%")
                ->orWhere('title', 'like', "%{$this->gallerySearch}%")
            ));

        $this->applyCategory($query);

        return [
            'galleryItems' => $query->paginate(24, ['*'], 'mpage'),
            'galleryFolders' => MediaFolder::query()
                ->orderBy('name')
                ->withCount('media')
                ->get(),
        ];
    }

    /** Generate a public URL for a media item. */
    public static function mediaUrl(Media $item): string
    {
        return Storage::disk($item->disk)->url($item->path);
    }
}
