<?php

use Webkul\NeuroFlow\Application\Presenters\RealtimeOperationPresenter;
use Webkul\NeuroFlow\Infrastructure\Demo\DemoRealtimeOperationReadModel;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;

function demoRunbookClinic(string $id = '11111111-1111-4111-8111-111111111111'): ActiveClinic
{
    return new ActiveClinic($id, 'Clinica NeuroFlow Demo', 'America/Sao_Paulo', 1);
}

function renderDemoRunbookScenario(string $scenario, ?ActiveClinic $clinic = null): string
{
    test()->actingAs(getDefaultAdmin(), 'user');
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);

    config([
        'neuroflow.demo_realtime_scenario' => $scenario,
    ]);

    $state = (new DemoRealtimeOperationReadModel)->stateFor($clinic ?? demoRunbookClinic());
    $cockpit = app(RealtimeOperationPresenter::class)->present($state);

    return view('neuroflow::realtime-operation.index', [
        'cockpit' => $cockpit,
        'desktopMinWidth' => 1024,
    ])->render();
}

it('renders the happy path demo chain without requiring external logs', function () {
    $html = renderDemoRunbookScenario('happy_path');

    foreach ([
        'Mensagem recebida',
        'Tenant resolvido',
        'Contato classificado',
        'Qualificacao atualizada',
        'Evidencia consultada',
        'Horario oferecido',
        'Resposta enviada',
        'Agendamento confirmado',
        'CRM atualizado',
        'SLA ok',
        'Lead novo',
        'Qualificacao completa',
        'Confirmado',
        'Fonte aprovada',
        'demo_mock',
    ] as $marker) {
        expect($html)->toContain($marker);
    }

    expect($html)->not->toContain('PHONE_DEMO_PLACEHOLDER');
    expect($html)->not->toContain('raw_payload');
    expect($html)->not->toContain('Authorization');
    expect($html)->not->toContain('BEGIN OPENSSH PRIVATE KEY');
});

it('renders mandatory exception scenarios as fail-closed or blocked states', function () {
    $unknownTenant = renderDemoRunbookScenario('unknown_tenant');
    expect($unknownTenant)->toContain('Estado canonico degradado');
    expect($unknownTenant)->toContain('UNKNOWN_TENANT');
    expect($unknownTenant)->not->toContain('lead_must_not_render_unknown_tenant');
    expect($unknownTenant)->not->toContain('Agendamento confirmado');
    expect($unknownTenant)->not->toContain('confirmado por projection fresca');

    $missingSsot = renderDemoRunbookScenario('missing_ssot');
    expect($missingSsot)->toContain('Fonte ausente');
    expect($missingSsot)->toContain('Handoff humano necessario');
    expect($missingSsot)->toContain('Excecoes Humanas');

    $slotConflict = renderDemoRunbookScenario('slot_conflict');
    expect($slotConflict)->toContain('Falha ou conflito');
    expect($slotConflict)->toContain('Conflito de agenda');
    expect($slotConflict)->not->toContain('confirmado por projection fresca');

    $syncFailure = renderDemoRunbookScenario('crm_n8n_sync_failure');
    expect($syncFailure)->toContain('Estado canonico degradado');
    expect($syncFailure)->toContain('UPSTREAM_FAILURE');
    expect($syncFailure)->not->toContain('Agendamento confirmado');
});

it('keeps duplicate replay and cross-tenant demo states visually constrained', function () {
    config([
        'neuroflow.demo_realtime_scenario' => 'duplicate_replay',
    ]);

    $state = (new DemoRealtimeOperationReadModel)->stateFor(demoRunbookClinic());
    expect(collect($state['pipeline'])->pluck('lead_id')->duplicates()->all())->toBe([]);
    expect(collect($state['timeline'])->pluck('timeline_event_id')->duplicates()->all())->toBe([]);
    expect(collect($state['agenda'])->pluck('appointment_id')->duplicates()->all())->toBe([]);

    $mainClinicHtml = renderDemoRunbookScenario('same_phone_cross_tenant', demoRunbookClinic());
    $crossTenantHtml = renderDemoRunbookScenario(
        'same_phone_cross_tenant',
        demoRunbookClinic('55555555-5555-4555-8555-555555555555')
    );

    expect($mainClinicHtml)->not->toContain('lead_demo_cross_tenant_0001');
    expect($crossTenantHtml)->toContain('Lead lead_dem...0001');
    expect($crossTenantHtml)->not->toContain('PHONE_DEMO_PLACEHOLDER');
});
