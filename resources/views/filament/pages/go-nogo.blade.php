<x-filament-panels::page>
    @php
        $offers = $this->getProspectsOffers();
    @endphp

    @if(empty($offers))
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px;">
            <svg xmlns="http://www.w3.org/2000/svg" style="width: 64px; height: 64px; color: #86efac; margin-bottom: 16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <h2 style="font-size: 20px; font-weight: 700; color: #6b7280;">Sin ofertas en Prospects</h2>
            <p style="color: #9ca3af;">No hay ofertas pendientes de decision Go / No Go.</p>
        </div>
    @else
        <div style="margin-bottom: 12px; font-size: 13px; color: #6b7280;">
            {{ count($offers) }} ofertas en Prospects pendientes de decision
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px;">
            @foreach($offers as $offer)
                @php
                    $iaColor = match($offer['ia_go_nogo']) {
                        'GO' => '#22c55e',
                        'GO_TACTICO' => '#f59e0b',
                        'NO_GO' => '#ef4444',
                        default => '#94a3b8',
                    };
                    $iaLabel = match($offer['ia_go_nogo']) {
                        'GO' => '🟢 GO',
                        'GO_TACTICO' => '🟡 GO TÁCTICO',
                        'NO_GO' => '🔴 NO GO',
                        default => '⏳ Pendiente IA',
                    };
                @endphp

                <div style="background: white; border: 1px solid #e5e7eb; border-left: 5px solid {{ $iaColor }}; border-radius: 12px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">

                    {{-- Header --}}
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 13px; font-weight: 800; color: {{ $iaColor }}; background: {{ $iaColor }}15; padding: 3px 12px; border-radius: 8px;">
                                {{ $iaLabel }}
                            </span>
                            @if($offer['codigo'])
                                <span style="font-size: 11px; color: #9ca3af; font-weight: 600;">{{ $offer['codigo'] }}</span>
                            @endif
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            @if($offer['fecha_presentacion'])
                                <span style="font-size: 11px; color: #9ca3af;">
                                    {{ \Carbon\Carbon::parse($offer['fecha_presentacion'])->format('d/m/Y') }}
                                </span>
                            @endif
                            @if($offer['url'])
                                <a href="{{ $offer['url'] }}" target="_blank" style="font-size: 11px; color: #3b82f6; text-decoration: none; font-weight: 600;">Pliego ↗</a>
                            @endif
                        </div>
                    </div>

                    {{-- Cliente + Objeto --}}
                    <p style="font-size: 14px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0;" title="{{ $offer['cliente'] }}">
                        {{ $offer['cliente'] }}
                    </p>
                    <p style="font-size: 12px; color: #6b7280; margin: 0 0 8px 0; line-height: 1.5;" title="{{ $offer['objeto'] }}">
                        {{ Str::limit($offer['objeto'], 200) }}
                    </p>

                    {{-- Presupuesto --}}
                    @if($offer['presupuesto'])
                        <p style="font-size: 15px; font-weight: 800; color: #059669; margin: 0 0 10px 0;">
                            {{ number_format($offer['presupuesto'], 0, ',', '.') }} €
                        </p>
                    @endif

                    {{-- Análisis IA --}}
                    @if($offer['ia_analysis'])
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                            <p style="font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin: 0 0 6px 0;">
                                Analisis IA
                                @if($offer['ia_date'])
                                    · {{ \Carbon\Carbon::parse($offer['ia_date'])->format('d/m/Y H:i') }}
                                @endif
                            </p>
                            <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.6; white-space: pre-line;">{{ $offer['ia_analysis'] }}</p>
                        </div>
                    @else
                        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px; margin-bottom: 12px;">
                            <p style="font-size: 11px; color: #b45309; margin: 0;">⏳ Pendiente de analisis IA. Se analizara cuando haya pliegos adjuntos en Kanboard.</p>
                        </div>
                    @endif

                    {{-- Botones de decision --}}
                    <div style="display: flex; gap: 10px; border-top: 1px solid #f3f4f6; padding-top: 12px;">
                        <button wire:click="decideGo({{ $offer['id'] }})" wire:loading.attr="disabled"
                            style="padding: 8px 20px; font-size: 13px; font-weight: 700; border-radius: 8px; background: #22c55e; color: white; border: none; cursor: pointer;">
                            🟢 GO
                        </button>
                        <button wire:click="decideGoTactico({{ $offer['id'] }})" wire:loading.attr="disabled"
                            style="padding: 8px 20px; font-size: 13px; font-weight: 700; border-radius: 8px; background: #f59e0b; color: white; border: none; cursor: pointer;">
                            🟡 GO Tactico
                        </button>
                        <button wire:click="decideNoGo({{ $offer['id'] }})" wire:loading.attr="disabled"
                            onclick="return confirm('¿Seguro? Se descartara la oferta y se cerrara la tarea en Kanboard.')"
                            style="padding: 8px 20px; font-size: 13px; font-weight: 700; border-radius: 8px; background: #ef4444; color: white; border: none; cursor: pointer;">
                            🔴 NO GO
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
