<?php

namespace Webkul\NeuroFlow\Infrastructure\Supabase;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;
use Webkul\NeuroFlow\Domain\Contracts\RealtimeOperationReadModel;
use Webkul\NeuroFlow\Domain\Enums\SyncStatus;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;

class SupabaseRealtimeOperationReadModel implements RealtimeOperationReadModel
{
    public function __construct(private readonly ?ClientInterface $client = null)
    {
    }

    public function stateFor(ActiveClinic $clinic): array
    {
        if (! $this->isConfigured()) {
            return $this->disabledState($clinic);
        }

        try {
            return $this->normalizeState($clinic, $this->fetchState($clinic));
        } catch (GuzzleException) {
            return $this->failedState($clinic, 'SUPABASE_REQUEST_FAILED');
        } catch (\Throwable) {
            return $this->failedState($clinic, 'CRM_PROJECTION_INVALID');
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('neuroflow.supabase_url'))
            && filled(config('neuroflow.supabase_service_role_key'));
    }

    /**
     * @throws GuzzleException
     */
    private function fetchState(ActiveClinic $clinic): array
    {
        $client = $this->client ?? new \GuzzleHttp\Client([
            'base_uri' => rtrim((string) config('neuroflow.supabase_url'), '/').'/',
            'timeout' => 8,
        ]);

        $response = $client->request('POST', 'rest/v1/rpc/'.config('neuroflow.supabase_realtime_state_rpc'), [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'apikey' => (string) config('neuroflow.supabase_service_role_key'),
                'Authorization' => 'Bearer '.config('neuroflow.supabase_service_role_key'),
            ],
            'json' => [
                'p_clinic_id' => $clinic->id,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload)) {
            throw new \UnexpectedValueException('Invalid Supabase projection response.');
        }

        return $payload[0] ?? $payload;
    }

    private function normalizeState(ActiveClinic $clinic, array $payload): array
    {
        $this->assertTenantMatches($clinic, $payload);

        $sync = $payload['sync'] ?? [];
        $syncStatus = $this->validSyncStatus($sync['sync_status'] ?? null);
        $updatedAt = $payload['updated_at'] ?? $sync['last_success_at'] ?? null;
        $lagSeconds = $sync['lag_seconds'] ?? $this->lagSeconds($updatedAt);

        if ($syncStatus === SyncStatus::Fresh->value && $updatedAt === null) {
            throw new \UnexpectedValueException('Fresh projection has no sync timestamp.');
        }

        if ($syncStatus === SyncStatus::Fresh->value && $lagSeconds !== null && $lagSeconds > config('neuroflow.stale_after_seconds')) {
            $syncStatus = SyncStatus::Stale->value;
        }

        $isCanonicalFresh = $syncStatus === SyncStatus::Fresh->value;

        return [
            'schema_version' => 'laravel.crm.realtime-operation.v1',
            'clinic' => $clinic->toArray(),
            'sync' => [
                'projection_name' => 'realtime_operation',
                'sync_status' => $syncStatus,
                'last_success_at' => $sync['last_success_at'] ?? $updatedAt,
                'last_error_code' => $sync['last_error_code'] ?? null,
                'lag_seconds' => $lagSeconds,
            ],
            'pipeline' => $isCanonicalFresh ? $this->sanitizeSection($clinic, $payload, 'pipeline') : [],
            'timeline' => $isCanonicalFresh ? $this->sanitizeSection($clinic, $payload, 'timeline') : [],
            'agenda' => $isCanonicalFresh ? $this->sanitizeSection($clinic, $payload, 'agenda') : [],
            'evidence' => $isCanonicalFresh ? $this->sanitizeSection($clinic, $payload, 'evidence') : [],
            'exceptions' => $isCanonicalFresh ? $this->sanitizeSection($clinic, $payload, 'exceptions') : [],
            'updated_at' => $updatedAt,
        ];
    }

    private function assertTenantMatches(ActiveClinic $clinic, array $payload): void
    {
        if (isset($payload['clinic_id']) && (string) $payload['clinic_id'] !== $clinic->id) {
            throw new \UnexpectedValueException('Projection tenant does not match active clinic.');
        }
    }

    private function sanitizeSection(ActiveClinic $clinic, array $payload, string $section): array
    {
        $rows = $payload[$section] ?? [];

        if (! is_array($rows)) {
            throw new \UnexpectedValueException('Projection section is not an array.');
        }

        return array_map(function ($row) use ($clinic, $section): array {
            if (! is_array($row) || (string) ($row['clinic_id'] ?? '') !== $clinic->id) {
                throw new \UnexpectedValueException('Projection row tenant does not match active clinic.');
            }

            return array_intersect_key($row, array_flip($this->allowedFields($section)));
        }, $rows);
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

    private function disabledState(ActiveClinic $clinic): array
    {
        return $this->emptyState($clinic, SyncStatus::Disabled->value, 'SUPABASE_NOT_CONFIGURED');
    }

    private function failedState(ActiveClinic $clinic, string $errorCode): array
    {
        return $this->emptyState($clinic, SyncStatus::Failed->value, $errorCode);
    }

    private function emptyState(ActiveClinic $clinic, string $status, ?string $errorCode): array
    {
        return [
            'schema_version' => 'laravel.crm.realtime-operation.v1',
            'clinic' => $clinic->toArray(),
            'sync' => [
                'projection_name' => 'realtime_operation',
                'sync_status' => $status,
                'last_success_at' => null,
                'last_error_code' => $errorCode,
                'lag_seconds' => null,
            ],
            'pipeline' => [],
            'timeline' => [],
            'agenda' => [],
            'evidence' => [],
            'exceptions' => [],
            'updated_at' => null,
        ];
    }

    private function validSyncStatus(?string $status): string
    {
        return SyncStatus::tryFrom((string) $status)?->value ?? SyncStatus::Failed->value;
    }

    private function lagSeconds(?string $updatedAt): ?int
    {
        if (! $updatedAt) {
            return null;
        }

        return max(0, Carbon::parse($updatedAt)->diffInSeconds(now()));
    }
}
