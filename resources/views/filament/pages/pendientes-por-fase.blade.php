@php
    $phases = $this->getKanboardPhases();
    $phaseColors = $this->getPhaseColors();
    $pendientesOffers = $this->getPendientesOffers();
    $phaseCounts = $this->getPhaseCounts();
    $totalCount = array_sum($phaseCounts);
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filtro por fase --}}
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <button wire:click="$set('phaseFilter', 'all')"
                style="padding: 6px 18px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer;
                       border: 2px solid {{ $this->phaseFilter === 'all' ? '#3b82f6' : '#e5e7eb' }};
                       background: {{ $this->phaseFilter === 'all' ? '#3b82f6' : 'white' }};
                       color: {{ $this->phaseFilter === 'all' ? 'white' : '#6b7280' }};">
                Todos ({{ $totalCount }})
            </button>
            @foreach($phases as $phase)
                @php
                    $color = $phaseColors[$phase] ?? '#6b7280';
                    $count = $phaseCounts[$phase] ?? 0;
                @endphp
                <button wire:click="$set('phaseFilter', '{{ $phase }}')"
                    style="padding: 6px 18px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer;
                           border: 2px solid {{ $this->phaseFilter === $phase ? $color : '#e5e7eb' }};
                           background: {{ $this->phaseFilter === $phase ? $color : 'white' }};
                           color: {{ $this->phaseFilter === $phase ? 'white' : $color }};">
                    {{ $phase }} ({{ $count }})
                </button>
            @endforeach

            <span style="font-size: 12px; color: #9ca3af; margin-left: auto;">{{ count($pendientesOffers) }} ofertas</span>
        </div>

        {{-- Tabla de ofertas --}}
        @if(empty($pendientesOffers))
            <div style="text-align: center; padding: 60px; color: #9ca3af;">
                <svg xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px; margin: 0 auto 12px; color: #d1d5db;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <p style="font-size: 16px; font-weight: 600;">No hay ofertas pendientes con este filtro</p>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Fase</th>
                            <th style="padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Categoria</th>
                            <th style="padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Codigo</th>
                            <th style="padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Cliente</th>
                            <th style="padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Objeto</th>
                            <th style="padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Tipo</th>
                            <th style="padding: 10px 14px; text-align: right; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Presupuesto</th>
                            <th style="padding: 10px 14px; text-align: center; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Presentacion</th>
                            <th style="padding: 10px 14px; text-align: center; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendientesOffers as $offer)
                            @php $phColor = $phaseColors[$offer['kb_phase']] ?? '#6b7280'; @endphp
                            <tr style="border-bottom: 1px solid #f3f4f6; transition: background 0.15s;"
                                onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 10px 14px;">
                                    <span style="font-size: 10px; font-weight: 700; color: white; background: {{ $phColor }}; padding: 2px 10px; border-radius: 10px; text-transform: uppercase; white-space: nowrap;">
                                        {{ $offer['kb_phase'] }}
                                    </span>
                                </td>
                                <td style="padding: 10px 14px; white-space: nowrap;">
                                    @if(!empty($offer['kb_category']))
                                        <span style="font-size: 10px; color: #4b5563; background: #eef2ff; padding: 2px 8px; border-radius: 8px; font-weight: 600;">
                                            {{ $offer['kb_category'] }}
                                        </span>
                                    @else
                                        <span style="color: #d1d5db; font-size: 12px;">—</span>
                                    @endif
                                </td>
                                <td style="padding: 10px 14px; font-size: 12px; color: #9ca3af; font-weight: 600; white-space: nowrap;">
                                    {{ $offer['codigo'] ?? '—' }}
                                </td>
                                <td style="padding: 10px 14px; font-weight: 600; color: #1f2937; max-width: 220px;" title="{{ $offer['cliente'] }}">
                                    {{ Str::limit($offer['cliente'], 45) }}
                                </td>
                                <td style="padding: 10px 14px; color: #6b7280; max-width: 280px; font-size: 12px;" title="{{ $offer['objeto'] }}">
                                    {{ Str::limit($offer['objeto'], 70) }}
                                </td>
                                <td style="padding: 10px 14px; white-space: nowrap;">
                                    @if($offer['tipo'])
                                        <span style="font-size: 10px; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 8px;">{{ $offer['tipo'] }}</span>
                                    @endif
                                </td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: #059669; white-space: nowrap;">
                                    @if($offer['presupuesto'])
                                        {{ number_format($offer['presupuesto'], 0, ',', '.') }} €
                                    @endif
                                </td>
                                <td style="padding: 10px 14px; text-align: center; color: #9ca3af; font-size: 12px; white-space: nowrap;">
                                    @if($offer['fecha_presentacion'])
                                        {{ \Carbon\Carbon::parse($offer['fecha_presentacion'])->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td style="padding: 10px 14px; text-align: center;">
                                    @if($offer['url'])
                                        <a href="{{ $offer['url'] }}" target="_blank" style="font-size: 11px; color: #3b82f6; text-decoration: none; font-weight: 600;">
                                            Ver ↗
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</x-filament-panels::page>
