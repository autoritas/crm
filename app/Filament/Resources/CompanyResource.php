<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?int $navigationSort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la empresa')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color principal'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Imagen corporativa')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo (cabecera)')
                            ->helperText('Acepta SVG, PNG, JPG, WEBP.')
                            ->disk('public')
                            ->directory('companies/logos')
                            ->visibility('public')
                            ->rules(['file', 'max:2048'])
                            ->acceptedFileTypes([
                                'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp', 'image/gif',
                                'text/xml', 'text/plain', 'text/html', 'application/xml',
                            ]),
                        Forms\Components\FileUpload::make('icon_path')
                            ->label('Icono (favicon)')
                            ->helperText('Acepta SVG, PNG, JPG, WEBP.')
                            ->disk('public')
                            ->directory('companies/icons')
                            ->visibility('public')
                            ->rules(['file', 'max:1024'])
                            ->acceptedFileTypes([
                                'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp', 'image/gif',
                                'text/xml', 'text/plain', 'text/html', 'application/xml',
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('Modelo Go / No Go')
                    ->description('Criterios que la IA usara para evaluar si una oferta es GO o NO GO. Se envia completo al modelo de IA junto con los pliegos.')
                    ->schema([
                        Forms\Components\Textarea::make('go_nogo_model')
                            ->label('Modelo de decision Go / No Go')
                            ->rows(15)
                            ->helperText('Pega aqui el modelo completo de criterios. La IA lo usara como referencia para analizar cada pliego.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Kanboard')
                    ->schema([
                        Forms\Components\TextInput::make('kanboard_project_id')
                            ->label('ID Proyecto Kanboard')
                            ->numeric()
                            ->helperText('El ID del proyecto en Kanboard para esta empresa.'),
                        Forms\Components\Repeater::make('kanboardColumns')
                            ->relationship()
                            ->label('Columnas del tablero')
                            ->schema([
                                Forms\Components\TextInput::make('kanboard_column_id')
                                    ->label('ID Columna')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\TextInput::make('position')
                                    ->label('Posicion')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('description')
                                    ->label('Descripcion'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Añadir columna')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => ($state['name'] ?? '') . ' (ID: ' . ($state['kanboard_column_id'] ?? '') . ')'),
                    ]),

                Forms\Components\Section::make('APIs de IA')
                    ->description('Cada empresa puede tener sus propias claves de API para servicios de IA.')
                    ->schema([
                        Forms\Components\Repeater::make('apiCredentials')
                            ->relationship(
                                'apiCredentials',
                                modifyQueryUsing: fn ($query, $record) => $record
                                    ? $query->where('id_company', $record->id)
                                    : $query
                            )
                            ->label('')
                            ->schema([
                                Forms\Components\Hidden::make('id_company')
                                    ->default(fn ($livewire) => $livewire->record?->id),
                                Forms\Components\Select::make('service')
                                    ->label('Servicio')
                                    ->options([
                                        'openai' => 'OpenAI',
                                        'anthropic' => 'Anthropic (Claude)',
                                        'n8n' => 'n8n',
                                        'kanboard' => 'Kanboard API',
                                        'other' => 'Otro',
                                    ])
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('label')
                                    ->label('Etiqueta')
                                    ->placeholder('Ej: OpenAI Produccion')
                                    ->columnSpan(1),
                                Forms\Components\Textarea::make('api_key')
                                    ->label('API Key')
                                    ->rows(1)
                                    ->columnSpan(2),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Activa')
                                    ->default(true)
                                    ->columnSpan(1),
                            ])
                            ->columns(5)
                            ->defaultItems(0)
                            ->addActionLabel('Añadir credencial')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                strtoupper($state['service'] ?? '') . ' - ' . ($state['label'] ?? 'Sin etiqueta')
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('icon_path')
                    ->label('Icono')
                    ->disk('public')
                    ->circular()
                    ->size(32),
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->height(30),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
