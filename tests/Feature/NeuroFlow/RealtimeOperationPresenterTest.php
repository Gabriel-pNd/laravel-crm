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

it('uses operational priority instead of arbitrary first row for lead focus and SLA', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'pipeline' => [
            [
                'lead_id' => 'lead-ok',
                'classification' => 'new_lead',
                'sla_status' => 'within_target',
                'sla_elapsed_seconds' => 20,
            ],
            [
                'lead_id' => 'lead-risk',
                'qualification_status' => 'in_progress',
                'sla_status' => 'breached',
                'sla_elapsed_seconds' => 190,
                'sla_target_seconds' => 120,
                'next_action' => 'Responder responsavel',
            ],
        ],
    ]));

    expect($presented['lead_summary']['title'])->toBe('Lead lead-risk');
    expect($presented['sla_card']['status'])->toBe('breached');
    expect($presented['sla_card']['elapsed'])->toBe('190s');
});

it('maps appointment status, closed conversations and visual substates to canonical stages', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'pipeline' => [
            ['lead_id' => 'lead-offered', 'appointment_status' => 'pending_confirmation'],
            ['lead_id' => 'lead-confirmed', 'appointment_status' => 'confirmed'],
            ['lead_id' => 'lead-lost', 'conversation_status' => 'closed'],
            ['lead_id' => 'lead-human', 'visual_substate' => 'aguardando_responsavel'],
            ['lead_id' => 'lead-qualifying', 'pipeline_substate' => 'classificando'],
            ['lead_id' => 'lead-exception', 'substate' => 'exception_review'],
        ],
    ]));

    $columns = collect($presented['pipeline_columns'])->keyBy('key');

    expect($columns['agendamento_oferecido']['items'])->toHaveCount(1);
    expect($columns['agendado']['items'])->toHaveCount(1);
    expect($columns['perdido']['items'])->toHaveCount(1);
    expect($columns['humano_necessario']['items'])->toHaveCount(1);
    expect($columns['qualificando']['items'])->toHaveCount(1);
    expect($columns['excecao']['items'])->toHaveCount(1);
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
        'agenda' => [[
            'appointment_id' => 'appt-stale',
            'appointment_status' => 'confirmed',
            'starts_at' => '2026-06-03T13:00:00Z',
        ]],
        'evidence' => [[
            'evidence_id' => 'evidence-stale',
            'title' => 'Fonte aprovada',
            'source_status' => 'active',
        ]],
        'exceptions' => [[
            'human_escalation_id' => 'exc-stale',
            'exception_reason' => 'booking_conflict',
            'status' => 'resolved',
        ]],
    ]));

    expect($presented['is_degraded'])->toBeTrue();
    expect(collect($presented['pipeline_columns'])->sum(fn ($column) => count($column['items'])))->toBe(0);
    expect($presented['timeline'])->toBe([]);
    expect($presented['schedule']['items'])->toBe([]);
    expect($presented['evidence']['items'])->toBe([]);
    expect($presented['exceptions']['items'])->toBe([]);
    expect($presented['lead_summary']['empty'])->toBeTrue();
});

it('presents schedule slots with canonical status, timezone labels and honest confirmation state', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'clinic' => [
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Clinica Manaus',
            'timezone' => 'America/Manaus',
        ],
        'agenda' => [
            [
                'appointment_id' => 'appt-confirmed-123456789',
                'slot_id' => 'slot-1',
                'professional_id' => 'prof-1',
                'room_id' => 'room-1',
                'duration_minutes' => 50,
                'starts_at' => '2026-06-03T13:00:00Z',
                'ends_at' => '2026-06-03T13:50:00Z',
                'timezone' => 'America/Manaus',
                'appointment_status' => 'confirmed',
                'last_correlation_id' => 'corr-schedule-123456789',
            ],
            [
                'appointment_id' => 'appt-failed',
                'appointment_status' => 'failed_exception',
                'starts_at' => 'not-a-date',
                'sync_status' => 'fresh',
            ],
        ],
    ]));

    expect($presented['schedule']['items'])->toHaveCount(2);
    expect($presented['schedule']['items'][0]['component'])->toBe('CompactScheduleSlot');
    expect($presented['schedule']['items'][0]['status_label'])->toBe('Confirmado');
    expect($presented['schedule']['items'][0]['confirmation_state'])->toBe('confirmado por projection fresca');
    expect($presented['schedule']['items'][0]['time_range'])->toBe('03/06/2026 09:00 - 09:50');
    expect($presented['schedule']['items'][0]['resource_label'])->toBe('Sala room-1 · Profissional prof-1');
    expect($presented['schedule']['items'][0]['trace'])->toBe('corr-sch...6789');
    expect($presented['schedule']['items'][1]['status_label'])->toBe('Falha ou conflito');
    expect($presented['schedule']['items'][1]['severity'])->toBe('breached');
    expect($presented['schedule']['items'][1]['time_range'])->toBe('Sem timestamp');
});

it('presents evidence badges and drawer data without leaking non allowlisted fields', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'evidence' => [
            [
                'evidence_id' => 'evidence-approved-123456789',
                'title' => 'Horario de funcionamento',
                'source_type' => 'faq',
                'source_status' => 'active',
                'approval_state' => 'approved',
                'knowledge_version' => 'kv-2026-06',
                'source_date' => '2026-06-01',
                'cited_reference' => 'FAQ-12',
                'safe_summary' => 'Resposta enviada com fonte aprovada.',
                'confidence' => 'high',
                'support' => 'supported',
                'policy_version' => 'policy-v1',
                'rule_version' => 'rule-v1',
                'fallback_used' => false,
                'handoff_required' => false,
                'recorded_at' => '2026-06-02T12:30:00Z',
                'prompt' => 'nao pode aparecer',
                'raw_payload' => ['secret' => true],
            ],
            [
                'evidence_id' => 'evidence-missing',
                'fallback_used' => true,
                'fallback_reason' => 'source_missing',
                'handoff_required' => true,
            ],
        ],
    ]));

    expect($presented['evidence']['items'])->toHaveCount(2);
    expect($presented['evidence']['items'][0]['component'])->toBe('EvidenceBadge');
    expect($presented['evidence']['items'][0]['title'])->toBe('Horario de funcionamento');
    expect($presented['evidence']['items'][0]['status_label'])->toBe('Fonte aprovada');
    expect($presented['evidence']['items'][0]['reference_label'])->toBe('Ref FAQ-12');
    expect($presented['evidence']['items'][0]['support_label'])->toBe('high · supported');
    expect($presented['evidence']['items'][0])->not->toHaveKeys(['prompt', 'raw_payload']);
    expect($presented['evidence']['items'][1]['status_label'])->toBe('Fonte ausente ou fallback');
    expect($presented['evidence']['items'][1]['fallback_label'])->toBe('Fallback: source_missing');
    expect($presented['evidence']['items'][1]['handoff_label'])->toBe('Handoff humano necessario');
});

it('presents human exception cards with reason severity SLA owner and trace', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'exceptions' => [
            [
                'human_escalation_id' => 'exc-booking-123456789',
                'severity' => 'high',
                'exception_reason' => 'booking_conflict',
                'status' => 'open',
                'automation_state' => 'pausada',
                'affected_entity_type' => 'appointment',
                'safe_summary' => 'Conflito de sala detectado.',
                'suggested_action' => 'Revisar slot e contatar responsavel.',
                'sla_impact' => 'breached',
                'owner_role' => 'receptionist',
                'owner_user_id' => 'user-1',
                'created_at' => '2026-06-02T12:10:00Z',
                'due_at' => '2026-06-02T12:20:00Z',
                'correlation_id' => 'corr-exception-123456789',
                'stack_trace' => 'nao pode aparecer',
            ],
        ],
    ]));

    expect($presented['exceptions']['items'])->toHaveCount(1);
    expect($presented['exceptions']['items'][0]['component'])->toBe('HumanExceptionCard');
    expect($presented['exceptions']['items'][0]['reason_label'])->toBe('Conflito de agenda');
    expect($presented['exceptions']['items'][0]['severity_label'])->toBe('Alta');
    expect($presented['exceptions']['items'][0]['sla_label'])->toBe('SLA violado');
    expect($presented['exceptions']['items'][0]['owner_label'])->toBe('Responsavel: receptionist · user-1');
    expect($presented['exceptions']['items'][0]['trace'])->toBe('corr-exc...6789');
    expect($presented['exceptions']['items'][0])->not->toHaveKey('stack_trace');
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
                'masked_external_ref' => 'doc-***-123',
            ],
            [
                'clinic_id' => '11111111-1111-4111-8111-111111111111',
                'event_type' => 'operator_note',
                'summary' => 'Resposta administrativa sem canal WhatsApp',
                'occurred_at' => '2026-06-02T12:03:00Z',
                'actor_type' => 'operator',
                'source_system' => 'crm',
            ],
        ],
    ]));

    expect($presented['timeline'])->toHaveCount(3);
    expect($presented['timeline'][0]['title'])->toBe('Mensagem recebida');
    expect($presented['timeline'][0]['actor'])->toBe('Responsavel');
    expect($presented['timeline'][1]['evidence_marker'])->toBeTrue();
    expect($presented['timeline'][1]['external_ref'])->toBe('doc-***-123');
    expect($presented['whatsapp_mirror']['messages'])->toHaveCount(1);
    expect($presented['whatsapp_mirror']['messages'][0]['summary'])->toBe('Mensagem recebida com resumo seguro');
});

it('filters WhatsApp mirror by canonical event/source fields instead of translated title text', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'timeline' => [
            [
                'event_type' => 'response_sent',
                'summary' => 'Resumo UI-safe enviado',
                'occurred_at' => '2026-06-02T12:01:00Z',
                'actor_type' => 'automation',
                'source_system' => 'crm',
            ],
            [
                'event_type' => 'operator_note',
                'summary' => 'Evento de CRM com palavra resposta no resumo',
                'occurred_at' => '2026-06-02T12:02:00Z',
                'actor_type' => 'operator',
                'source_system' => 'crm',
            ],
        ],
    ]));

    expect($presented['whatsapp_mirror']['messages'])->toHaveCount(1);
    expect($presented['whatsapp_mirror']['messages'][0]['event_type'])->toBe('response_sent');
});

it('uses the clinic timezone and tolerates invalid timestamps', function () {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'clinic' => [
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Clinica Manaus',
            'timezone' => 'America/Manaus',
        ],
        'updated_at' => '2026-06-02T12:00:00Z',
        'timeline' => [
            [
                'event_type' => 'message_received',
                'summary' => 'Timestamp invalido nao quebra cockpit',
                'occurred_at' => 'nao-e-data',
                'actor_type' => 'contact',
                'source_system' => 'whatsapp',
            ],
        ],
    ]));

    expect($presented['sync']['updated_label'])->toBe('02/06/2026 08:00');
    expect($presented['timeline'][0]['occurred_at'])->toBe('Sem timestamp');
});

it('keeps loading and partial sync states explicit in the presenter', function (string $syncStatus, string $label, string $pollingState) {
    $presented = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'sync' => [
            'sync_status' => $syncStatus,
            'last_success_at' => '2026-06-02T12:00:00Z',
        ],
    ]));

    expect($presented['sync']['label'])->toBe($label);
    expect($presented['sync']['polling_state'])->toBe($pollingState);
})->with([
    ['loading', 'Carregando sync', 'polling carregando'],
    ['partial', 'Sync parcial', 'polling parcial'],
]);

it('renders the cockpit view with operational data and masked references', function () {
    test()->actingAs(getDefaultAdmin(), 'user');
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);

    $cockpit = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'pipeline' => [[
            'lead_id' => 'lead-view',
            'classification' => 'new_lead',
            'sla_status' => 'at_risk',
            'sla_elapsed_seconds' => 110,
        ]],
        'timeline' => [[
            'event_type' => 'message_received',
            'summary' => 'Resumo seguro para view',
            'occurred_at' => '2026-06-02T12:01:00Z',
            'actor_type' => 'contact',
            'source_system' => 'whatsapp',
            'correlation_id' => 'corr-view-123456789',
            'masked_external_ref' => 'wa-***-123',
        ]],
    ]));

    $view = view('neuroflow::realtime-operation.index', [
        'cockpit' => $cockpit,
        'desktopMinWidth' => 1024,
    ])->render();

    expect($view)->toContain('SyncStatusIndicator');
    expect($view)->toContain('SlaCard');
    expect($view)->toContain('LeadSummaryCard');
    expect($view)->toContain('tenant channel header');
    expect($view)->toContain('automation human actor badge');
    expect($view)->toContain('WhatsApp conversation mirror');
    expect($view)->toContain('Lead lead-view');
    expect($view)->toContain('Ref wa-***-123');
});

it('renders schedule evidence and exception components with accessible labels', function () {
    test()->actingAs(getDefaultAdmin(), 'user');
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);

    $cockpit = app(RealtimeOperationPresenter::class)->present(freshRealtimeState([
        'agenda' => [[
            'appointment_id' => 'appt-view',
            'appointment_status' => 'pending_confirmation',
            'professional_id' => 'prof-view',
            'room_id' => 'room-view',
            'starts_at' => '2026-06-03T13:00:00Z',
            'ends_at' => '2026-06-03T13:50:00Z',
        ]],
        'evidence' => [[
            'evidence_id' => 'evidence-view',
            'title' => 'Fonte demo',
            'approval_state' => 'approved',
            'source_status' => 'active',
            'cited_reference' => 'FAQ-12',
            'safe_summary' => 'Resumo seguro de evidencia.',
            'confidence' => 'high',
            'support' => 'supported',
        ]],
        'exceptions' => [[
            'human_escalation_id' => 'exception-view',
            'exception_reason' => 'source_missing',
            'severity' => 'medium',
            'status' => 'open',
            'safe_summary' => 'Fonte ausente para responder.',
            'suggested_action' => 'Validar fonte com coordencao.',
            'sla_impact' => 'at_risk',
            'owner_role' => 'manager_coordinator',
        ]],
    ]));

    $view = view('neuroflow::realtime-operation.index', [
        'cockpit' => $cockpit,
        'desktopMinWidth' => 1024,
    ])->render();

    expect($view)->toContain('CompactScheduleSlot');
    expect($view)->toContain('EvidenceBadge');
    expect($view)->toContain('EvidenceDrawer');
    expect($view)->toContain('HumanExceptionCard');
    expect($view)->toContain('aria-expanded');
    expect($view)->toContain('aria-controls');
    expect($view)->toContain('Resumo seguro de evidencia.');
    expect($view)->toContain('Fonte ausente para responder.');
});
