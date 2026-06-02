<?php

namespace Webkul\NeuroFlow\Application\Presenters;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Webkul\NeuroFlow\Domain\Enums\PipelineStage;
use Webkul\NeuroFlow\Domain\Enums\SyncStatus;

class RealtimeOperationPresenter
{
    public function present(array $state): array
    {
        $syncStatus = (string) data_get($state, 'sync.sync_status', SyncStatus::Failed->value);
        $isFresh = $syncStatus === SyncStatus::Fresh->value;
        $pipelineRows = $isFresh ? ($state['pipeline'] ?? []) : [];
        $timelineRows = $isFresh ? ($state['timeline'] ?? []) : [];

        return [
            'sync' => $this->sync($state),
            'tenant' => $this->tenant($state),
            'pipeline_columns' => $this->pipelineColumns($pipelineRows),
            'lead_summary' => $this->leadSummary($pipelineRows[0] ?? null),
            'sla_card' => $this->slaCard($pipelineRows[0] ?? null),
            'timeline' => $this->timeline($timelineRows),
            'whatsapp_mirror' => $this->whatsappMirror($timelineRows),
            'is_degraded' => ! $isFresh,
            'degraded_message' => $isFresh ? null : $this->degradedMessage($syncStatus),
        ];
    }

    private function sync(array $state): array
    {
        $syncStatus = (string) data_get($state, 'sync.sync_status', SyncStatus::Failed->value);

        return [
            'status' => $syncStatus,
            'label' => match ($syncStatus) {
                SyncStatus::Fresh->value => 'Atualizado',
                SyncStatus::Stale->value => 'Desatualizado',
                SyncStatus::Failed->value => 'Erro de sync',
                SyncStatus::Disabled->value => 'Sync desabilitado',
                default => 'Sync invalido',
            },
            'last_error_code' => data_get($state, 'sync.last_error_code'),
            'lag_seconds' => data_get($state, 'sync.lag_seconds'),
            'updated_label' => $this->timeLabel($state['updated_at'] ?? data_get($state, 'sync.last_success_at')),
            'polling_state' => match ($syncStatus) {
                SyncStatus::Fresh->value => 'polling atualizado',
                SyncStatus::Stale->value => 'polling atrasado',
                SyncStatus::Failed->value => 'polling com erro',
                SyncStatus::Disabled->value => 'polling indisponivel',
                default => 'polling parcial',
            },
        ];
    }

    private function tenant(array $state): array
    {
        return [
            'name' => (string) data_get($state, 'clinic.name', 'Clinica nao resolvida'),
            'timezone' => (string) data_get($state, 'clinic.timezone', 'America/Sao_Paulo'),
            'channel_label' => 'Canal resolvido pelo backend',
        ];
    }

    private function pipelineColumns(array $rows): array
    {
        $columns = [];

        foreach (PipelineStage::cases() as $stage) {
            $columns[$stage->value] = [
                'key' => $stage->value,
                'label' => $stage->label(),
                'items' => [],
            ];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stage = $this->stageFor($row);
            $columns[$stage->value]['items'][] = $this->leadCard($row, $stage);
        }

        return array_values($columns);
    }

    private function leadSummary(?array $row): array
    {
        if (! $row) {
            return [
                'empty' => true,
                'title' => 'Nenhum lead canonico fresco',
                'subtitle' => 'Pipeline vazio ou sync degradado.',
                'stage' => null,
                'next_action' => 'Sem proxima acao persistida',
            ];
        }

        $stage = $this->stageFor($row);

        return [
            'empty' => false,
            'title' => 'Lead '.$this->shortId((string) ($row['lead_id'] ?? $row['contact_id'] ?? 'sem-id')),
            'subtitle' => $this->classificationLabel((string) ($row['classification'] ?? 'unknown')),
            'stage' => $stage->label(),
            'next_action' => $this->safeText($row['next_action'] ?? 'Sem proxima acao persistida'),
            'correlation_id' => $this->shortId((string) ($row['last_correlation_id'] ?? '')),
        ];
    }

    private function leadCard(array $row, PipelineStage $stage): array
    {
        return [
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'title' => 'Lead '.$this->shortId((string) ($row['lead_id'] ?? $row['contact_id'] ?? 'sem-id')),
            'classification' => $this->classificationLabel((string) ($row['classification'] ?? 'unknown')),
            'qualification' => $this->qualificationLabel((string) ($row['qualification_status'] ?? 'not_started')),
            'conversation' => $this->conversationLabel((string) ($row['conversation_status'] ?? 'open')),
            'sla' => $this->slaCard($row),
            'next_action' => $this->safeText($row['next_action'] ?? 'Sem proxima acao'),
            'correlation_id' => $this->shortId((string) ($row['last_correlation_id'] ?? '')),
        ];
    }

    private function slaCard(?array $row): array
    {
        if (! $row) {
            return [
                'status' => 'empty',
                'label' => 'SLA sem conversa',
                'elapsed' => 'Sem medicao',
                'target' => '120s',
                'detail' => 'Nenhuma conversa fresca no read model.',
            ];
        }

        $target = (int) ($row['sla_target_seconds'] ?? 120);
        $elapsed = $row['sla_elapsed_seconds'] ?? null;
        $status = match ((string) ($row['sla_status'] ?? 'not_applicable')) {
            'within_target' => 'ok',
            'at_risk' => 'warning',
            'breached' => 'breached',
            default => 'empty',
        };

        return [
            'status' => $status,
            'label' => match ($status) {
                'ok' => 'SLA ok',
                'warning' => 'SLA em risco',
                'breached' => 'SLA violado',
                default => 'SLA nao aplicavel',
            },
            'elapsed' => $elapsed === null ? 'Sem medicao' : $elapsed.'s',
            'target' => $target.'s',
            'detail' => 'Limite de primeira resposta: '.$target.'s',
        ];
    }

    private function timeline(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'title' => $this->eventLabel((string) ($row['event_type'] ?? 'event')),
                'summary' => $this->safeText($row['summary'] ?? 'Resumo seguro indisponivel'),
                'actor' => $this->actorLabel((string) ($row['actor_type'] ?? 'system')),
                'source' => $this->safeText($row['source_system'] ?? 'sistema'),
                'occurred_at' => $this->timeLabel($row['occurred_at'] ?? null),
                'status' => $this->eventStatus((string) ($row['event_type'] ?? 'event')),
                'correlation_id' => $this->shortId((string) ($row['correlation_id'] ?? '')),
                'evidence_marker' => $this->hasEvidenceMarker($row),
                'external_ref' => $this->safeText($row['masked_external_ref'] ?? ''),
            ];
        }, array_filter($rows, 'is_array')));
    }

    private function whatsappMirror(array $rows): array
    {
        $messages = array_values(array_filter($this->timeline($rows), function (array $event): bool {
            $title = Str::lower($event['title']);

            return Str::contains($title, ['mensagem', 'whatsapp', 'resposta']);
        }));

        return [
            'empty' => count($messages) === 0,
            'messages' => $messages,
        ];
    }

    private function stageFor(array $row): PipelineStage
    {
        $qualification = (string) ($row['qualification_status'] ?? 'not_started');
        $conversation = (string) ($row['conversation_status'] ?? 'open');
        $delivery = (string) ($row['delivery_status'] ?? 'not_applicable');
        $classification = (string) ($row['classification'] ?? 'unknown');
        $nextAction = Str::lower((string) ($row['next_action'] ?? ''));

        if ($delivery === 'exception_recorded' || $qualification === 'blocked' || $conversation === 'escalated') {
            return PipelineStage::Exception;
        }

        if ($qualification === 'needs_human' || Str::contains($nextAction, ['handoff', 'humano'])) {
            return PipelineStage::HumanNeeded;
        }

        if ($conversation === 'booked' || Str::contains($nextAction, ['confirmed', 'agendado'])) {
            return PipelineStage::Booked;
        }

        if (Str::contains($nextAction, ['offer', 'oferecido', 'slot', 'pending_confirmation'])) {
            return PipelineStage::AppointmentOffered;
        }

        if ($conversation === 'closed' && Str::contains($nextAction, ['lost', 'perdido'])) {
            return PipelineStage::Lost;
        }

        if ($qualification === 'complete') {
            return PipelineStage::Qualified;
        }

        if ($qualification === 'in_progress' || in_array($conversation, ['waiting_contact', 'waiting_system'], true)) {
            return PipelineStage::Qualifying;
        }

        if ($classification !== 'unknown') {
            return PipelineStage::Classified;
        }

        return PipelineStage::New;
    }

    private function degradedMessage(string $syncStatus): string
    {
        return match ($syncStatus) {
            SyncStatus::Stale->value => 'Projection atrasada. Leads e eventos ficam ocultos ate novo estado fresco.',
            SyncStatus::Disabled->value => 'Supabase nao configurado para esta superficie.',
            default => 'Projection falhou. A tela nao confirma sucesso operacional.',
        };
    }

    private function classificationLabel(string $value): string
    {
        return match ($value) {
            'new_lead' => 'Lead novo',
            'existing_lead' => 'Lead existente',
            'patient' => 'Responsavel/paciente',
            default => 'Classificacao desconhecida',
        };
    }

    private function qualificationLabel(string $value): string
    {
        return match ($value) {
            'in_progress' => 'Qualificacao em andamento',
            'complete' => 'Qualificacao completa',
            'blocked' => 'Qualificacao bloqueada',
            'needs_human' => 'Precisa de humano',
            default => 'Qualificacao nao iniciada',
        };
    }

    private function conversationLabel(string $value): string
    {
        return match ($value) {
            'waiting_contact' => 'Aguardando responsavel',
            'waiting_system' => 'Aguardando sistema',
            'booked' => 'Agendado',
            'escalated' => 'Escalado',
            'closed' => 'Fechado',
            default => 'Aberto',
        };
    }

    private function actorLabel(string $value): string
    {
        return match ($value) {
            'automation', 'agent', 'n8n' => 'IA / automacao',
            'human', 'operator' => 'Humano',
            'contact', 'lead', 'responsible' => 'Responsavel',
            default => 'Sistema',
        };
    }

    private function eventLabel(string $value): string
    {
        $labels = [
            'message_received' => 'Mensagem recebida',
            'response_sent' => 'Resposta enviada',
            'tenant_resolved' => 'Tenant resolvido',
            'contact_classified' => 'Contato classificado',
            'qualification_updated' => 'Qualificacao atualizada',
            'ssot_answer_retrieved' => 'Evidencia consultada',
            'appointment_offered' => 'Horario oferecido',
            'appointment_booked' => 'Agendamento confirmado',
            'human_escalation_created' => 'Excecao humana aberta',
            'crm_updated' => 'CRM atualizado',
        ];

        return $labels[$value] ?? Str::headline(str_replace('_', ' ', $value));
    }

    private function eventStatus(string $value): string
    {
        if (Str::contains($value, ['failed', 'exception', 'timeout'])) {
            return 'erro';
        }

        if (Str::contains($value, ['requested', 'offered', 'waiting'])) {
            return 'pendente';
        }

        return 'registrado';
    }

    private function hasEvidenceMarker(array $row): bool
    {
        return Str::contains((string) ($row['event_type'] ?? ''), ['ssot', 'evidence'])
            || Str::contains((string) ($row['source_system'] ?? ''), ['ssot', 'rag']);
    }

    private function timeLabel(?string $value): string
    {
        if (! $value) {
            return 'Sem timestamp';
        }

        return Carbon::parse($value)->timezone('America/Sao_Paulo')->format('d/m/Y H:i');
    }

    private function shortId(string $value): string
    {
        if ($value === '') {
            return 'sem trace';
        }

        return strlen($value) <= 12 ? $value : substr($value, 0, 8).'...'.substr($value, -4);
    }

    private function safeText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return Str::limit((string) $value, 120, '...');
    }
}
