@php
    $companyId = (int) session('current_company_id', 1);
    $kpi = new \App\Services\KpiService($companyId);
    $p = $kpi->pendientes();
    $of = $kpi->ofertas();
    $ld = $kpi->leads();
@endphp

<x-filament-panels::page>
    <div x-data="{ tab: '{{ $this->activeTab }}' }">

        {{-- TABS --}}
        <div style="border-bottom: 2px solid #e5e7eb; margin-bottom: 24px;">
            <nav style="display: flex; gap: 32px; margin-bottom: -2px;">
                @foreach(['home' => 'Home', 'pendientes' => 'Pendientes', 'ofertas_tab' => 'Ofertas', 'periodo' => 'Periodo', 'detalle' => 'Detalle', 'competencia' => 'Competencia'] as $key => $label)
                    <button x-on:click="tab = '{{ $key }}'; $wire.set('activeTab', '{{ $key }}')"
                        :style="tab === '{{ $key }}'
                            ? 'border-bottom: 3px solid #3b82f6; color: #3b82f6; font-weight: 600; padding-bottom: 10px; font-size: 14px; background: none; border-top: none; border-left: none; border-right: none; cursor: pointer;'
                            : 'border-bottom: 3px solid transparent; color: #9ca3af; padding-bottom: 10px; font-size: 14px; background: none; border-top: none; border-left: none; border-right: none; cursor: pointer;'">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ==================== HOME ==================== --}}
        <div x-show="tab === 'home'" x-cloak>

            {{-- ===== BLOQUE PENDIENTES ===== --}}
            <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fbbf24; border-radius: 16px; padding: 24px; box-shadow: 0 4px 15px rgba(251, 191, 36, 0.15), 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px;">

                {{-- Header --}}
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: #f59e0b; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 22px; height: 22px; color: white;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #92400e; margin: 0;">Pendientes de decidir</h2>
                        <p style="font-size: 13px; color: #b45309; margin: 0;">{{ $p['total_count'] }} ofertas activas · {{ number_format($p['total_importe'], 0, ',', '.') }} €</p>
                    </div>
                </div>

                @php $phases = $p['phases']; @endphp

                {{-- Barra: Numero de ofertas --}}
                <div style="margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 11px; color: #92400e; font-weight: 600; width: 60px; text-align: right;">Ofertas</span>
                        <div style="flex: 1;">
                            <div style="display: flex; border-radius: 8px; overflow: hidden; height: 42px; background: #fde68a;">
                                @foreach($phases as $name => $ph)
                                    @if($ph['count'] > 0)
                                    <div style="width: {{ max($ph['pct_count'], 6) }}%; background: {{ $ph['color'] }}; border-right: 2px solid rgba(255,255,255,0.3);"></div>
                                    @endif
                                @endforeach
                            </div>
                            <div style="display: flex; margin-top: 4px;">
                                @foreach($phases as $name => $ph)
                                    @if($ph['count'] > 0)
                                    <div style="width: {{ max($ph['pct_count'], 6) }}%; text-align: center;">
                                        <span style="font-size: 11px; font-weight: 700; color: {{ $ph['color'] }};">{{ $ph['count'] }}</span>
                                        <span style="font-size: 9px; color: #92400e;"> ({{ $ph['pct_count'] }}%)</span>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Barra: Importe --}}
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 11px; color: #92400e; font-weight: 600; width: 60px; text-align: right;">Importe</span>
                        <div style="flex: 1;">
                            <div style="display: flex; border-radius: 8px; overflow: hidden; height: 42px; background: #fde68a;">
                                @foreach($phases as $name => $ph)
                                    @if($ph['importe'] > 0)
                                    <div style="width: {{ max($ph['pct_importe'], 6) }}%; background: {{ $ph['color'] }}; opacity: 0.85; border-right: 2px solid rgba(255,255,255,0.3);"></div>
                                    @endif
                                @endforeach
                            </div>
                            <div style="display: flex; margin-top: 4px;">
                                @foreach($phases as $name => $ph)
                                    @if($ph['importe'] > 0)
                                    <div style="width: {{ max($ph['pct_importe'], 6) }}%; text-align: center;">
                                        <span style="font-size: 10px; font-weight: 700; color: {{ $ph['color'] }};">{{ number_format($ph['importe'], 0, ',', '.') }} €</span>
                                        <span style="font-size: 9px; color: #92400e;"> ({{ $ph['pct_importe'] }}%)</span>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Leyenda --}}
                <div style="display: flex; gap: 20px; margin-left: 72px; flex-wrap: wrap;">
                    @foreach($phases as $name => $ph)
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div style="width: 12px; height: 12px; border-radius: 3px; background: {{ $ph['color'] }};"></div>
                            <span style="font-size: 11px; color: #78716c;">{{ $name }} ({{ $ph['count'] }} · {{ $ph['pct_count'] }}%)</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ===== BLOQUE OFERTAS ===== --}}
            <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; border-radius: 16px; padding: 24px; box-shadow: 0 4px 15px rgba(134, 239, 172, 0.15), 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px;">

                {{-- Header --}}
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: #22c55e; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 22px; height: 22px; color: white;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    </div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #166534; margin: 0;">Ofertas</h2>
                        <p style="font-size: 13px; color: #15803d; margin: 0;">Ganadas, Pendientes y Perdidas</p>
                    </div>
                </div>

                @php
                    $rows = [
                        ['label' => 'Total historico', 'data' => $of['total']],
                        ['label' => 'Ultimos 12 meses', 'data' => $of['u12']],
                        ['label' => 'Año ' . $of['year'], 'data' => $of['year_actual']],
                    ];
                @endphp

                @foreach($rows as $idx => $row)
                    @php $d = $row['data']; @endphp

                    @if($idx > 0)
                        <div style="border-top: 1px solid #bbf7d0; margin: 16px 0;"></div>
                    @endif

                    <div style="margin-bottom: 4px;">
                        <span style="font-size: 11px; color: #166534; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">{{ $row['label'] }}</span>
                        <span style="font-size: 11px; color: #15803d; margin-left: 8px;">{{ $d['total_count'] }} ofertas</span>
                    </div>

                    @php
                        $segments = [
                            ['key' => 'gan', 'color' => '#22c55e', 'colorLight' => '#16a34a', 'icon' => '✓'],
                            ['key' => 'pen', 'color' => '#f59e0b', 'colorLight' => '#d97706', 'icon' => '⏳'],
                            ['key' => 'per', 'color' => '#ef4444', 'colorLight' => '#dc2626', 'icon' => '✗'],
                        ];
                    @endphp

                    {{-- Barra: Numero --}}
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 2px;">
                        <span style="font-size: 10px; color: #15803d; font-weight: 600; width: 55px; text-align: right;">Numero</span>
                        <div style="flex: 1;">
                            <div style="display: flex; border-radius: 8px; overflow: hidden; height: 36px; background: #d1fae5;">
                                @foreach($segments as $seg)
                                    @if($d[$seg['key'].'_count'] > 0)
                                    <div style="width: {{ max($d['pct_'.$seg['key'].'_count'], 6) }}%; background: {{ $seg['color'] }}; border-right: 2px solid rgba(255,255,255,0.4);"></div>
                                    @endif
                                @endforeach
                            </div>
                            <div style="display: flex; margin-top: 3px;">
                                @foreach($segments as $seg)
                                    @if($d[$seg['key'].'_count'] > 0)
                                    <div style="width: {{ max($d['pct_'.$seg['key'].'_count'], 6) }}%; text-align: center;">
                                        <span style="font-size: 11px; font-weight: 700; color: {{ $seg['color'] }};">{{ $seg['icon'] }} {{ number_format($d[$seg['key'].'_count']) }}</span>
                                        <span style="font-size: 9px; color: #15803d;"> ({{ $d['pct_'.$seg['key'].'_count'] }}%)</span>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Barra: Importe --}}
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 10px; color: #15803d; font-weight: 600; width: 55px; text-align: right;">Importe</span>
                        <div style="flex: 1;">
                            <div style="display: flex; border-radius: 8px; overflow: hidden; height: 36px; background: #d1fae5;">
                                @foreach($segments as $seg)
                                    @if($d[$seg['key'].'_importe'] > 0)
                                    <div style="width: {{ max($d['pct_'.$seg['key'].'_importe'], 6) }}%; background: {{ $seg['colorLight'] }}; border-right: 2px solid rgba(255,255,255,0.4);"></div>
                                    @endif
                                @endforeach
                            </div>
                            <div style="display: flex; margin-top: 3px;">
                                @foreach($segments as $seg)
                                    @if($d[$seg['key'].'_importe'] > 0)
                                    <div style="width: {{ max($d['pct_'.$seg['key'].'_importe'], 6) }}%; text-align: center;">
                                        <span style="font-size: 10px; font-weight: 700; color: {{ $seg['colorLight'] }};">{{ number_format($d[$seg['key'].'_importe'], 0, ',', '.') }} €</span>
                                        <span style="font-size: 9px; color: #15803d;"> ({{ $d['pct_'.$seg['key'].'_importe'] }}%)</span>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                @endforeach

                {{-- Leyenda --}}
                <div style="display: flex; gap: 24px; margin-left: 67px; margin-top: 14px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 12px; height: 12px; border-radius: 3px; background: #22c55e;"></div>
                        <span style="font-size: 11px; color: #78716c;">Ganadas</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 12px; height: 12px; border-radius: 3px; background: #f59e0b;"></div>
                        <span style="font-size: 11px; color: #78716c;">Pendientes</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 12px; height: 12px; border-radius: 3px; background: #ef4444;"></div>
                        <span style="font-size: 11px; color: #78716c;">Perdidas</span>
                    </div>
                </div>
            </div>

            {{-- ===== BLOQUE LEADS ===== --}}
            <div style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border: 1px solid #c084fc; border-radius: 16px; padding: 24px; box-shadow: 0 4px 15px rgba(192, 132, 252, 0.15), 0 1px 3px rgba(0,0,0,0.08);">

                {{-- Header --}}
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: #a855f7; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 22px; height: 22px; color: white;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                    </div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #6b21a8; margin: 0;">Leads (Concursos)</h2>
                        <p style="font-size: 12px; color: #7e22ce; margin: 2px 0 0 0;">
                            @foreach($ld['statuses'] as $s)
                                <span style="color: {{ $s->color }}; font-weight: 700;">{{ $s->status }}</span>
                                @if(!$loop->last) <span style="color: #c4b5fd;">/</span> @endif
                            @endforeach
                        </p>
                    </div>
                </div>

                @php
                    $quarters = $ld['quarters'];
                    $statuses = $ld['statuses'];
                    $maxCount = $ld['max_count'];
                    $maxImporte = $ld['max_importe'];
                @endphp

                {{-- GRAFICA 1: Numero de operaciones --}}
                <div style="margin-bottom: 4px;">
                    <span style="font-size: 11px; color: #6b21a8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Numero de operaciones</span>
                </div>
                <div style="display: flex; gap: 8px; padding: 0 4px;">
                    @foreach($quarters as $qKey => $q)
                        <div style="flex: 1; display: flex; flex-direction: column;">
                            {{-- Desglose por estado encima de la barra --}}
                            <div style="margin-bottom: 6px;">
                                @foreach($q['segments'] as $sId => $seg)
                                    @if($seg['count'] > 0)
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 2px;">
                                        @php $segPctCnt = $q['total_count'] > 0 ? round($seg['count'] / $q['total_count'] * 100, 1) : 0; @endphp
                                        <span style="font-size: 9px; color: {{ $seg['color'] }}; font-weight: 600;">{{ Str::limit($seg['status'], 8, '.') }}</span>
                                        <span style="font-size: 9px; color: {{ $seg['color'] }}; font-weight: 700;">{{ number_format($seg['count']) }} <span style="font-weight: 500;">({{ $segPctCnt }}%)</span></span>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                            {{-- Barra --}}
                            @php $barHeight = $maxCount > 0 ? round($q['total_count'] / $maxCount * 100) : 0; @endphp
                            <div style="flex: 1; display: flex; align-items: flex-end; min-height: 120px;">
                                <div style="width: 100%; height: {{ max($barHeight, 3) }}%; border-radius: 6px 6px 0 0; overflow: hidden; display: flex; flex-direction: column-reverse;">
                                    @foreach($q['segments'] as $sId => $seg)
                                        @if($seg['count'] > 0)
                                            @php $segPct = $q['total_count'] > 0 ? round($seg['count'] / $q['total_count'] * 100) : 0; @endphp
                                            <div style="width: 100%; height: {{ max($segPct, 2) }}%; background: {{ $seg['color'] }};" title="{{ $seg['status'] }}: {{ number_format($seg['count']) }}"></div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div style="display: flex; gap: 8px; padding: 0 4px; border-top: 1px solid #e9d5ff; padding-top: 6px;">
                    @foreach($quarters as $qKey => $q)
                        <div style="flex: 1; text-align: center;">
                            <span style="font-size: 10px; color: #7e22ce; font-weight: 600;">{{ $q['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div style="border-top: 1px solid #e9d5ff; margin: 16px 0;"></div>

                {{-- GRAFICA 2: Importe presupuesto --}}
                <div style="margin-bottom: 4px;">
                    <span style="font-size: 11px; color: #6b21a8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Importe presupuesto</span>
                </div>
                <div style="display: flex; gap: 8px; padding: 0 4px;">
                    @foreach($quarters as $qKey => $q)
                        <div style="flex: 1; display: flex; flex-direction: column;">
                            {{-- Desglose por estado --}}
                            <div style="margin-bottom: 6px;">
                                @foreach($q['segments'] as $sId => $seg)
                                    @if($seg['importe'] > 0)
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 2px;">
                                        <span style="font-size: 9px; color: {{ $seg['color'] }}; font-weight: 600;">{{ Str::limit($seg['status'], 8, '.') }}</span>
                                        @php $segPctImp = $q['total_importe'] > 0 ? round($seg['importe'] / $q['total_importe'] * 100, 1) : 0; @endphp
                                        <span style="font-size: 8px; color: {{ $seg['color'] }}; font-weight: 700;">{{ number_format($seg['importe'] / 1000000, 1, ',', '.') }}M <span style="font-weight: 500;">({{ $segPctImp }}%)</span></span>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                            {{-- Barra --}}
                            @php $barHeight = $maxImporte > 0 ? round($q['total_importe'] / $maxImporte * 100) : 0; @endphp
                            <div style="flex: 1; display: flex; align-items: flex-end; min-height: 120px;">
                                <div style="width: 100%; height: {{ max($barHeight, 3) }}%; border-radius: 6px 6px 0 0; overflow: hidden; display: flex; flex-direction: column-reverse;">
                                    @foreach($q['segments'] as $sId => $seg)
                                        @if($seg['importe'] > 0)
                                            @php $segPct = $q['total_importe'] > 0 ? round($seg['importe'] / $q['total_importe'] * 100) : 0; @endphp
                                            <div style="width: 100%; height: {{ max($segPct, 2) }}%; background: {{ $seg['color'] }};" title="{{ $seg['status'] }}: {{ number_format($seg['importe'], 0, ',', '.') }} €"></div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div style="display: flex; gap: 8px; padding: 0 4px; border-top: 1px solid #e9d5ff; padding-top: 6px;">
                    @foreach($quarters as $qKey => $q)
                        <div style="flex: 1; text-align: center;">
                            <span style="font-size: 10px; color: #7e22ce; font-weight: 600;">{{ $q['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Leyenda --}}
                <div style="display: flex; gap: 16px; margin-top: 14px; flex-wrap: wrap;">
                    @foreach($statuses as $s)
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; border-radius: 3px; background: {{ $s->color }};"></div>
                            <span style="font-size: 11px; color: #78716c;">{{ $s->status }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- ==================== TAB PENDIENTES ==================== --}}
        <div x-show="tab === 'pendientes'" x-cloak>
            @php
                $phases = $this->getKanboardPhases();
                $phaseColors = $this->getPhaseColors();
                $pendientesOffers = $this->getPendientesOffers();
            @endphp

            {{-- Filtro --}}
            <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
                <button wire:click="$set('pendientesFilter', 'all')"
                    style="padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; border: 2px solid {{ $this->pendientesFilter === 'all' ? '#3b82f6' : '#e5e7eb' }}; background: {{ $this->pendientesFilter === 'all' ? '#3b82f6' : 'white' }}; color: {{ $this->pendientesFilter === 'all' ? 'white' : '#6b7280' }};">
                    Todos ({{ count($this->getPendientesOffers()) }})
                </button>
                @foreach($phases as $phase)
                    @php $color = $phaseColors[$phase] ?? '#6b7280'; @endphp
                    <button wire:click="$set('pendientesFilter', '{{ $phase }}')"
                        style="padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; border: 2px solid {{ $this->pendientesFilter === $phase ? $color : '#e5e7eb' }}; background: {{ $this->pendientesFilter === $phase ? $color : 'white' }}; color: {{ $this->pendientesFilter === $phase ? 'white' : $color }};">
                        {{ $phase }}
                    </button>
                @endforeach
            </div>

            {{-- Cards --}}
            @if(empty($pendientesOffers))
                <div style="text-align: center; padding: 40px; color: #9ca3af;">
                    <p style="font-size: 16px; font-weight: 600;">No hay ofertas pendientes con este filtro</p>
                </div>
            @else
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 12px;">
                    @foreach($pendientesOffers as $offer)
                        @php $phColor = $phaseColors[$offer['kb_phase']] ?? '#6b7280'; @endphp
                        <div style="background: white; border: 1px solid #e5e7eb; border-left: 4px solid {{ $phColor }}; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                            {{-- Header: fase + codigo --}}
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 10px; font-weight: 700; color: white; background: {{ $phColor }}; padding: 2px 10px; border-radius: 10px; text-transform: uppercase;">
                                    {{ $offer['kb_phase'] }}
                                </span>
                                @if($offer['codigo'])
                                    <span style="font-size: 10px; color: #9ca3af; font-weight: 600;">{{ $offer['codigo'] }}</span>
                                @endif
                            </div>

                            {{-- Cliente --}}
                            <p style="font-size: 13px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; line-height: 1.3;" title="{{ $offer['cliente'] }}">
                                {{ Str::limit($offer['cliente'], 60) }}
                            </p>

                            {{-- Objeto --}}
                            <p style="font-size: 11px; color: #6b7280; margin: 0 0 10px 0; line-height: 1.4;" title="{{ $offer['objeto'] }}">
                                {{ Str::limit($offer['objeto'], 120) }}
                            </p>

                            {{-- Footer: presupuesto + fecha + link --}}
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f3f4f6; padding-top: 8px;">
                                <div>
                                    @if($offer['presupuesto'])
                                        <span style="font-size: 13px; font-weight: 800; color: #059669;">{{ number_format($offer['presupuesto'], 0, ',', '.') }} €</span>
                                    @endif
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    @if($offer['fecha_presentacion'])
                                        <span style="font-size: 11px; color: #9ca3af;">
                                            {{ \Carbon\Carbon::parse($offer['fecha_presentacion'])->format('d/m/Y') }}
                                        </span>
                                    @endif
                                    @if($offer['url'])
                                        <a href="{{ $offer['url'] }}" target="_blank" style="font-size: 11px; color: #3b82f6; text-decoration: none; font-weight: 600;">
                                            Ver ↗
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ==================== TAB OFERTAS ==================== --}}
        <div x-show="tab === 'ofertas_tab'" x-cloak>
            @php
                $ofCards = $this->getOfertasCards();
                $statusColors = $this->getOfertasStatusColors();
            @endphp

            {{-- Filtros --}}
            <div style="display: flex; gap: 24px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">

                {{-- Filtro Tiempo --}}
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 11px; color: #6b7280; font-weight: 600;">Periodo:</span>
                    @foreach(['all' => 'Todos', '12m' => 'Ultimos 12m', 'year' => 'Año ' . now()->year] as $val => $label)
                        <button wire:click="$set('ofertasTimeFilter', '{{ $val }}')"
                            style="padding: 5px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer; border: 2px solid {{ $this->ofertasTimeFilter === $val ? '#3b82f6' : '#e5e7eb' }}; background: {{ $this->ofertasTimeFilter === $val ? '#3b82f6' : 'white' }}; color: {{ $this->ofertasTimeFilter === $val ? 'white' : '#6b7280' }};">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Filtro Estado --}}
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 11px; color: #6b7280; font-weight: 600;">Estado:</span>
                    <button wire:click="$set('ofertasStatusFilter', 'all')"
                        style="padding: 5px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer; border: 2px solid {{ $this->ofertasStatusFilter === 'all' ? '#3b82f6' : '#e5e7eb' }}; background: {{ $this->ofertasStatusFilter === 'all' ? '#3b82f6' : 'white' }}; color: {{ $this->ofertasStatusFilter === 'all' ? 'white' : '#6b7280' }};">
                        Todos
                    </button>
                    @foreach(['ganadas' => 'Ganadas', 'pendientes' => 'Pendientes', 'perdidas' => 'Perdidas'] as $val => $label)
                        @php $sc = $statusColors[$val]; @endphp
                        <button wire:click="$set('ofertasStatusFilter', '{{ $val }}')"
                            style="padding: 5px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer; border: 2px solid {{ $this->ofertasStatusFilter === $val ? $sc : '#e5e7eb' }}; background: {{ $this->ofertasStatusFilter === $val ? $sc : 'white' }}; color: {{ $this->ofertasStatusFilter === $val ? 'white' : $sc }};">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <span style="font-size: 12px; color: #9ca3af; margin-left: auto;">{{ count($ofCards) }} ofertas</span>
            </div>

            {{-- Cards --}}
            @if(empty($ofCards))
                <div style="text-align: center; padding: 40px; color: #9ca3af;">
                    <p style="font-size: 16px; font-weight: 600;">No hay ofertas con estos filtros</p>
                </div>
            @else
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 12px;">
                    @foreach($ofCards as $offer)
                        <div style="background: white; border: 1px solid #e5e7eb; border-left: 4px solid {{ $offer['status_color'] }}; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                            {{-- Header: estado + codigo --}}
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 10px; font-weight: 700; color: white; background: {{ $offer['status_color'] }}; padding: 2px 10px; border-radius: 10px;">
                                    {{ $offer['status'] }}
                                </span>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    @if($offer['tipo'])
                                        <span style="font-size: 9px; color: #9ca3af; background: #f3f4f6; padding: 1px 8px; border-radius: 8px;">{{ $offer['tipo'] }}</span>
                                    @endif
                                    @if($offer['codigo'])
                                        <span style="font-size: 10px; color: #9ca3af; font-weight: 600;">{{ $offer['codigo'] }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Cliente --}}
                            <p style="font-size: 13px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; line-height: 1.3;" title="{{ $offer['cliente'] }}">
                                {{ Str::limit($offer['cliente'], 60) }}
                            </p>

                            {{-- Objeto --}}
                            <p style="font-size: 11px; color: #6b7280; margin: 0 0 10px 0; line-height: 1.4;" title="{{ $offer['objeto'] }}">
                                {{ Str::limit($offer['objeto'], 120) }}
                            </p>

                            {{-- Footer --}}
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f3f4f6; padding-top: 8px;">
                                <div>
                                    @if($offer['presupuesto'])
                                        <span style="font-size: 13px; font-weight: 800; color: #059669;">{{ number_format($offer['presupuesto'], 0, ',', '.') }} €</span>
                                    @endif
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    @if($offer['fecha_presentacion'])
                                        <span style="font-size: 11px; color: #9ca3af;">
                                            {{ \Carbon\Carbon::parse($offer['fecha_presentacion'])->format('d/m/Y') }}
                                        </span>
                                    @endif
                                    @if($offer['url'])
                                        <a href="{{ $offer['url'] }}" target="_blank" style="font-size: 11px; color: #3b82f6; text-decoration: none; font-weight: 600;">
                                            Ver ↗
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ==================== OTROS TABS ==================== --}}
        @foreach(['periodo' => 'Indicadores por periodo', 'detalle' => 'Indicadores detalle', 'competencia' => 'Indicadores de competencia'] as $tabKey => $tabTitle)
            <div x-show="tab === '{{ $tabKey }}'" x-cloak>
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px; color: #d1d5db; margin-bottom: 12px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <h2 style="font-size: 20px; font-weight: 700; color: #9ca3af;">Proximamente</h2>
                    <p style="font-size: 14px; color: #d1d5db;">{{ $tabTitle }}</p>
                </div>
            </div>
        @endforeach

    </div>
</x-filament-panels::page>
