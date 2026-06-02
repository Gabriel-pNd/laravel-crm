<?php

use Webkul\NeuroFlow\Application\Presenters\RealtimeOperationPresenter;

function freshRealtimeState(array $overrides = []): array
{
    return array_replace_recursive([
        'clinic' => [
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Clinica Demo',
            'timezone' => 'America/Sao_Paulo',
        ],
        'sync' => [
            'sync_status' => 'fresh',
            'last_success_at' => '2026-06-02T12:00:00Z',
            'lag_seconds' => 0,
        ],
        'pipeline' => [],
        'timeline' => [],
        'agenda' => [],
        'evidence' => [],
        'exceptions' => [],
        'updated_at' => '2026-06-02T12:00:00Z',
    ], $overrides);
}

it('maps canonical pipeline states from normalized projection rows', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'pipeline' => [
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'lead_id' => 'lead-new',
                'classification' => 'unknown',
                'qualification_status' => 'not_started',
                'conversation_status' => 'open',
                'sla_status' => 'within_target',
                'sla_elapsed_seconds' => 18,
                'last_correlation_id' => 'corr-new-123456789',
            ],
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'lead_id' => 'lead-classified',
                'classification' => 'new_lead',
                'qualification_status' => 'not_started',
                'conversation_status' => 'open',
            ],
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'lead_id' => 'lead-qualifying',
                'classification' => 'existing_lead',
                'qualification_status' => 'in_progress',
                'conversation_status' => 'waiting_contact',
            ],
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'lead_id' => 'lead-human',
                'qualification_status' => 'needs_human',
                'conversation_status' => 'open',
            ],
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'lead_id' => 'lead-booked',
                'qualification_status' => 'complete',
                'conversation_status' => 'booked',
            ],
        ],
    ]));

    $columns = collect($presented['pipeline_columns'])->keyBy('key');

    expect($columns['novo']['items'])->toHaveCount(1);
    expect($columns['classificado']['items'])->toHaveCount(1);
    expect($columns['qualificando']['items'])->toHaveCount(1);
    expect($columns['humano_necessario']['items'])->toHaveCount(1);
    expect($columns['agendado']['items'])->toHaveCount(1);
    expect($columns['novo']['items'][0]['sla']['status'])->toBe('ok');
    expect($columns['novo']['items'][0]['correlation_id'])->toBe('corr-new...6789');
});

it('maps SLA status to textual UI badges', function (string $persisted, string $expected, string $label) {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'pipeline' => [[
            'clinic_id' => '11111111-1111-4111-8111-111111111111',
            'lead_id' => 'lead-1',
            'sla_status' => $persisted,
            'sla_elapsed_seconds' => 119,
            'sla_target_seconds' => 120,
        ]],
    ]));

    expect($presented['sla_card']['status'])->toBe($expected);
    expect($presented['sla_card']['label'])->toBe($label);
    expect($presented['sla_card']['elapsed'])->toBe('119s');
    expect($presented['sla_card']['target'])->toBe('120s');
})->with([
    ['within_target', 'ok', 'SLA ok'],
    ['at_risk', 'warning', 'SLA em risco'],
    ['breached', 'breached', 'SLA violado'],
]);

it('hides operational rows when sync is degraded', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'sync' => [
            'sync_status' => 'stale',
            'last_success_at' => '2026-06-02T12:00:00Z',
            'lag_seconds' => 999,
        ],
        'pipeline' => [[
            'clinic_id' => '11111111-1111-4111-8111-111111111111',
            'lead_id' => 'lead-should-not-render',
            'conversation_status' => 'booked',
        ]],
        'timeline' => [[
            'clinic_id' => '11111111-1111-4111-8111-111111111111',
            'event_type' => 'appointment_booked',
            'summary' => 'Agendamento confirmado',
        ]],
    ]));

    expect($presented['is_degraded'])->toBeTrue();
    expect(collect($presented['pipeline_columns'])->sum(fn ($column) => count($column['items'])))->toBe(0);
    expect($presented['timeline'])->toBe([]);
    expect($presented['lead_summary']['empty'])->toBeTrue();
});

it('builds timeline and WhatsApp mirror from UI-safe timeline events', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'timeline' => [
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'event_type' => 'message_received',
                'summary' => 'Mensagem recebida com resumo seguro',
                'occurred_at' => '2026-06-02T12:01:00Z',
                'actor_type' => 'contact',
                'source_system' => 'whatsapp',
                'correlation_id' => 'corr-message-123456789',
            ],
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'event_type' => 'ssot_answer_retrieved',
                'summary' => 'Fonte aprovada consultada',
                'occurred_at' => '2026-06-02T12:02:00Z',
                'actor_type' => 'automation',
                'source_system' => 'ssot',
                'correlation_id' => 'corr-evidence-123456789',
            ],
        ],
    ]));

    expect($presented['timeline'])->toHaveCount(2);
    expect($presented['timeline'][0]['title'])->toBe('Mensagem recebida');
    expect($presented['timeline'][0]['actor'])->toBe('Responsavel');
    expect($presented['timeline'][1]['evidence_marker'])->toBeTrue();
    expect($presented['whatsapp_mirror']['messages'])->toHaveCount(1);
    expect($presented['whatsapp_mirror']['messages'][0]['summary'])->toBe('Mensagem recebida com resumo seguro');
});

it('keeps the cockpit view markers for required story components', function () {
    $view = file_get_contents(base_path('packages/Webkul/NeuroFlow/src/Resources/views/realtime-operation/index.blade.php'));

    expect($view)->toContain('SyncStatusIndicator');
    expect($view)->toContain('SlaCard');
    expect($view)->toContain('LeadSummaryCard');
    expect($view)->toContain('tenant channel header');
    expect($view)->toContain('automation human actor badge');
    expect($view)->toContain('WhatsApp conversation mirror');
});
