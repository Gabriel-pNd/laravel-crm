<x-admin::layouts>
    <x-slot:title>
        Operacao em Tempo Real
    </x-slot>

    @php
        $sync = $cockpit['sync'];
        $syncStatus = $sync['status'];
        $statusClasses = [
            'fresh' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'stale' => 'border-amber-200 bg-amber-50 text-amber-800',
            'failed' => 'border-red-200 bg-red-50 text-red-800',
            'disabled' => 'border-gray-200 bg-gray-50 text-gray-700',
            'loading' => 'border-blue-200 bg-blue-50 text-blue-800',
            'partial' => 'border-sky-200 bg-sky-50 text-sky-800',
            'empty' => 'border-gray-200 bg-gray-50 text-gray-700',
            'ok' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
            'breached' => 'border-red-200 bg-red-50 text-red-800',
        ];
    @endphp

    <div class="neuroflow-cockpit space-y-4" style="--neuroflow-min-width: {{ $desktopMinWidth }}px">
        <section class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between" aria-label="tenant channel header">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $cockpit['tenant']['name'] }} · {{ $cockpit['tenant']['timezone'] }}</p>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Operacao em Tempo Real</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $cockpit['tenant']['channel_label'] }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm" aria-label="SyncStatusIndicator">
                <span class="rounded border px-3 py-1.5 font-medium {{ $statusClasses[$syncStatus] ?? $statusClasses['failed'] }}">
                    {{ $sync['label'] }} · {{ $syncStatus }}
                </span>

                <span class="rounded border border-gray-200 bg-white px-3 py-1.5 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    {{ $sync['updated_label'] }} · {{ $sync['polling_state'] }}
                </span>
            </div>
        </section>

        <section class="critical-viewport-warning rounded border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900">
            Viewport abaixo do minimo operacional. Acoes criticas ficam bloqueadas; use desktop para leitura e triagem completa.
        </section>

        @if ($cockpit['is_degraded'])
            <section class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-live="polite">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Estado canonico degradado</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $cockpit['degraded_message'] }} Codigo: {{ $sync['last_error_code'] ?? 'SYNC_STALE' }}.
                        </p>
                    </div>

                    <button disabled class="rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-400">
                        Acao critica bloqueada
                    </button>
                </div>
            </section>
        @endif

        <section class="grid gap-4 xl:grid-cols-3">
            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-label="SlaCard">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">SLA de primeira resposta</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $cockpit['sla_card']['detail'] }}</p>
                    </div>

                    <span class="rounded border px-3 py-1.5 text-sm font-medium {{ $statusClasses[$cockpit['sla_card']['status']] ?? $statusClasses['empty'] }}">
                        {{ $cockpit['sla_card']['label'] }}
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Decorrido</dt>
                        <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $cockpit['sla_card']['elapsed'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Limite</dt>
                        <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $cockpit['sla_card']['target'] }}</dd>
                    </div>
                </dl>
            </article>

            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-label="LeadSummaryCard">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Lead em foco</h2>
                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $cockpit['lead_summary']['title'] }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $cockpit['lead_summary']['subtitle'] }}</p>

                <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                    <span class="rounded border border-gray-200 px-2 py-1 text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        Estado: {{ $cockpit['lead_summary']['stage'] ?? 'vazio' }}
                    </span>
                    <span class="rounded border border-gray-200 px-2 py-1 text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        Trace: {{ $cockpit['lead_summary']['correlation_id'] ?? 'sem trace' }}
                    </span>
                </div>

                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $cockpit['lead_summary']['next_action'] }}</p>
            </article>

            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-label="automation human actor badge">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Ator operacional</h2>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="rounded border border-emerald-200 bg-emerald-50 px-3 py-1.5 font-medium text-emerald-800">IA / automacao</span>
                    <span class="rounded border border-blue-200 bg-blue-50 px-3 py-1.5 font-medium text-blue-800">Humano por excecao</span>
                    <span class="rounded border border-gray-200 bg-gray-50 px-3 py-1.5 font-medium text-gray-700">Sistema</span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Badges sempre mostram texto alem de cor.</p>
            </article>
        </section>

        <section class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Funil de Leads</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">Estados canonicos persistidos</span>
            </div>

            <div class="mt-4 grid gap-3 xl:grid-cols-3 2xl:grid-cols-5">
                @foreach ($cockpit['pipeline_columns'] as $column)
                    <article class="min-h-32 rounded border border-gray-200 p-3 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $column['label'] }}</h3>
                            <span class="rounded border border-gray-200 px-2 py-0.5 text-xs text-gray-600 dark:border-gray-800 dark:text-gray-300">{{ count($column['items']) }}</span>
                        </div>

                        <div class="mt-3 space-y-2">
                            @forelse ($column['items'] as $lead)
                                <div class="rounded border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $lead['title'] }}</p>
                                    <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $lead['classification'] }} · {{ $lead['qualification'] }}</p>
                                    <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $lead['conversation'] }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs font-medium">
                                        <span class="rounded border px-2 py-1 {{ $statusClasses[$lead['sla']['status']] ?? $statusClasses['empty'] }}">{{ $lead['sla']['label'] }}</span>
                                        <span class="rounded border border-gray-200 px-2 py-1 text-gray-700 dark:border-gray-800 dark:text-gray-200">Trace {{ $lead['correlation_id'] }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded border border-dashed border-gray-200 p-3 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">Nenhum lead neste estado.</p>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(360px,0.6fr)]">
            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-label="OperationalTimeline">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Timeline Operacional</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($cockpit['timeline']) }} eventos</span>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse ($cockpit['timeline'] as $event)
                        <article class="rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $event['title'] }}</h3>
                                <span class="rounded border border-gray-200 px-2 py-1 text-xs font-medium text-gray-700 dark:border-gray-800 dark:text-gray-200">{{ $event['status'] }}</span>
                            </div>
                            <p class="mt-2 text-gray-600 dark:text-gray-300">{{ $event['summary'] }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                                <span class="rounded border border-gray-200 px-2 py-1 dark:border-gray-800">{{ $event['actor'] }}</span>
                                <span class="rounded border border-gray-200 px-2 py-1 dark:border-gray-800">{{ $event['source'] }}</span>
                                <span class="rounded border border-gray-200 px-2 py-1 dark:border-gray-800">{{ $event['occurred_at'] }}</span>
                                <span class="rounded border border-gray-200 px-2 py-1 dark:border-gray-800">Trace {{ $event['correlation_id'] }}</span>
                                @if ($event['external_ref'] !== '')
                                    <span class="rounded border border-gray-200 px-2 py-1 dark:border-gray-800">Ref {{ $event['external_ref'] }}</span>
                                @endif
                                @if ($event['evidence_marker'])
                                    <span class="rounded border border-sky-200 bg-sky-50 px-2 py-1 text-sky-800">Evidencia vinculada</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="rounded border border-dashed border-gray-200 p-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            Nenhum evento operacional fresco. Estados loading, stale, sync parcial e erro aparecem no indicador de sync sem confirmar sucesso.
                        </p>
                    @endforelse
                </div>
            </article>

            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-label="WhatsApp conversation mirror">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Espelho WhatsApp</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($cockpit['whatsapp_mirror']['messages'] as $message)
                        <div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $message['actor'] }}</p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $message['occurred_at'] }}</span>
                            </div>
                            <p class="mt-2 text-gray-600 dark:text-gray-300">{{ $message['summary'] }}</p>
                            <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">Trace {{ $message['correlation_id'] }}</p>
                        </div>
                    @empty
                        <p class="rounded border border-dashed border-gray-200 p-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            Nenhuma mensagem UI-safe na timeline. O espelho nao mostra corpo integral nem payload bruto.
                        </p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>

    @push('styles')
        <style>
            .critical-viewport-warning {
                display: none;
            }

            @media (max-width: {{ $desktopMinWidth - 1 }}px) {
                .neuroflow-cockpit {
                    min-width: 0;
                }

                .critical-viewport-warning {
                    display: block;
                }
            }
        </style>
    @endpush
</x-admin::layouts>
