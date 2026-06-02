<?php

return [
    'demo_mode' => env('NEUROFLOW_DEMO_MODE', true),
    'use_supabase_mocks' => filter_var(env('NEUROFLOW_USE_SUPABASE_MOCKS', env('NF_USE_SUPABASE_MOCKS', false)), FILTER_VALIDATE_BOOLEAN),
    'demo_clinic_id' => env('NEUROFLOW_DEMO_CLINIC_ID', '11111111-1111-4111-8111-111111111111'),
    'demo_clinic_name' => env('NEUROFLOW_DEMO_CLINIC_NAME', 'Clinica NeuroFlow Demo'),
    'demo_clinic_timezone' => env('NEUROFLOW_DEMO_CLINIC_TIMEZONE', 'America/Sao_Paulo'),
    'demo_realtime_scenario' => env('NEUROFLOW_DEMO_REALTIME_SCENARIO', 'happy_path'),
    'demo_realtime_fixture' => env('NEUROFLOW_DEMO_REALTIME_FIXTURE'),
    'supabase_url' => env('NEUROFLOW_SUPABASE_URL', env('SUPABASE_URL')),
    'supabase_service_role_key' => env('NEUROFLOW_SUPABASE_SERVICE_ROLE_KEY', env('SUPABASE_SERVICE_ROLE_KEY')),
    'supabase_realtime_state_rpc' => env('NEUROFLOW_SUPABASE_REALTIME_STATE_RPC', 'neuroflow_get_laravel_crm_realtime_operation_state'),
    'polling_interval_seconds' => (int) env('NEUROFLOW_POLLING_INTERVAL_SECONDS', 30),
    'stale_after_seconds' => (int) env('NEUROFLOW_STALE_AFTER_SECONDS', 120),
    'desktop_min_width_px' => (int) env('NEUROFLOW_DESKTOP_MIN_WIDTH_PX', 1024),
];
