<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientAliasResource\Pages;
use App\Models\Client;
use App\Models\ClientAlias;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientAliasResource extends Resource
{
    protected static ?string $model = ClientAlias::class;

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Sinonimos';

    protected static ?string $modelLabel = 'Sinonimo';

    protected static ?string $pluralModelLabel = 'Sinonimos de clientes';

    protected static ?int $navigationSort = 9;

    protected static function getCompanyId(): int
    {
        return (int) session('current_company_id', 1);
    }

    public static function form(Form $form): Form
    {
        $companyId = static::getCompanyId();

        return $form
            ->schema([
                Forms\Components\TextInput::make('raw_name')
                    ->label('Nombre original')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Select::make('id_client')
                    ->label('Cliente normalizado')
                    ->options(
                        Client::where('id_company', $companyId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->createOptionForm([
                        Forms\Components\Hidden::make('id_company')
                            ->default(fn () => session('current_company_id', 1)),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre normalizado')
                            ->required(),
                        Forms\Components\TextInput::make('cif')
                            ->label('CIF')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('province')
                            ->label('Provincia')
                            ->maxLength(100),
                    ])
                    ->createOptionUsing(function (array $data) {
                        return Client::create($data)->id;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = static::getCompanyId();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('id_company', $companyId))
            ->defaultSort('raw_name')
            ->defaultPaginationPageOption(100)
            ->paginationPageOptions([25, 50, 100])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->columns([
                Tables\Columns\TextColumn::make('raw_name')
                    ->label('Nombre original')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->raw_name),
                Tables\Columns\SelectColumn::make('id_client')
                    ->label('Cliente normalizado')
                    ->options(fn () =>
                        Client::where('id_company', static::getCompanyId())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->sortable()
                    ->placeholder('— Sin vincular —'),
                Tables\Columns\TextColumn::make('infonalia_count')
                    ->label('Registros')
                    ->getStateUsing(fn ($record) =>
                        \App\Models\InfonaliaData::where('id_company', $record->id_company)
                            ->where('cliente', $record->raw_name)
                            ->count()
                    )
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vinculacion')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->options([
                        'vinculado' => 'Vinculados',
                        'sin_vincular' => 'Sin vincular',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'vinculado' => $query->whereNotNull('id_client'),
                        'sin_vincular' => $query->whereNull('id_client'),
                        default => $query,
                    })
                    ->default('sin_vincular'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('vincular_cliente')
                        ->label('Vincular a cliente')
                        ->icon('heroicon-o-link')
                        ->form([
                            Forms\Components\Select::make('id_client')
                                ->label('Cliente normalizado')
                                ->options(
                                    Client::where('id_company', static::getCompanyId())
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\Hidden::make('id_company')
                                        ->default(fn () => session('current_company_id', 1)),
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nombre normalizado')
                                        ->required(),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    return Client::create($data)->id;
                                }),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $clientId = $data['id_client'];

                            foreach ($records as $alias) {
                                $alias->update(['id_client' => $clientId]);

                                // Actualizar los registros de infonalia vinculados
                                \App\Models\InfonaliaData::where('id_company', $alias->id_company)
                                    ->where('cliente', $alias->raw_name)
                                    ->update(['id_client' => $clientId]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientAliases::route('/'),
        ];
    }
}
