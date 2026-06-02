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
        $sync = $payload['sync'] ?? [];
        $syncStatus = $this->validSyncStatus($sync['sync_status'] ?? null);
        $updatedAt = $payload['updated_at'] ?? $sync['last_success_at'] ?? null;
        $lagSeconds = $sync['lag_seconds'] ?? $this->lagSeconds($updatedAt);

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
            'pipeline' => $isCanonicalFresh ? ($payload['pipeline'] ?? []) : [],
            'timeline' => $isCanonicalFresh ? ($payload['timeline'] ?? []) : [],
            'agenda' => $isCanonicalFresh ? ($payload['agenda'] ?? []) : [],
            'evidence' => $isCanonicalFresh ? ($payload['evidence'] ?? []) : [],
            'exceptions' => $isCanonicalFresh ? ($payload['exceptions'] ?? []) : [],
            'updated_at' => $updatedAt,
        ];
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
