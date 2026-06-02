<?php

namespace Webkul\NeuroFlow\Infrastructure\Demo;

use Webkul\NeuroFlow\Domain\Contracts\RealtimeOperationReadModel;
use Webkul\NeuroFlow\Domain\Enums\SyncStatus;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;

class DemoRealtimeOperationReadModel implements RealtimeOperationReadModel
{
    public function stateFor(ActiveClinic $clinic): array
    {
        try {
            $fixture = $this->fixture();
            $scenario = (string) config('neuroflow.demo_realtime_scenario', 'happy_path');
            $state = $fixture['states'][$scenario] ?? null;

            if (! is_array($state)) {
                return $this->emptyState($clinic, SyncStatus::Failed->value, 'DEMO_SCENARIO_NOT_FOUND');
            }

            return $this->normalizeState($clinic, $state);
        } catch (\Throwable) {
            return $this->emptyState($clinic, SyncStatus::Failed->value, 'DEMO_FIXTURE_UNAVAILABLE');
        }
    }

    private function fixture(): array
    {
        $path = config('neuroflow.demo_realtime_fixture')
            ?: dirname(__DIR__, 2).'/Resources/fixtures/realtime-operation-demo.v1.json';

        if (! is_file($path)) {
            throw new \RuntimeException('Demo realtime operation fixture is unavailable.');
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            throw new \UnexpectedValueException('Demo realtime operation fixture is invalid.');
        }

        return $payload;
    }

    private function normalizeState(ActiveClinic $clinic, array $state): array
    {
        $sync = $state['sync'] ?? [];
        $syncStatus = SyncStatus::tryFrom((string) ($sync['sync_status'] ?? SyncStatus::Failed->value))?->value
            ?? SyncStatus::Failed->value;
        $updatedAt = $state['updated_at'] ?? $sync['last_success_at'] ?? null;
        $isFresh = $syncStatus === SyncStatus::Fresh->value;

        return [
            'schema_version' => 'laravel.crm.realtime-operation.v1',
            'clinic' => $clinic->toArray(),
            'sync' => [
                'projection_name' => 'realtime_operation_demo_mock',
                'sync_status' => $syncStatus,
                'last_success_at' => $sync['last_success_at'] ?? $updatedAt,
                'last_error_code' => $sync['last_error_code'] ?? null,
                'lag_seconds' => $sync['lag_seconds'] ?? 0,
                'source_system' => 'demo_mock',
                'correlation_id' => $sync['correlation_id'] ?? null,
                'idempotency_key' => $sync['idempotency_key'] ?? null,
            ],
            'pipeline' => $isFresh ? $this->sanitizeSection($clinic, $state, 'pipeline') : [],
            'timeline' => $isFresh ? $this->sanitizeSection($clinic, $state, 'timeline') : [],
            'agenda' => $isFresh ? $this->sanitizeSection($clinic, $state, 'agenda') : [],
            'evidence' => $isFresh ? $this->sanitizeSection($clinic, $state, 'evidence') : [],
            'exceptions' => $isFresh ? $this->sanitizeSection($clinic, $state, 'exceptions') : [],
            'updated_at' => $updatedAt,
        ];
    }

    private function sanitizeSection(ActiveClinic $clinic, array $state, string $section): array
    {
        $rows = $state[$section] ?? [];

        if (! is_array($rows)) {
            throw new \UnexpectedValueException('Demo projection section is not an array.');
        }

        $allowed = array_flip($this->allowedFields($section));

        return array_values(array_map(
            fn (array $row): array => array_intersect_key($row, $allowed),
            array_filter($rows, fn ($row): bool => is_array($row) && (string) ($row['clinic_id'] ?? '') === $clinic->id)
        ));
    }

    private function allowedFields(string $section): array
    {
        return match ($section) {
            'pipeline' => [
                'clinic_id', 'contact_id', 'lead_id', 'conversation_id', 'classification', 'qualification_status',
                'conversation_status', 'next_action', 'sla_status', 'sla_target_seconds', 'sla_elapsed_seconds',
                'first_response_at', 'first_response_due_at', 'delivery_status', 'last_workflow_run_id',
                'appointment_status', 'visual_substate', 'pipeline_substate', 'substate', 'last_correlation_id', 'updated_at',
            ],
            'timeline' => [
                'clinic_id', 'timeline_event_id', 'contact_id', 'conversation_id', 'lead_id', 'appointment_id',
                'event_type', 'summary', 'occurred_at', 'actor_type', 'source_system', 'correlation_id',
                'masked_external_ref',
            ],
            'agenda' => [
                'clinic_id', 'appointment_id', 'slot_id', 'unit_id', 'professional_id', 'room_id', 'service_id',
                'duration_minutes', 'starts_at', 'ends_at', 'timezone', 'appointment_status', 'booking_source',
                'contact_id', 'lead_id', 'patient_id', 'sync_status', 'last_correlation_id',
            ],
            'evidence' => [
                'clinic_id', 'conversation_id', 'evidence_id', 'source_id', 'title', 'source_type',
                'source_status', 'approval_state', 'knowledge_version', 'source_date', 'cited_reference',
                'safe_summary', 'confidence', 'support', 'policy_version', 'rule_version', 'prompt_version',
                'fallback_used', 'fallback_reason', 'handoff_required', 'recorded_at',
            ],
            'exceptions' => [
                'clinic_id', 'human_escalation_id', 'severity', 'exception_reason', 'reason', 'status',
                'automation_state', 'affected_entity_type', 'affected_entity_id', 'safe_summary',
                'suggested_action', 'sla_impact', 'owner_role', 'owner_user_id', 'created_at', 'claimed_at',
                'resolved_at', 'due_at', 'linked_event_id', 'conversation_id', 'contact_id', 'lead_id',
                'patient_id', 'appointment_id', 'workflow_run_id', 'correlation_id', 'masked_external_ref',
            ],
        };
    }

    private function emptyState(ActiveClinic $clinic, string $status, ?string $errorCode): array
    {
        return [
            'schema_version' => 'laravel.crm.realtime-operation.v1',
            'clinic' => $clinic->toArray(),
            'sync' => [
                'projection_name' => 'realtime_operation_demo_mock',
                'sync_status' => $status,
                'last_success_at' => null,
                'last_error_code' => $errorCode,
                'lag_seconds' => null,
                'source_system' => 'demo_mock',
                'correlation_id' => null,
                'idempotency_key' => null,
            ],
            'pipeline' => [],
            'timeline' => [],
            'agenda' => [],
            'evidence' => [],
            'exceptions' => [],
            'updated_at' => null,
        ];
    }
}
