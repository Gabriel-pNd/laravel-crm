<?php

use Illuminate\Http\Request;
use Webkul\NeuroFlow\Domain\Contracts\ActiveClinicMemberships;
use Webkul\NeuroFlow\Domain\Contracts\RealtimeOperationReadModel;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinic;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinicResolver;
use Webkul\NeuroFlow\Infrastructure\Supabase\SupabaseRealtimeOperationReadModel;

it('returns disabled sync status when supabase is not configured', function () {
    config([
        'neuroflow.supabase_url' => null,
        'neuroflow.supabase_service_role_key' => null,
    ]);

    $state = app(RealtimeOperationReadModel::class)->stateFor(new ActiveClinic(
        id: '11111111-1111-4111-8111-111111111111',
        name: 'Clinica Demo',
        timezone: 'America/Sao_Paulo',
        userId: 1,
    ));

    expect($state['sync']['sync_status'])->toBe('disabled');
    expect($state['sync']['last_error_code'])->toBe('SUPABASE_NOT_CONFIGURED');
    expect($state['pipeline'])->toBe([]);
});

it('does not resolve an active clinic for users without neuroflow permission', function () {
    $role = (object) [
        'permission_type' => 'custom',
        'permissions' => ['dashboard'],
    ];

    $user = new class($role) {
        public int $id = 10;
        public int $status = 1;

        public function __construct(public object $role)
        {
        }
    };

    $request = Request::create('/admin/neuroflow/realtime-operation');
    $request->setUserResolver(fn () => $user);

    expect(app(ActiveClinicResolver::class)->resolve($request))->toBeNull();
});

it('requires an active clinic membership after krayin permission passes', function () {
    $role = (object) [
        'permission_type' => 'all',
        'permissions' => [],
    ];

    $user = new class($role) {
        public int $id = 10;
        public int $status = 1;
        public string $email = 'aline.owner@example.test';

        public function __construct(public object $role)
        {
        }
    };

    $request = Request::create('/admin/neuroflow/realtime-operation');
    $request->setUserResolver(fn () => $user);

    app()->instance(ActiveClinicMemberships::class, new class implements ActiveClinicMemberships {
        public function activeClinicFor(object $user, string $clinicId): ?ActiveClinic
        {
            return null;
        }
    });

    expect(app(ActiveClinicResolver::class)->resolve($request))->toBeNull();

    app()->instance(ActiveClinicMemberships::class, new class implements ActiveClinicMemberships {
        public function activeClinicFor(object $user, string $clinicId): ?ActiveClinic
        {
            return new ActiveClinic($clinicId, 'Clinica Demo', 'America/Sao_Paulo', (int) $user->id);
        }
    });

    $clinic = app(ActiveClinicResolver::class)->resolve($request);

    expect($clinic)->not->toBeNull();
    expect($clinic->id)->toBe((string) config('neuroflow.demo_clinic_id'));
});

it('normalizes stale projections instead of promoting success', function () {
    config([
        'neuroflow.stale_after_seconds' => 1,
    ]);

    $client = new class implements \GuzzleHttp\ClientInterface {
        public function send(\Psr\Http\Message\RequestInterface $request, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'sync' => [
                    'sync_status' => 'fresh',
                    'last_success_at' => now()->subMinutes(5)->toISOString(),
                ],
                'agenda' => [
                    ['appointment_status' => 'confirmed'],
                ],
                'pipeline' => [],
                'timeline' => [],
                'evidence' => [],
                'exceptions' => [],
            ]));
        }

        public function sendAsync(\Psr\Http\Message\RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function request($method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return $this->send(new \GuzzleHttp\Psr7\Request($method, $uri), $options);
        }

        public function requestAsync($method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function getConfig(?string $option = null): mixed
        {
            return null;
        }
    };

    config([
        'neuroflow.supabase_url' => 'https://example.supabase.co',
        'neuroflow.supabase_service_role_key' => 'test-key',
    ]);

    $state = (new SupabaseRealtimeOperationReadModel($client))->stateFor(new ActiveClinic(
        id: '11111111-1111-4111-8111-111111111111',
        name: 'Clinica Demo',
        timezone: 'America/Sao_Paulo',
        userId: 1,
    ));

    expect($state['sync']['sync_status'])->toBe('stale');
    expect($state['agenda'])->toBe([]);
});

it('requests projections with the backend resolved clinic id for each tenant', function () {
    $requestedClinicIds = [];

    $client = new class($requestedClinicIds) implements \GuzzleHttp\ClientInterface {
        public array $clinicIds = [];

        public function __construct(array $clinicIds)
        {
            $this->clinicIds = $clinicIds;
        }

        public function send(\Psr\Http\Message\RequestInterface $request, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'sync' => [
                    'sync_status' => 'fresh',
                    'last_success_at' => now()->toISOString(),
                    'lag_seconds' => 0,
                ],
                'pipeline' => [
                    [
                        'clinic_id' => $this->clinicIds[array_key_last($this->clinicIds)],
                        'conversation_id' => 'conv-'.$this->clinicIds[array_key_last($this->clinicIds)],
                    ],
                ],
                'timeline' => [],
                'agenda' => [],
                'evidence' => [],
                'exceptions' => [],
            ]));
        }

        public function sendAsync(\Psr\Http\Message\RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function request($method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            $this->clinicIds[] = $options['json']['p_clinic_id'];

            return $this->send(new \GuzzleHttp\Psr7\Request($method, $uri), $options);
        }

        public function requestAsync($method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function getConfig(?string $option = null): mixed
        {
            return null;
        }
    };

    config([
        'neuroflow.supabase_url' => 'https://example.supabase.co',
        'neuroflow.supabase_service_role_key' => 'test-key',
    ]);

    $readModel = new SupabaseRealtimeOperationReadModel($client);

    $first = $readModel->stateFor(new ActiveClinic('clinic-a', 'Clinica A', 'America/Sao_Paulo', 1));
    $second = $readModel->stateFor(new ActiveClinic('clinic-b', 'Clinica B', 'America/Sao_Paulo', 2));

    expect($client->clinicIds)->toBe(['clinic-a', 'clinic-b']);
    expect($first['clinic']['id'])->toBe('clinic-a');
    expect($second['clinic']['id'])->toBe('clinic-b');
    expect($first['pipeline'][0]['conversation_id'])->toBe('conv-clinic-a');
    expect($second['pipeline'][0]['conversation_id'])->toBe('conv-clinic-b');
});

it('fails closed when fresh projections are missing sync timestamps', function () {
    $client = new class implements \GuzzleHttp\ClientInterface {
        public function send(\Psr\Http\Message\RequestInterface $request, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'sync' => [
                    'sync_status' => 'fresh',
                    'lag_seconds' => 0,
                ],
                'pipeline' => [],
                'timeline' => [],
                'agenda' => [],
                'evidence' => [],
                'exceptions' => [],
            ]));
        }

        public function sendAsync(\Psr\Http\Message\RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function request($method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return $this->send(new \GuzzleHttp\Psr7\Request($method, $uri), $options);
        }

        public function requestAsync($method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function getConfig(?string $option = null): mixed
        {
            return null;
        }
    };

    config([
        'neuroflow.supabase_url' => 'https://example.supabase.co',
        'neuroflow.supabase_service_role_key' => 'test-key',
    ]);

    $state = (new SupabaseRealtimeOperationReadModel($client))->stateFor(new ActiveClinic(
        id: '11111111-1111-4111-8111-111111111111',
        name: 'Clinica Demo',
        timezone: 'America/Sao_Paulo',
        userId: 1,
    ));

    expect($state['sync']['sync_status'])->toBe('failed');
    expect($state['sync']['last_error_code'])->toBe('CRM_PROJECTION_INVALID');
    expect($state['pipeline'])->toBe([]);
});

it('fails closed when projection tenant does not match active clinic', function () {
    $client = new class implements \GuzzleHttp\ClientInterface {
        public function send(\Psr\Http\Message\RequestInterface $request, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'clinic_id' => '55555555-5555-4555-8555-555555555555',
                'sync' => [
                    'sync_status' => 'fresh',
                    'last_success_at' => now()->toISOString(),
                    'lag_seconds' => 0,
                ],
                'pipeline' => [],
                'timeline' => [],
                'agenda' => [],
                'evidence' => [],
                'exceptions' => [],
            ]));
        }

        public function sendAsync(\Psr\Http\Message\RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function request($method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return $this->send(new \GuzzleHttp\Psr7\Request($method, $uri), $options);
        }

        public function requestAsync($method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function getConfig(?string $option = null): mixed
        {
            return null;
        }
    };

    config([
        'neuroflow.supabase_url' => 'https://example.supabase.co',
        'neuroflow.supabase_service_role_key' => 'test-key',
    ]);

    $state = (new SupabaseRealtimeOperationReadModel($client))->stateFor(new ActiveClinic(
        id: '11111111-1111-4111-8111-111111111111',
        name: 'Clinica Demo',
        timezone: 'America/Sao_Paulo',
        userId: 1,
    ));

    expect($state['sync']['sync_status'])->toBe('failed');
    expect($state['sync']['last_error_code'])->toBe('CRM_PROJECTION_INVALID');
});

it('returns only ui safe allowlisted fields from fresh projections', function () {
    $client = new class implements \GuzzleHttp\ClientInterface {
        public function send(\Psr\Http\Message\RequestInterface $request, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'sync' => [
                    'sync_status' => 'fresh',
                    'last_success_at' => now()->toISOString(),
                    'lag_seconds' => 0,
                ],
                'pipeline' => [[
                    'clinic_id' => '11111111-1111-4111-8111-111111111111',
                    'conversation_id' => 'conv-1',
                    'appointment_status' => 'pending_confirmation',
                    'visual_substate' => 'classificando',
                    'raw_payload' => ['secret' => true],
                    'message_body' => 'texto livre',
                ]],
                'timeline' => [],
                'agenda' => [],
                'evidence' => [],
                'exceptions' => [],
            ]));
        }

        public function sendAsync(\Psr\Http\Message\RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function request($method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface
        {
            return $this->send(new \GuzzleHttp\Psr7\Request($method, $uri), $options);
        }

        public function requestAsync($method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
        {
            throw new RuntimeException('Not used.');
        }

        public function getConfig(?string $option = null): mixed
        {
            return null;
        }
    };

    config([
        'neuroflow.supabase_url' => 'https://example.supabase.co',
        'neuroflow.supabase_service_role_key' => 'test-key',
    ]);

    $state = (new SupabaseRealtimeOperationReadModel($client))->stateFor(new ActiveClinic(
        id: '11111111-1111-4111-8111-111111111111',
        name: 'Clinica Demo',
        timezone: 'America/Sao_Paulo',
        userId: 1,
    ));

    expect($state['sync']['sync_status'])->toBe('fresh');
    expect($state['pipeline'][0])->toHaveKeys(['clinic_id', 'conversation_id', 'appointment_status', 'visual_substate']);
    expect($state['pipeline'][0])->not->toHaveKeys(['raw_payload', 'message_body']);
});
