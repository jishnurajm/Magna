<?php

declare(strict_types=1);

namespace Magna\Admin\Resources\User;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Magna\Admin\Resources\UserResource;
use Magna\Users\User;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add user')
                ->icon('heroicon-m-user-plus')
                ->modalHeading('Add user')
                // Accounts created by an admin are trusted, so mark the email
                // verified immediately instead of sending a verification link.
                ->after(function (User $record): void {
                    if ($record->email_verified_at === null) {
                        $record->forceFill(['email_verified_at' => now()])->saveQuietly();
                    }
                }),
        ];
    }
}
