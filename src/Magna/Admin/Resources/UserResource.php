<?php

declare(strict_types=1);

namespace Magna\Admin\Resources;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Magna\Admin\Resources\User\EditUser;
use Magna\Admin\Resources\User\ListUsers;
use Magna\Auth\Role;
use Magna\Users\User;
use Magna\Users\UserStatus;

class UserResource extends \Filament\Resources\Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return string[] */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(12)
                // Required only when creating; the 'hashed' cast on the model
                // hashes it. On edit, a blank value is dropped so the current
                // password is kept.
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Minimum 12 characters. Leave blank to keep the current password when editing.'),

            Select::make('status')
                ->label('Status')
                ->options([
                    UserStatus::Active->value => 'Active',
                    UserStatus::Suspended->value => 'Suspended',
                ])
                ->default(UserStatus::Active->value)
                ->required(),

            Select::make('roles')
                ->label('Roles')
                ->multiple()
                ->relationship('roles', 'name')
                ->options(
                    fn (): array => Role::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all(),
                )
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => UserStatus::Active->value,
                        'danger' => UserStatus::Suspended->value,
                    ])
                    ->formatStateUsing(
                        fn (UserStatus $state): string => match ($state) {
                            UserStatus::Active => 'Active',
                            UserStatus::Suspended => 'Suspended',
                        },
                    ),

                IconColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('warning'),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('users.manage') ?? false),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
