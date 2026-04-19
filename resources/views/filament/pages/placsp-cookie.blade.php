<x-filament-panels::page>
    {{-- Estado actual --}}
    <div style="background: {{ $cookie_exists ? '#ecfdf5' : '#fef2f2' }};
                border-left: 4px solid {{ $cookie_exists ? '#10b981' : '#ef4444' }};
                padding: 14px 16px; border-radius: 8px; margin-bottom: 16px;">
        <div style="font-size: 14px; font-weight: 700; color: {{ $cookie_exists ? '#047857' : '#991b1b' }};">
            @if($cookie_exists)
                ✓ Cookie guardada
            @else
                ✗ No hay cookie guardada
            @endif
        </div>
        <div style="font-size: 12px; color: #475569; margin-top: 4px;">
            @if($cookie_exists)
                {{ number_format($cookie_length, 0, ',', '.') }} bytes ·
                Guardada hace {{ \Carbon\Carbon::createFromTimestamp($cookie_mtime)->diffForHumans() }}
                <br>
            @endif
            <code style="font-size: 11px; color: #64748b;">{{ $cookie_path }}</code>
        </div>
    </div>

    {{-- Form --}}
    <form wire:submit.prevent="save" style="margin-bottom: 20px;">
        {{ $this->form }}

        <div style="display: flex; gap: 10px; margin-top: 14px;">
            <button type="submit" wire:loading.attr="disabled"
                style="padding: 9px 20px; font-size: 13px; font-weight: 700; border-radius: 8px;
                       background: #16a34a; color: white; border: none; cursor: pointer;">
                Guardar cookie
            </button>

            <button type="button" wire:click="test" wire:loading.attr="disabled"
                style="padding: 9px 20px; font-size: 13px; font-weight: 700; border-radius: 8px;
                       background: #3b82f6; color: white; border: none; cursor: pointer;"
                title="Prueba sync sobre la oferta PLACSP más reciente con tarea Kanboard">
                Probar cookie actual
            </button>

            <span wire:loading style="padding: 9px 0; font-size: 12px; color: #64748b;">
                procesando...
            </span>
        </div>
    </form>

    {{-- Instrucciones --}}
    <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 16px; border-radius: 8px;">
        <div style="font-size: 14px; font-weight: 700; color: #92400e; margin-bottom: 8px;">
            Cómo extraer la cookie desde Chrome
        </div>
        <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: #78350f; line-height: 1.7;">
            <li>Abre <strong>Chrome</strong> y entra en
                <a href="https://contrataciondelestado.es/wps/portal/plataforma/empresas"
                   target="_blank" style="color: #1d4ed8;">
                    contrataciondelestado.es/wps/portal/plataforma/empresas ↗
                </a>.
            </li>
            <li>Logueate con tu usuario y contraseña PLACSP. Verás arriba "Operador Económico" y tu nombre.</li>
            <li>Abre DevTools (<strong>F12</strong>) → pestaña <strong>Network</strong> (Red).</li>
            <li>Pulsa <strong>F5</strong> para recargar (se llenará la lista).</li>
            <li>Click en la <strong>primera fila</strong> (suele ser tipo "document").</li>
            <li>Panel derecho → pestaña <strong>Headers</strong> (Cabeceras) → baja hasta
                <strong>"Request Headers"</strong>.</li>
            <li>Busca la línea <code>Cookie:</code>. Click derecho sobre su valor →
                <strong>"Copy value"</strong>.</li>
            <li>Pégalo en el textarea de arriba y pulsa <strong>Guardar cookie</strong>.</li>
            <li>Pulsa <strong>Probar cookie actual</strong> para verificar que funciona.</li>
        </ol>
        <div style="margin-top: 10px; font-size: 12px; color: #78350f;">
            Duración típica: <strong>2-4 horas de actividad, 30 min de inactividad</strong>.
            Cuando te digan que la cookie expiró (o te des cuenta porque los pliegos dejan de adjuntarse
            solos), vuelve aquí y pega una nueva. Son 10 segundos.
        </div>
    </div>
</x-filament-panels::page>
