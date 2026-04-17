<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Administra empresas.
 *
 * La identidad (`name`) viene de Core y NO se edita aqui.
 * Todo lo demas (branding, Kanboard, modelo Go/NoGo) vive en la
 * tabla local `company_settings` y se mapea al form via hooks en
 * la pagina EditCompany.
 */
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
                Forms\Components\Section::make('Identidad (Core)')
                    ->description('Estos datos se gestionan en Stockflow Core y no son editables desde el CRM.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(1),

                Forms\Components\Section::make('Ajustes locales del CRM')
                    ->schema([
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color principal'),
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
                        Forms\Components\TextInput::make('kanboard_default_category_id')
                            ->label('Categoria por defecto')
                            ->numeric()
                            ->helperText('ID de la categoria CONCURSO en Kanboard.'),
                        Forms\Components\TextInput::make('kanboard_default_owner_id')
                            ->label('Owner por defecto')
                            ->numeric()
                            ->helperText('ID del usuario asignado por defecto en Kanboard.'),
                        Forms\Components\Placeholder::make('workflow_info')
                            ->label('Fases / columnas del tablero')
                            ->content(fn () => new \Illuminate\Support\HtmlString(
                                'Las fases del workflow y su asociacion a columnas Kanboard se gestionan ahora en '
                                . '<a href="' . route('filament.admin.resources.offer-workflows.index') . '" '
                                . 'class="text-primary-600 underline">Admin Comercial · Estados Workflow</a>.'
                            )),
                    ]),

                Forms\Components\Section::make('APIs de IA')
                    ->description('Cada empresa puede tener sus propias claves de API para servicios de IA.')
                    ->schema([
                        Forms\Components\Repeater::make('apiCredentials')
                            ->relationship(
                                'apiCredentials',
                                modifyQueryUsing: fn ($query, $record) => $record
                                    ? $query->where('company_id', $record->id)
                                    : $query
                            )
                            ->label('')
                            ->schema([
                                Forms\Components\Hidden::make('company_id')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('settings.slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('settings.kanboard_project_id')
                    ->label('Kanboard project')
                    ->badge(),
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
            ->bulkActions([]);
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
