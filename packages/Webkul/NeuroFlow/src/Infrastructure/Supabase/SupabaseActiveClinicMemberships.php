<?php

namespace Webkul\NeuroFlow\Infrastructure\Supabase;

use GuzzleHttp\ClientInterface;
use Webkul\NeuroFlow\Domain\Contracts\ActiveClinicMemberships;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;

class SupabaseActiveClinicMemberships implements ActiveClinicMemberships
{
    public function __construct(private readonly ?ClientInterface $client = null)
    {
    }

    public function activeClinicFor(object $user, string $clinicId): ?ActiveClinic
    {
        if (! $this->isConfigured() || blank($clinicId) || blank($user->email ?? null)) {
            return null;
        }

        try {
            $neuroflowUserId = $this->resolveNeuroFlowUserId((string) $user->email);

            if (! $neuroflowUserId || ! $this->hasActiveMembership($neuroflowUserId, $clinicId)) {
                return null;
            }

            return new ActiveClinic(
                id: $clinicId,
                name: (string) config('neuroflow.demo_clinic_name'),
                timezone: (string) config('neuroflow.demo_clinic_timezone'),
                userId: (int) $user->id,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('neuroflow.supabase_url'))
            && filled(config('neuroflow.supabase_service_role_key'));
    }

    private function resolveNeuroFlowUserId(string $email): ?string
    {
        $rows = $this->request('GET', 'rest/v1/neuroflow_users', [
            'query' => [
                'select' => 'user_id',
                'email' => 'eq.'.$email,
                'status' => 'eq.active',
                'limit' => 1,
            ],
        ]);

        return is_string($rows[0]['user_id'] ?? null) ? $rows[0]['user_id'] : null;
    }

    private function hasActiveMembership(string $neuroflowUserId, string $clinicId): bool
    {
        $rows = $this->request('GET', 'rest/v1/neuroflow_clinic_memberships', [
            'query' => [
                'select' => 'membership_id',
                'user_id' => 'eq.'.$neuroflowUserId,
                'clinic_id' => 'eq.'.$clinicId,
                'status' => 'eq.active',
                'active_clinic_context_allowed' => 'eq.true',
                'limit' => 1,
            ],
        ]);

        return filled($rows[0]['membership_id'] ?? null);
    }

    private function request(string $method, string $uri, array $options): array
    {
        $client = $this->client ?? new \GuzzleHttp\Client([
            'base_uri' => rtrim((string) config('neuroflow.supabase_url'), '/').'/',
            'timeout' => 8,
        ]);

        $response = $client->request($method, $uri, array_replace_recursive($options, [
            'headers' => [
                'Accept' => 'application/json',
                'apikey' => (string) config('neuroflow.supabase_service_role_key'),
                'Authorization' => 'Bearer '.config('neuroflow.supabase_service_role_key'),
            ],
        ]));

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload)) {
            throw new \UnexpectedValueException('Invalid Supabase membership response.');
        }

        return $payload;
    }
}
