<x-filament-panels::page>
    @php
        $groups = $this->getGroups();
        $statusOptions = $this->getStatusOptions();
        $negativeReasons = $this->getNegativeReasons();
        $positiveReasons = $this->getPositiveReasons();
        $allReasons = $negativeReasons + $positiveReasons;
    @endphp

    @if(empty($groups))
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px;">
            <svg xmlns="http://www.w3.org/2000/svg" style="width: 64px; height: 64px; color: #86efac; margin-bottom: 16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <h2 style="font-size: 20px; font-weight: 700; color: #6b7280;">Todo cribado</h2>
            <p style="color: #9ca3af;">No hay items pendientes con decision IA por revisar.</p>
        </div>
    @else
        {{-- Barra de acciones masivas --}}
        <div x-data="{ showBulkDecision: false, bulkDecisionId: '' }" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding: 10px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;">
            <button wire:click="confirmAll" wire:loading.attr="disabled"
                style="padding: 7px 16px; font-size: 12px; font-weight: 700; border-radius: 8px; background: #22c55e; color: white; border: none; cursor: pointer;"
                onclick="return confirm('¿Aceptar TODOS los items con la decision de la IA?')">
                ✓ Aceptar todos
            </button>

            <span style="color: #cbd5e1;">|</span>

            <span style="font-size: 12px; color: #64748b; font-weight: 600;">
                Seleccionados: <span style="color: #3b82f6;">{{ count($this->selectedItems) }}</span>
            </span>

            <button wire:click="confirmSelected" wire:loading.attr="disabled"
                style="padding: 6px 14px; font-size: 11px; font-weight: 600; border-radius: 8px; background: {{ count($this->selectedItems) > 0 ? '#3b82f6' : '#e2e8f0' }}; color: {{ count($this->selectedItems) > 0 ? 'white' : '#94a3b8' }}; border: none; cursor: pointer;">
                ✓ Aceptar seleccion
            </button>

            <button x-on:click="showBulkDecision = !showBulkDecision"
                style="padding: 6px 14px; font-size: 11px; font-weight: 600; border-radius: 8px; background: {{ count($this->selectedItems) > 0 ? '#f59e0b' : '#e2e8f0' }}; color: {{ count($this->selectedItems) > 0 ? 'white' : '#94a3b8' }}; border: none; cursor: pointer;">
                Cambiar seleccion a...
            </button>

            <div x-show="showBulkDecision" x-cloak style="display: flex; align-items: center; gap: 8px;">
                <select x-model="bulkDecisionId" style="font-size: 12px; border-radius: 6px; border: 1px solid #d1d5db; padding: 5px 8px;">
                    <option value="">Elegir...</option>
                    @foreach($statusOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <button x-on:click="if(bulkDecisionId) { $wire.call('bulkDecision', parseInt(bulkDecisionId)); showBulkDecision = false; }"
                    style="padding: 5px 12px; font-size: 11px; font-weight: 600; border-radius: 6px; background: #dc2626; color: white; border: none; cursor: pointer;">
                    Aplicar
                </button>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 20px;">
            @foreach($groups as $groupKey => $group)
                <div style="border-radius: 12px; border: 1px solid #e5e7eb; background: white; overflow: hidden;">
                    {{-- Header del grupo --}}
                    <div class="px-4 py-3 flex items-center gap-3" style="background: {{ $group['color'] }}15; border-left: 4px solid {{ $group['color'] }};">
                        <span class="inline-block w-3 h-3 rounded-full" style="background: {{ $group['color'] }};"></span>
                        <h3 class="text-lg font-semibold" style="color: {{ $group['color'] }};">
                            {{ $group['name'] }}
                        </h3>
                        <span class="text-sm text-gray-400">({{ count($group['items']) }} items)</span>
                    </div>

                    {{-- Items --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($group['items'] as $item)
                            <div style="padding: 10px 16px;" x-data="{ showOverride: false }">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    {{-- Checkbox --}}
                                    <div style="padding-top: 4px; flex-shrink: 0;">
                                        <input type="checkbox" value="{{ $item->id }}" wire:model.live="selectedItems"
                                            style="width: 16px; height: 16px; border-radius: 4px; cursor: pointer;">
                                    </div>
                                    {{-- Info --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                                {{ $item->cliente ?? 'Sin cliente' }}
                                            </span>
                                            @if($item->presupuesto)
                                                <span class="text-xs font-mono text-green-600 bg-green-50 px-2 py-0.5 rounded dark:bg-green-900/20">
                                                    {{ number_format($item->presupuesto, 2, ',', '.') }} €
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                                            {{ Str::limit($item->resumen_objeto, 200) }}
                                        </p>
                                        @if($item->ia_motivo)
                                            <p class="text-xs text-gray-400 italic mb-1">
                                                <span class="font-medium">IA:</span> {{ Str::limit($item->ia_motivo, 150) }}
                                            </p>
                                        @endif
                                        @if($item->url)
                                            <a href="{{ $item->url }}" target="_blank" class="text-xs text-primary-600 hover:underline">
                                                Ver licitacion →
                                            </a>
                                        @endif
                                    </div>

                                    {{-- Acciones: pulgar arriba / pulgar abajo --}}
                                    <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                                        {{-- Pulgar arriba: acepto la IA --}}
                                        <button
                                            wire:click="confirm({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                            style="display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: #dcfce7; color: #16a34a; border: 2px solid #86efac; cursor: pointer;"
                                            title="Acepto la decision de la IA"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 24px; height: 24px;"><path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777ZM2.331 10.727a.75.75 0 0 1 .874-.529 49.343 49.343 0 0 0 6.564.885.75.75 0 0 1-.12 1.495 50.846 50.846 0 0 1-6.79-.916.75.75 0 0 1-.528-.935Z" /></svg>
                                        </button>

                                        {{-- Pulgar abajo: rechazo la IA --}}
                                        <button
                                            x-on:click="showOverride = !showOverride"
                                            style="display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: #fee2e2; color: #dc2626; border: 2px solid #fca5a5; cursor: pointer;"
                                            title="Rechazo la decision de la IA"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 24px; height: 24px;"><path d="M15.73 5.5h1.035A7.465 7.465 0 0 1 18 9.625a7.465 7.465 0 0 1-1.235 4.125h-.148c-.806 0-1.534.446-2.031 1.08a9.04 9.04 0 0 1-2.861 2.4c-.723.384-1.35.956-1.653 1.715a4.498 4.498 0 0 0-.322 1.672V21a.75.75 0 0 1-.75.75 2.25 2.25 0 0 1-2.25-2.25c0-1.152.26-2.243.723-3.218.266-.558-.107-1.282-.725-1.282H3.622c-1.026 0-1.945-.694-2.054-1.715A12.134 12.134 0 0 1 1.5 12c0-2.848.992-5.464 2.649-7.521C4.537 3.997 5.136 3.75 5.754 3.75H9.77a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23ZM21.669 13.773a.75.75 0 0 1-.874.529 49.38 49.38 0 0 0-6.564-.885.75.75 0 0 1 .12-1.495 50.892 50.892 0 0 1 6.79.916.75.75 0 0 1 .528.935Z" /></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Panel de rechazo (pulgar abajo) --}}
                                @php
                                    // Si la IA dijo positivo (Ofertar) y el humano rechaza → motivo negativo
                                    // Si la IA dijo negativo (Descartar) y el humano rechaza → motivo positivo
                                    $iaStatusName = strtolower($item->iaDecision?->status ?? '');
                                    $iaWasPositive = in_array($iaStatusName, ['ofertar', 'focus']);
                                    $reasonsForOverride = $iaWasPositive ? $negativeReasons : $positiveReasons;
                                    $reasonTypeLabel = $iaWasPositive ? 'Motivo negativo (por que no ofertar)' : 'Motivo positivo (por que si ofertar)';
                                @endphp
                                <div x-show="showOverride" x-cloak x-transition
                                     style="margin-top: 12px; padding: 14px; border-radius: 10px; border: 1px solid #fca5a5; background: #fef2f2;"
                                     x-data="{
                                        newDecision: '',
                                        reasonId: '',
                                        comment: '',
                                        submit() {
                                            if (!this.newDecision) return;
                                            let dec = parseInt(this.newDecision);
                                            let reason = this.reasonId ? parseInt(this.reasonId) : null;
                                            let comm = this.comment || null;
                                            $wire.call('override', {{ $item->id }}, dec, reason, comm);
                                            showOverride = false;
                                        }
                                     }"
                                >
                                    <p style="font-size: 12px; font-weight: 600; color: #dc2626; margin-bottom: 10px;">
                                        La IA propuso <strong>{{ $item->iaDecision?->status }}</strong> — elige la decision correcta:
                                    </p>
                                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                                        <div>
                                            <label style="display: block; font-size: 11px; font-weight: 500; color: #4b5563; margin-bottom: 4px;">Decision correcta *</label>
                                            <select x-model="newDecision" style="width: 100%; font-size: 13px; border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px;">
                                                <option value="">Seleccionar...</option>
                                                @foreach($statusOptions as $id => $name)
                                                    <option value="{{ $id }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 11px; font-weight: 500; color: #4b5563; margin-bottom: 4px;">{{ $reasonTypeLabel }}</label>
                                            <select x-model="reasonId" style="width: 100%; font-size: 13px; border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px;">
                                                <option value="">Seleccionar motivo...</option>
                                                @foreach($reasonsForOverride as $id => $reason)
                                                    <option value="{{ $id }}">{{ $reason }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 11px; font-weight: 500; color: #4b5563; margin-bottom: 4px;">Comentario</label>
                                            <input type="text" x-model="comment" style="width: 100%; font-size: 13px; border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px;" placeholder="Opcional...">
                                        </div>
                                        <div style="display: flex; align-items: flex-end; gap: 8px;">
                                            <button
                                                x-on:click="submit()"
                                                :disabled="!newDecision"
                                                style="padding: 7px 16px; font-size: 12px; font-weight: 600; border-radius: 8px; background: #dc2626; color: white; border: none; cursor: pointer;"
                                            >
                                                Corregir
                                            </button>
                                            <button
                                                x-on:click="showOverride = false"
                                                style="padding: 7px 12px; font-size: 12px; font-weight: 600; border-radius: 8px; background: #e5e7eb; color: #4b5563; border: none; cursor: pointer;"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
