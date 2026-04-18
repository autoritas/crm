<x-filament-panels::page>
    @php
        $groups           = $this->getGroups();
        $statusOptions    = $this->getStatusOptions();
        $iaDirectionMap   = $this->getIaDirectionMap();
        $negativeReasons  = $this->getNegativeReasons();
        $positiveReasons  = $this->getPositiveReasons();
    @endphp

    @if(empty($groups))
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px;">
            <svg xmlns="http://www.w3.org/2000/svg" style="width: 64px; height: 64px; color: #86efac; margin-bottom: 16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <h2 style="font-size: 20px; font-weight: 700; color: #6b7280;">Todo cribado</h2>
            <p style="color: #9ca3af;">No hay items pendientes con decision IA por revisar.</p>
        </div>
    @else
        {{-- Leyenda explicativa --}}
        <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 10px; padding: 8px 14px;
                    background: #f1f5f9; border-radius: 10px; font-size: 12px; color: #475569;">
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="display: inline-flex; width: 22px; height: 22px; border-radius: 50%;
                             background: #dcfce7; color: #16a34a; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 14px; height: 14px;"><path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777Z"/></svg>
                </span>
                <strong>Pulgar arriba</strong> = enviar a <strong>Ofertar</strong>
            </span>
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="display: inline-flex; width: 22px; height: 22px; border-radius: 50%;
                             background: #fee2e2; color: #dc2626; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 14px; height: 14px;"><path d="M15.73 5.5h1.035A7.465 7.465 0 0 1 18 9.625a7.465 7.465 0 0 1-1.235 4.125h-.148c-.806 0-1.534.446-2.031 1.08a9.04 9.04 0 0 1-2.861 2.4c-.723.384-1.35.956-1.653 1.715a4.498 4.498 0 0 0-.322 1.672V21a.75.75 0 0 1-.75.75 2.25 2.25 0 0 1-2.25-2.25c0-1.152.26-2.243.723-3.218.266-.558-.107-1.282-.725-1.282H3.622c-1.026 0-1.945-.694-2.054-1.715A12.134 12.134 0 0 1 1.5 12c0-2.848.992-5.464 2.649-7.521C4.537 3.997 5.136 3.75 5.754 3.75H9.77a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23Z"/></svg>
                </span>
                <strong>Pulgar abajo</strong> = <strong>Descartar</strong>
            </span>
            <span style="color: #94a3b8;">·</span>
            <span style="color: #64748b;">
                Si cambias la decision de la IA (o estaba en Revision) se pide motivo y se aprende del feedback.
            </span>
        </div>

        {{-- Barra de acciones masivas --}}
        <div
            x-data='{
                bulkModal: false,
                bulkDirection: null,
                bulkCount: 0,
                bulkReasonId: "",
                bulkComment: "",
                positiveReasons: @json($positiveReasons),
                negativeReasons: @json($negativeReasons),
                get reasons() {
                    return this.bulkDirection === "ofertar" ? this.positiveReasons : this.negativeReasons;
                },
                get reasonLabel() {
                    return this.bulkDirection === "ofertar"
                        ? "Motivo positivo — por que si ofertar"
                        : "Motivo negativo — por que descartar";
                },
                openBulk(direction, count) {
                    this.bulkDirection = direction;
                    this.bulkCount = count;
                    this.bulkReasonId = "";
                    this.bulkComment = "";
                    this.bulkModal = true;
                },
                submitBulk() {
                    if (!this.bulkReasonId) return;
                    $wire.call("bulkDecide", this.bulkDirection,
                        parseInt(this.bulkReasonId), this.bulkComment || null);
                    this.bulkModal = false;
                }
            }'
            x-on:bulk-needs-reason.window="openBulk($event.detail.direction, $event.detail.count)"
            style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding: 10px 16px;
                   background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;"
        >
            <button wire:click="confirmAll" wire:loading.attr="disabled"
                style="padding: 7px 14px; font-size: 12px; font-weight: 600; border-radius: 8px;
                       background: #3b82f6; color: white; border: none; cursor: pointer;"
                onclick="return confirm('¿Aceptar TODOS los items con la decision que ha propuesto la IA?')">
                ✓ Aceptar todos como IA
            </button>

            <span style="color: #cbd5e1;">|</span>

            <span style="font-size: 12px; color: #64748b; font-weight: 600;">
                Seleccionados: <span style="color: #3b82f6;">{{ count($this->selectedItems) }}</span>
            </span>

            <button wire:click="bulkDecide('ofertar')" wire:loading.attr="disabled"
                @disabled(count($this->selectedItems) === 0)
                style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px;
                       font-size: 12px; font-weight: 700; border-radius: 8px;
                       background: {{ count($this->selectedItems) > 0 ? '#16a34a' : '#e2e8f0' }};
                       color: {{ count($this->selectedItems) > 0 ? 'white' : '#94a3b8' }};
                       border: none; cursor: {{ count($this->selectedItems) > 0 ? 'pointer' : 'not-allowed' }};"
                title="Enviar seleccion a Ofertar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 14px; height: 14px;"><path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777Z"/></svg>
                Ofertar seleccion
            </button>

            <button wire:click="bulkDecide('descartar')" wire:loading.attr="disabled"
                @disabled(count($this->selectedItems) === 0)
                style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px;
                       font-size: 12px; font-weight: 700; border-radius: 8px;
                       background: {{ count($this->selectedItems) > 0 ? '#dc2626' : '#e2e8f0' }};
                       color: {{ count($this->selectedItems) > 0 ? 'white' : '#94a3b8' }};
                       border: none; cursor: {{ count($this->selectedItems) > 0 ? 'pointer' : 'not-allowed' }};"
                title="Descartar seleccion">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 14px; height: 14px;"><path d="M15.73 5.5h1.035A7.465 7.465 0 0 1 18 9.625a7.465 7.465 0 0 1-1.235 4.125h-.148c-.806 0-1.534.446-2.031 1.08a9.04 9.04 0 0 1-2.861 2.4c-.723.384-1.35.956-1.653 1.715a4.498 4.498 0 0 0-.322 1.672V21a.75.75 0 0 1-.75.75 2.25 2.25 0 0 1-2.25-2.25c0-1.152.26-2.243.723-3.218.266-.558-.107-1.282-.725-1.282H3.622c-1.026 0-1.945-.694-2.054-1.715A12.134 12.134 0 0 1 1.5 12c0-2.848.992-5.464 2.649-7.521C4.537 3.997 5.136 3.75 5.754 3.75H9.77a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23Z"/></svg>
                Descartar seleccion
            </button>

            {{-- Modal bulk: motivo unico --}}
            <div x-show="bulkModal" x-cloak
                 style="position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 50;
                        display: flex; align-items: center; justify-content: center;"
                 x-on:click.self="bulkModal = false">
                <div style="background: white; border-radius: 12px; padding: 20px; width: 480px; max-width: 92vw;"
                     x-on:click.stop>
                    <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 6px 0;">
                        Motivo para <span x-text="bulkDirection === 'ofertar' ? 'Ofertar' : 'Descartar'"></span>
                        <span x-text="bulkCount" style="color: #64748b;"></span>
                        <span style="color: #64748b;">items</span>
                    </h3>
                    <p style="font-size: 12px; color: #64748b; margin: 0 0 12px 0;">
                        Se guardara como feedback en el prompt de la IA
                        (<span x-text="bulkDirection === 'ofertar' ? 'positivos' : 'negativos'"></span>).
                    </p>
                    <label style="display: block; font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px;"
                           x-text="reasonLabel"></label>
                    <select x-model="bulkReasonId"
                            style="width: 100%; font-size: 13px; border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px; margin-bottom: 10px;">
                        <option value="">Seleccionar motivo...</option>
                        <template x-for="[id, text] in Object.entries(reasons)" :key="id">
                            <option :value="id" x-text="text"></option>
                        </template>
                    </select>

                    <label style="display: block; font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px;">Comentario (opcional)</label>
                    <input type="text" x-model="bulkComment"
                           style="width: 100%; font-size: 13px; border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px;"
                           placeholder="Detalle para el prompt...">

                    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;">
                        <button x-on:click="bulkModal = false"
                                style="padding: 7px 14px; font-size: 12px; font-weight: 600; border-radius: 8px; background: #e5e7eb; color: #4b5563; border: none; cursor: pointer;">
                            Cancelar
                        </button>
                        <button x-on:click="submitBulk()" :disabled="!bulkReasonId"
                                :style="bulkReasonId
                                    ? 'padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 8px; background: ' + (bulkDirection === 'ofertar' ? '#16a34a' : '#dc2626') + '; color: white; border: none; cursor: pointer;'
                                    : 'padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 8px; background: #e5e7eb; color: #94a3b8; border: none; cursor: not-allowed;'">
                            Aplicar
                        </button>
                    </div>
                </div>
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
                            @php
                                $iaDirection = $iaDirectionMap[$item->id_ia_decision] ?? 'descartar';
                                // up (ofertar) requiere motivo si IA != ofertar o si IA = revision
                                $upNeedsReason   = $iaDirection !== 'ofertar';   // revision tambien
                                $downNeedsReason = $iaDirection !== 'descartar'; // revision tambien
                            @endphp
                            <div style="padding: 10px 16px;"
                                 x-data='{
                                    reasonModal: false,
                                    reasonDirection: null,
                                    reasonId: "",
                                    comment: "",
                                    positiveReasons: @json($positiveReasons),
                                    negativeReasons: @json($negativeReasons),
                                    get reasons() {
                                        return this.reasonDirection === "ofertar" ? this.positiveReasons : this.negativeReasons;
                                    },
                                    get label() {
                                        return this.reasonDirection === "ofertar"
                                            ? "Motivo positivo — por que si ofertar"
                                            : "Motivo negativo — por que descartar";
                                    },
                                    open(direction) {
                                        this.reasonDirection = direction;
                                        this.reasonId = "";
                                        this.comment = "";
                                        this.reasonModal = true;
                                    },
                                    submit() {
                                        if (!this.reasonId) return;
                                        $wire.call("decide", {{ $item->id }}, this.reasonDirection,
                                            parseInt(this.reasonId), this.comment || null);
                                        this.reasonModal = false;
                                    }
                                 }'>
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
                                            @if($iaDirection === 'revision')
                                                <span style="font-size: 10px; font-weight: 700; color: #b45309; background: #fef3c7; padding: 2px 8px; border-radius: 8px;">
                                                    IA en revision — pide motivo siempre
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-1"
                                           style="white-space: pre-line; line-height: 1.5;">{{ $item->resumen_objeto }}</p>
                                        @if($item->ia_motivo)
                                            <p class="text-xs text-gray-500 italic mb-1"
                                               style="white-space: pre-line; line-height: 1.5;">
                                                <span class="font-medium not-italic">IA:</span> {{ $item->ia_motivo }}
                                            </p>
                                        @endif
                                        @if($item->url)
                                            <a href="{{ $item->url }}" target="_blank" class="text-xs text-primary-600 hover:underline">
                                                Ver licitacion →
                                            </a>
                                        @endif
                                    </div>

                                    {{-- Acciones: 👍 ofertar / 👎 descartar --}}
                                    <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                                        {{-- Pulgar arriba = Ofertar --}}
                                        <button
                                            @if($upNeedsReason)
                                                x-on:click="open('ofertar')"
                                            @else
                                                wire:click="decide({{ $item->id }}, 'ofertar')"
                                                wire:loading.attr="disabled"
                                            @endif
                                            style="display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: #dcfce7; color: #16a34a; border: 2px solid #86efac; cursor: pointer;"
                                            title="{{ $upNeedsReason ? 'Enviar a Ofertar (pide motivo)' : 'Enviar a Ofertar (coincide con IA)' }}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 24px; height: 24px;"><path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777ZM2.331 10.727a.75.75 0 0 1 .874-.529 49.343 49.343 0 0 0 6.564.885.75.75 0 0 1-.12 1.495 50.846 50.846 0 0 1-6.79-.916.75.75 0 0 1-.528-.935Z"/></svg>
                                        </button>

                                        {{-- Pulgar abajo = Descartar --}}
                                        <button
                                            @if($downNeedsReason)
                                                x-on:click="open('descartar')"
                                            @else
                                                wire:click="decide({{ $item->id }}, 'descartar')"
                                                wire:loading.attr="disabled"
                                            @endif
                                            style="display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: #fee2e2; color: #dc2626; border: 2px solid #fca5a5; cursor: pointer;"
                                            title="{{ $downNeedsReason ? 'Descartar (pide motivo)' : 'Descartar (coincide con IA)' }}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 24px; height: 24px;"><path d="M15.73 5.5h1.035A7.465 7.465 0 0 1 18 9.625a7.465 7.465 0 0 1-1.235 4.125h-.148c-.806 0-1.534.446-2.031 1.08a9.04 9.04 0 0 1-2.861 2.4c-.723.384-1.35.956-1.653 1.715a4.498 4.498 0 0 0-.322 1.672V21a.75.75 0 0 1-.75.75 2.25 2.25 0 0 1-2.25-2.25c0-1.152.26-2.243.723-3.218.266-.558-.107-1.282-.725-1.282H3.622c-1.026 0-1.945-.694-2.054-1.715A12.134 12.134 0 0 1 1.5 12c0-2.848.992-5.464 2.649-7.521C4.537 3.997 5.136 3.75 5.754 3.75H9.77a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23ZM21.669 13.773a.75.75 0 0 1-.874.529 49.38 49.38 0 0 0-6.564-.885.75.75 0 0 1 .12-1.495 50.892 50.892 0 0 1 6.79.916.75.75 0 0 1 .528.935Z"/></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Modal de motivo individual --}}
                                <div x-show="reasonModal" x-cloak
                                     style="position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 50;
                                            display: flex; align-items: center; justify-content: center;"
                                     x-on:click.self="reasonModal = false">
                                    <div style="background: white; border-radius: 12px; padding: 20px; width: 480px; max-width: 92vw;"
                                         x-on:click.stop>
                                        <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 6px 0;">
                                            <span x-text="reasonDirection === 'ofertar' ? 'Enviar a Ofertar' : 'Descartar'"></span>
                                        </h3>
                                        <p style="font-size: 12px; color: #64748b; margin: 0 0 12px 0;">
                                            La IA habia propuesto <strong>{{ $item->iaDecision?->status }}</strong>.
                                            Este motivo se guarda como feedback en el prompt de la IA.
                                        </p>
                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px;" x-text="label"></label>
                                        <select x-model="reasonId"
                                                style="width: 100%; font-size: 13px; border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px; margin-bottom: 10px;">
                                            <option value="">Seleccionar motivo...</option>
                                            <template x-for="[id, text] in Object.entries(reasons)" :key="id">
                                                <option :value="id" x-text="text"></option>
                                            </template>
                                        </select>

                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px;">Comentario (opcional)</label>
                                        <input type="text" x-model="comment"
                                               style="width: 100%; font-size: 13px; border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px;"
                                               placeholder="Detalle para el prompt...">

                                        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;">
                                            <button x-on:click="reasonModal = false"
                                                    style="padding: 7px 14px; font-size: 12px; font-weight: 600; border-radius: 8px; background: #e5e7eb; color: #4b5563; border: none; cursor: pointer;">
                                                Cancelar
                                            </button>
                                            <button x-on:click="submit()" :disabled="!reasonId"
                                                    :style="reasonId
                                                        ? 'padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 8px; background: ' + (reasonDirection === 'ofertar' ? '#16a34a' : '#dc2626') + '; color: white; border: none; cursor: pointer;'
                                                        : 'padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 8px; background: #e5e7eb; color: #94a3b8; border: none; cursor: not-allowed;'">
                                                Aplicar
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
