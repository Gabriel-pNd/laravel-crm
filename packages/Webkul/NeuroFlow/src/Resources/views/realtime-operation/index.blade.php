<x-admin::layouts>
    <x-slot:title>
        Operacao em Tempo Real
    </x-slot>

    @php
        $sync = $state['sync'];
        $syncStatus = $sync['sync_status'];
        $statusClasses = [
            'fresh' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'stale' => 'border-amber-200 bg-amber-50 text-amber-800',
            'failed' => 'border-red-200 bg-red-50 text-red-800',
            'disabled' => 'border-gray-200 bg-gray-50 text-gray-700',
        ];
        $updatedAt = $state['updated_at'] ? \Illuminate\Support\Carbon::parse($state['updated_at'])->diffForHumans() : null;
    @endphp

    <div class="neuroflow-cockpit space-y-4" style="--neuroflow-min-width: {{ $desktopMinWidth }}px">
        <section class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $clinic->name }}</p>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Operacao em Tempo Real</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded border px-3 py-1.5 font-medium {{ $statusClasses[$syncStatus] ?? $statusClasses['failed'] }}">
                    Sync: {{ $syncStatus }}
                </span>

                <span class="rounded border border-gray-200 bg-white px-3 py-1.5 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    {{ $updatedAt ? 'Atualizado '.$updatedAt : 'Sem dado canonico' }}
                </span>
            </div>
        </section>

        <section class="critical-viewport-warning rounded border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900">
            Viewport abaixo do minimo operacional. Acoes criticas ficam bloqueadas; use desktop para leitura e triagem completa.
        </section>

        @if (in_array($syncStatus, ['failed', 'disabled', 'stale'], true))
            <section class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Estado canonico indisponivel ou degradado</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            A tela nao confirma sucesso sem estado persistido. Codigo: {{ $sync['last_error_code'] ?? 'SYNC_STALE' }}.
                        </p>
                    </div>

                    <button disabled class="rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-400">
                        Acao critica bloqueada
                    </button>
                </div>
            </section>
        @endif

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Funil de Leads</h2>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @forelse ($state['pipeline'] as $lead)
                        <div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $lead['classification'] ?? 'unknown' }}</p>
                            <p class="text-gray-600 dark:text-gray-300">{{ $lead['qualification_status'] ?? 'not_started' }} / {{ $lead['conversation_status'] ?? 'open' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sem projection de pipeline disponivel.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Agenda de Ocupacao</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($state['agenda'] as $appointment)
                        <div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $appointment['appointment_status'] ?? 'failed_exception' }}</p>
                            <p class="text-gray-600 dark:text-gray-300">{{ $appointment['starts_at'] ?? 'sem horario' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sem agenda canonica disponivel.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Timeline Operacional</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($state['timeline'] as $event)
                        <div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $event['event_type'] ?? 'event' }}</p>
                            <p class="text-gray-600 dark:text-gray-300">{{ $event['summary'] ?? 'Resumo indisponivel' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sem timeline canonica disponivel.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Cerebro SSOT e Excecoes</h2>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                        <p class="font-medium text-gray-900 dark:text-white">Evidencias</p>
                        <p class="text-gray-600 dark:text-gray-300">{{ count($state['evidence']) }} itens UI-safe</p>
                    </div>
                    <div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                        <p class="font-medium text-gray-900 dark:text-white">Excecoes Humanas</p>
                        <p class="text-gray-600 dark:text-gray-300">{{ count($state['exceptions']) }} abertas/visiveis</p>
                    </div>
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
