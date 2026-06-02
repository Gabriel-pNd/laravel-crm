<?php

use Webkul\NeuroFlow\Application\Presenters\RealtimeOperationPresenter;
use Webkul\NeuroFlow\Domain\Contracts\RealtimeOperationReadModel;
use Webkul\NeuroFlow\Infrastructure\Demo\DemoRealtimeOperationReadModel;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;
use Webkul\NeuroFlow\Infrastructure\Supabase\SupabaseRealtimeOperationReadModel;

function demoActiveClinic(string $id = '11111111-1111-4111-8111-111111111111'): ActiveClinic
{
    return new ActiveClinic($id, 'Clinica NeuroFlow Demo', 'America/Sao_Paulo', 1);
}

it('binds the demo read model only when demo mode and Supabase mocks are explicitly enabled', function () {
    config([
        'neuroflow.demo_mode' => true,
        'neuroflow.use_supabase_mocks' => true,
    ]);

    expect(app(RealtimeOperationReadModel::class))->toBeInstanceOf(DemoRealtimeOperationReadModel::class);

    $this->app->forgetInstance(RealtimeOperationReadModel::class);
    config([
        'neuroflow.demo_mode' => false,
        'neuroflow.use_supabase_mocks' => true,
    ]);

    expect(app(RealtimeOperationReadModel::class))->toBeInstanceOf(SupabaseRealtimeOperationReadModel::class);
});

it('returns a fresh demo projection with explicit mock origin and canonical sections', function () {
    config([
        'neuroflow.demo_realtime_scenario' => 'happy_path',
    ]);

    $state = (new DemoRealtimeOperationReadModel)->stateFor(demoActiveClinic());

    expect($state['schema_version'])->toBe('laravel.crm.realtime-operation.v1');
    expect($state['clinic']['id'])->toBe('11111111-1111-4111-8111-111111111111');
    expect($state['sync']['sync_status'])->toBe('fresh');
    expect($state['sync']['projection_name'])->toBe('realtime_operation_demo_mock');
    expect($state['sync']['source_system'])->toBe('demo_mock');
    expect($state['sync']['correlation_id'])->toBe('corr_demo_happy_0001');
    expect($state['sync']['idempotency_key'])->toBe('idem_demo_book_happy_0001');
    expect($state['pipeline'])->not->toBeEmpty();
    expect($state['timeline'])->not->toBeEmpty();
    expect($state['agenda'])->not->toBeEmpty();
    expect($state['evidence'])->not->toBeEmpty();
    expect($state['exceptions'])->not->toBeEmpty();
});

it('filters demo projection rows by active clinic and preserves same-phone cross-tenant isolation', function () {
    config([
        'neuroflow.demo_realtime_scenario' => 'same_phone_cross_tenant',
    ]);

    $main = (new DemoRealtimeOperationReadModel)->stateFor(demoActiveClinic());
    $crossTenant = (new DemoRealtimeOperationReadModel)->stateFor(demoActiveClinic('55555555-5555-4555-8555-555555555555'));

    expect($main['pipeline'])->toBe([]);
    expect($main['agenda'])->toBe([]);
    expect($crossTenant['pipeline'])->toHaveCount(1);
    expect($crossTenant['pipeline'][0]['lead_id'])->toBe('lead_demo_cross_tenant_0001');
    expect($crossTenant['pipeline'][0]['contact_phone'] ?? null)->toBeNull();
    expect($crossTenant['agenda'])->toHaveCount(1);
    expect($crossTenant['agenda'][0]['slot_id'])->toBe('slot_demo_cross_tenant_0001');
});

it('does not duplicate visual state for idempotent replay fixtures', function () {
    config([
        'neuroflow.demo_realtime_scenario' => 'duplicate_replay',
    ]);

    $state = (new DemoRealtimeOperationReadModel)->stateFor(demoActiveClinic());

    expect($state['sync']['idempotency_key'])->toBe('idem_demo_book_happy_0001');
    expect(collect($state['pipeline'])->pluck('lead_id')->duplicates()->all())->toBe([]);
    expect(collect($state['timeline'])->pluck('timeline_event_id')->duplicates()->all())->toBe([]);
    expect(collect($state['agenda'])->pluck('appointment_id')->duplicates()->all())->toBe([]);
    expect(collect($state['exceptions'])->pluck('human_escalation_id')->duplicates()->all())->toBe([]);
});

it('exposes CRM sync failure as degraded state without operational success rows', function () {
    config([
        'neuroflow.demo_realtime_scenario' => 'crm_n8n_sync_failure',
    ]);

    $state = (new DemoRealtimeOperationReadModel)->stateFor(demoActiveClinic());
    $presented = app(RealtimeOperationPresenter::class)->present($state);

    expect($state['sync']['sync_status'])->toBe('stale');
    expect($state['sync']['last_error_code'])->toBe('UPSTREAM_FAILURE');
    expect($state['pipeline'])->toBe([]);
    expect($state['agenda'])->toBe([]);
    expect($presented['is_degraded'])->toBeTrue();
    expect($presented['schedule']['items'])->toBe([]);
});

it('fails safely when the configured demo fixture is unavailable', function () {
    config([
        'neuroflow.demo_realtime_fixture' => base_path('missing-demo-fixture.json'),
    ]);

    $state = (new DemoRealtimeOperationReadModel)->stateFor(demoActiveClinic());

    expect($state['sync']['sync_status'])->toBe('failed');
    expect($state['sync']['last_error_code'])->toBe('DEMO_FIXTURE_UNAVAILABLE');
    expect($state['pipeline'])->toBe([]);
});

it('renders cockpit view from the demo projection fixture without leaking prohibited fields', function () {
    test()->actingAs(getDefaultAdmin(), 'user');
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);

    config([
        'neuroflow.demo_realtime_scenario' => 'happy_path',
    ]);

    $state = (new DemoRealtimeOperationReadModel)->stateFor(demoActiveClinic());
    $cockpit = app(RealtimeOperationPresenter::class)->present($state);

    $view = view('neuroflow::realtime-operation.index', [
        'cockpit' => $cockpit,
        'desktopMinWidth' => 1024,
    ])->render();

    expect($view)->toContain('SyncStatusIndicator');
    expect($view)->toContain('SlaCard');
    expect($view)->toContain('LeadSummaryCard');
    expect($view)->toContain('WhatsApp conversation mirror');
    expect($view)->toContain('CompactScheduleSlot');
    expect($view)->toContain('EvidenceBadge');
    expect($view)->toContain('EvidenceDrawer');
    expect($view)->toContain('HumanExceptionCard');
    expect($view)->toContain('demo_mock');
    expect($view)->not->toContain('PHONE_DEMO_PLACEHOLDER');
    expect($view)->not->toContain('BEGIN OPENSSH PRIVATE KEY');
    expect($view)->not->toContain('Authorization');
    expect($view)->not->toContain('raw_payload');
});
