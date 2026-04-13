<x-filament-panels::page>
    @php
        $groups = $this->getGroups();
        $statusOptions = $this->getStatusOptions();
        $negativeReasons = $this->getNegativeReasons();
        $positiveReasons = $this->getPositiveReasons();
        $allReasons = $negativeReasons + $positiveReasons;
    @endphp

    @if(empty($groups))
        <div class="flex flex-col items-center justify-center py-12">
            <x-heroicon-o-check-circle class="w-16 h-16 text-green-400 mb-4" />
            <h2 class="text-2xl font-bold text-gray-500 mb-2">Todo cribado</h2>
            <p class="text-gray-400">No hay items pendientes con decision IA por revisar.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($groups as $groupKey => $group)
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
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
                            <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50" x-data="{ showOverride: false }">
                                <div class="flex items-start gap-4">
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
                                    <div class="flex items-center gap-3 shrink-0">
                                        {{-- Pulgar arriba: acepto la IA --}}
                                        <button
                                            wire:click="confirm({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-green-100 text-green-600 hover:bg-green-200 hover:text-green-700 transition"
                                            title="Acepto la decision de la IA"
                                        >
                                            <x-heroicon-s-hand-thumb-up class="w-6 h-6" />
                                        </button>

                                        {{-- Pulgar abajo: rechazo la IA --}}
                                        <button
                                            x-on:click="showOverride = !showOverride"
                                            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-red-100 text-red-600 hover:bg-red-200 hover:text-red-700 transition"
                                            title="Rechazo la decision de la IA"
                                        >
                                            <x-heroicon-s-hand-thumb-down class="w-6 h-6" />
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
                                     class="mt-3 p-3 rounded-lg border border-red-200 dark:border-red-800"
                                     style="background: #fef2f215;"
                                     x-data="{
                                        newDecision: '',
                                        reasonId: '',
                                        comment: '',
                                        submit() {
                                            if (!this.newDecision) return;
                                            $wire.override({{ $item->id }}, this.newDecision, this.reasonId || null, this.comment || null);
                                            showOverride = false;
                                        }
                                     }"
                                >
                                    <p class="text-xs font-semibold text-red-600 mb-2">
                                        La IA propuso <strong>{{ $item->iaDecision?->status }}</strong> — elige la decision correcta:
                                    </p>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Decision correcta *</label>
                                            <select x-model="newDecision" class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                                                <option value="">Seleccionar...</option>
                                                @foreach($statusOptions as $id => $name)
                                                    <option value="{{ $id }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $reasonTypeLabel }}</label>
                                            <select x-model="reasonId" class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                                                <option value="">Seleccionar motivo...</option>
                                                @foreach($reasonsForOverride as $id => $reason)
                                                    <option value="{{ $id }}">{{ $reason }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Comentario</label>
                                            <input type="text" x-model="comment" class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" placeholder="Opcional...">
                                        </div>
                                        <div class="flex items-end gap-2">
                                            <button
                                                x-on:click="submit()"
                                                :disabled="!newDecision"
                                                class="px-4 py-2 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 transition"
                                            >
                                                Corregir
                                            </button>
                                            <button
                                                x-on:click="showOverride = false"
                                                class="px-3 py-2 text-xs font-medium rounded-lg bg-gray-200 text-gray-600 hover:bg-gray-300 transition"
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
