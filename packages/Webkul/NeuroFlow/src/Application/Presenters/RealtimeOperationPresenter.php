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
        $scheduleRows = $isFresh ? ($state['agenda'] ?? []) : [];
        $evidenceRows = $isFresh ? ($state['evidence'] ?? []) : [];
        $exceptionRows = $isFresh ? ($state['exceptions'] ?? []) : [];
        $timezone = $this->timezone(data_get($state, 'clinic.timezone'));
        $focusRow = $this->focusRow($pipelineRows);

        return [
            'sync' => $this->sync($state, $timezone),
            'tenant' => $this->tenant($state),
            'pipeline_columns' => $this->pipelineColumns($pipelineRows),
            'lead_summary' => $this->leadSummary($focusRow),
            'sla_card' => $this->slaCard($focusRow),
            'timeline' => $this->timeline($timelineRows, $timezone),
            'whatsapp_mirror' => $this->whatsappMirror($timelineRows, $timezone),
            'schedule' => $this->schedule($scheduleRows, $timezone),
            'evidence' => $this->evidence($evidenceRows, $timezone),
            'exceptions' => $this->exceptions($exceptionRows, $timezone),
            'is_degraded' => ! $isFresh,
            'degraded_message' => $isFresh ? null : $this->degradedMessage($syncStatus),
        ];
    }

    private function sync(array $state, string $timezone): array
    {
        $syncStatus = (string) data_get($state, 'sync.sync_status', SyncStatus::Failed->value);

        return [
            'status' => $syncStatus,
            'label' => match ($syncStatus) {
                SyncStatus::Fresh->value => 'Atualizado',
                SyncStatus::Stale->value => 'Desatualizado',
                SyncStatus::Failed->value => 'Erro de sync',
                SyncStatus::Disabled->value => 'Sync desabilitado',
                'loading' => 'Carregando sync',
                'partial' => 'Sync parcial',
                default => 'Sync invalido',
            },
            'last_error_code' => data_get($state, 'sync.last_error_code'),
            'lag_seconds' => data_get($state, 'sync.lag_seconds'),
            'updated_label' => $this->timeLabel($state['updated_at'] ?? data_get($state, 'sync.last_success_at'), $timezone),
            'polling_state' => match ($syncStatus) {
                SyncStatus::Fresh->value => 'polling atualizado',
                SyncStatus::Stale->value => 'polling atrasado',
                SyncStatus::Failed->value => 'polling com erro',
                SyncStatus::Disabled->value => 'polling indisponivel',
                'loading' => 'polling carregando',
                'partial' => 'polling parcial',
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

    private function focusRow(array $rows): ?array
    {
        $candidates = array_values(array_filter($rows, 'is_array'));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            return $this->focusScore($right) <=> $this->focusScore($left);
        });

        return $candidates[0];
    }

    private function focusScore(array $row): int
    {
        $sla = (string) ($row['sla_status'] ?? 'not_applicable');
        $stage = $this->stageFor($row);

        return match (true) {
            $sla === 'breached' => 100,
            $sla === 'at_risk' => 90,
            $stage === PipelineStage::Exception => 80,
            $stage === PipelineStage::HumanNeeded => 75,
            $stage === PipelineStage::AppointmentOffered => 70,
            $stage === PipelineStage::Booked => 60,
            $stage === PipelineStage::Qualifying => 50,
            $stage === PipelineStage::Qualified => 40,
            $stage === PipelineStage::Classified => 30,
            default => 10,
        };
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

    private function timeline(array $rows, string $timezone): array
    {
        return array_values(array_map(function (array $row) use ($timezone): array {
            return [
                'event_type' => (string) ($row['event_type'] ?? 'event'),
                'title' => $this->eventLabel((string) ($row['event_type'] ?? 'event')),
                'summary' => $this->safeText($row['summary'] ?? 'Resumo seguro indisponivel'),
                'actor' => $this->actorLabel((string) ($row['actor_type'] ?? 'system')),
                'source' => $this->safeText($row['source_system'] ?? 'sistema'),
                'occurred_at' => $this->timeLabel($row['occurred_at'] ?? null, $timezone),
                'status' => $this->eventStatus((string) ($row['event_type'] ?? 'event')),
                'correlation_id' => $this->shortId((string) ($row['correlation_id'] ?? '')),
                'evidence_marker' => $this->hasEvidenceMarker($row),
                'external_ref' => $this->safeText($row['masked_external_ref'] ?? ''),
            ];
        }, array_filter($rows, 'is_array')));
    }

    private function whatsappMirror(array $rows, string $timezone): array
    {
        $messageRows = array_values(array_filter($rows, function ($row): bool {
            if (! is_array($row)) {
                return false;
            }

            $eventType = Str::lower((string) ($row['event_type'] ?? ''));
            $sourceSystem = Str::lower((string) ($row['source_system'] ?? ''));

            return Str::contains($sourceSystem, 'whatsapp')
                || in_array($eventType, ['message_received', 'message_sent', 'response_sent', 'whatsapp_message_received', 'whatsapp_message_sent'], true);
        }));
        $messages = $this->timeline($messageRows, $timezone);

        return [
            'empty' => count($messages) === 0,
            'messages' => $messages,
        ];
    }

    private function schedule(array $rows, string $clinicTimezone): array
    {
        $items = array_values(array_map(function (array $row) use ($clinicTimezone): array {
            $status = $this->scheduleRowStatus($row);
            $timezone = $this->timezone($row['timezone'] ?? $clinicTimezone);
            $startsAt = $this->timeLabel($row['starts_at'] ?? null, $timezone);
            $endsAt = $this->timeLabel($row['ends_at'] ?? null, $timezone);
            $statusMeta = $this->scheduleStatus($status);

            return [
                'component' => 'CompactScheduleSlot',
                'id' => $this->shortId((string) ($row['appointment_id'] ?? $row['slot_id'] ?? 'sem-id')),
                'status' => $statusMeta['status'],
                'status_label' => $statusMeta['label'],
                'severity' => $statusMeta['severity'],
                'confirmation_state' => $statusMeta['confirmation_state'],
                'time_range' => $this->scheduleTimeRange($startsAt, $endsAt),
                'timezone' => $timezone,
                'resource_label' => $this->scheduleResource($row),
                'duration_label' => $this->durationLabel($row['duration_minutes'] ?? null),
                'trace' => $this->shortId((string) ($row['last_correlation_id'] ?? '')),
            ];
        }, array_filter($rows, 'is_array')));

        return [
            'empty' => count($items) === 0,
            'items' => $items,
        ];
    }

    private function evidence(array $rows, string $timezone): array
    {
        $items = array_values(array_map(function (array $row) use ($timezone): array {
            $fallbackUsed = filter_var($row['fallback_used'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $handoffRequired = filter_var($row['handoff_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $sourceStatus = Str::lower(trim((string) ($row['source_status'] ?? '')));
            $approvalState = Str::lower(trim((string) ($row['approval_state'] ?? '')));
            $hasApprovedSource = $sourceStatus === 'active'
                && $approvalState === 'approved'
                && (string) ($row['title'] ?? '') !== '';
            $isFallback = $fallbackUsed || $handoffRequired || ! $hasApprovedSource;
            $fallbackReason = $this->safeText($row['fallback_reason'] ?? 'source_missing');

            return [
                'component' => 'EvidenceBadge',
                'id' => $this->shortId((string) ($row['evidence_id'] ?? $row['source_id'] ?? 'sem-id')),
                'title' => $this->safeText($row['title'] ?? 'Fonte ausente'),
                'status' => $isFallback ? 'warning' : 'ok',
                'status_label' => $isFallback ? 'Fonte ausente ou fallback' : 'Fonte aprovada',
                'source_label' => trim($this->safeText($row['source_type'] ?? 'fonte').' · '.$this->safeText($row['source_status'] ?? 'status ausente'), ' ·'),
                'approval_label' => $this->safeText($row['approval_state'] ?? 'aprovacao ausente'),
                'version_label' => 'Versao '.$this->safeText($row['knowledge_version'] ?? $row['policy_version'] ?? 'indisponivel'),
                'source_date_label' => $this->safeText($row['source_date'] ?? 'sem data'),
                'reference_label' => 'Ref '.$this->safeText($row['cited_reference'] ?? 'ausente'),
                'summary' => $this->safeText($row['safe_summary'] ?? 'Resumo seguro indisponivel'),
                'support_label' => $this->safeText($row['confidence'] ?? 'confidence ausente').' · '.$this->safeText($row['support'] ?? 'support ausente'),
                'policy_label' => $this->safeText($row['policy_version'] ?? 'sem policy').' · '.$this->safeText($row['rule_version'] ?? 'sem rule'),
                'fallback_label' => $isFallback ? 'Fallback: '.$fallbackReason : 'Sem fallback',
                'handoff_label' => $handoffRequired ? 'Handoff humano necessario' : 'Sem handoff',
                'recorded_at' => $this->timeLabel($row['recorded_at'] ?? null, $timezone),
            ];
        }, array_filter($rows, 'is_array')));

        return [
            'empty' => count($items) === 0,
            'items' => $items,
        ];
    }

    private function exceptions(array $rows, string $timezone): array
    {
        $openRows = array_filter($rows, fn ($row): bool => is_array($row) && $this->isOpenException($row));

        $items = array_values(array_map(function (array $row) use ($timezone): array {
            $severity = Str::lower((string) ($row['severity'] ?? 'medium'));
            $reason = (string) ($row['exception_reason'] ?? $row['reason'] ?? 'missing_required_data');

            return [
                'component' => 'HumanExceptionCard',
                'id' => $this->shortId((string) ($row['human_escalation_id'] ?? 'sem-id')),
                'reason' => $reason,
                'reason_label' => $this->exceptionReasonLabel($reason),
                'severity' => $severity,
                'severity_label' => $this->severityLabel($severity),
                'severity_status' => $this->severityStatus($severity),
                'status_label' => $this->exceptionStatusLabel((string) ($row['status'] ?? 'open')),
                'automation_state' => $this->safeText($row['automation_state'] ?? 'estado ausente'),
                'affected_entity' => $this->safeText($row['affected_entity_type'] ?? 'entidade nao informada'),
                'summary' => $this->safeText($row['safe_summary'] ?? 'Resumo seguro indisponivel'),
                'suggested_action' => $this->safeText($row['suggested_action'] ?? 'Sem acao sugerida'),
                'sla_label' => $this->slaImpactLabel((string) ($row['sla_impact'] ?? 'not_applicable')),
                'owner_label' => $this->ownerLabel($row),
                'created_at' => $this->timeLabel($row['created_at'] ?? null, $timezone),
                'claimed_at' => $this->timeLabel($row['claimed_at'] ?? null, $timezone),
                'resolved_at' => $this->timeLabel($row['resolved_at'] ?? null, $timezone),
                'due_at' => $this->timeLabel($row['due_at'] ?? null, $timezone),
                'trace' => $this->shortId((string) ($row['correlation_id'] ?? $row['linked_event_id'] ?? '')),
            ];
        }, $openRows));

        return [
            'empty' => count($items) === 0,
            'items' => $items,
        ];
    }

    private function stageFor(array $row): PipelineStage
    {
        $qualification = Str::lower((string) ($row['qualification_status'] ?? 'not_started'));
        $conversation = Str::lower((string) ($row['conversation_status'] ?? 'open'));
        $delivery = Str::lower((string) ($row['delivery_status'] ?? 'not_applicable'));
        $classification = Str::lower((string) ($row['classification'] ?? 'unknown'));
        $appointment = Str::lower((string) ($row['appointment_status'] ?? ''));
        $substate = Str::lower((string) ($row['visual_substate'] ?? $row['pipeline_substate'] ?? $row['substate'] ?? ''));
        $nextAction = Str::lower((string) ($row['next_action'] ?? ''));

        if (
            $delivery === 'exception_recorded'
            || $qualification === 'blocked'
            || $conversation === 'escalated'
            || $appointment === 'failed_exception'
            || Str::contains($substate, ['excecao', 'exception'])
        ) {
            return PipelineStage::Exception;
        }

        if (
            $qualification === 'needs_human'
            || $appointment === 'reschedule_requested'
            || Str::contains($nextAction, ['handoff', 'humano'])
            || Str::contains($substate, ['humano', 'aguardando_responsavel', 'waiting_responsible'])
        ) {
            return PipelineStage::HumanNeeded;
        }

        if ($appointment === 'confirmed' || $conversation === 'booked' || Str::contains($nextAction, ['confirmed', 'agendado'])) {
            return PipelineStage::Booked;
        }

        if (
            in_array($appointment, ['offered', 'hold', 'pending_confirmation'], true)
            || Str::contains($nextAction, ['offer', 'oferecido', 'slot', 'pending_confirmation'])
        ) {
            return PipelineStage::AppointmentOffered;
        }

        if (
            $appointment === 'cancelled'
            || ($conversation === 'closed' && ($nextAction === '' || Str::contains($nextAction, ['lost', 'perdido', 'no_show', 'sem proxima', 'sem_proxima'])))
            || Str::contains($substate, ['perdido', 'lost'])
        ) {
            return PipelineStage::Lost;
        }

        if ($qualification === 'complete') {
            return PipelineStage::Qualified;
        }

        if (
            $qualification === 'in_progress'
            || in_array($conversation, ['waiting_contact', 'waiting_system'], true)
            || Str::contains($substate, ['classificando', 'qualificando', 'classifying', 'qualifying'])
        ) {
            return PipelineStage::Qualifying;
        }

        if (Str::contains($substate, ['nutricao', 'nurture'])) {
            return PipelineStage::Qualified;
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

    private function scheduleStatus(string $value): array
    {
        return match ($value) {
            'offered' => [
                'status' => 'offered',
                'label' => 'Oferecido',
                'severity' => 'empty',
                'confirmation_state' => 'horario oferecido, nao confirmado',
            ],
            'pending_confirmation' => [
                'status' => 'pending_confirmation',
                'label' => 'Pendente',
                'severity' => 'warning',
                'confirmation_state' => 'reserva pendente de resposta',
            ],
            'confirmed' => [
                'status' => 'confirmed',
                'label' => 'Confirmado',
                'severity' => 'ok',
                'confirmation_state' => 'confirmado por projection fresca',
            ],
            'reschedule_requested' => [
                'status' => 'reschedule_requested',
                'label' => 'Remarcacao solicitada',
                'severity' => 'warning',
                'confirmation_state' => 'precisa de acao humana',
            ],
            'cancelled' => [
                'status' => 'cancelled',
                'label' => 'Cancelado',
                'severity' => 'empty',
                'confirmation_state' => 'slot nao confirmado',
            ],
            default => [
                'status' => 'failed_exception',
                'label' => 'Falha ou conflito',
                'severity' => 'breached',
                'confirmation_state' => 'resultado tecnico ou excecao',
            ],
        };
    }

    private function scheduleRowStatus(array $row): string
    {
        $rowSyncStatus = Str::lower(trim((string) ($row['sync_status'] ?? SyncStatus::Fresh->value)));

        if ($rowSyncStatus !== SyncStatus::Fresh->value) {
            return 'failed_exception';
        }

        return Str::lower(trim((string) ($row['appointment_status'] ?? 'failed_exception')));
    }

    private function scheduleTimeRange(string $startsAt, string $endsAt): string
    {
        if ($startsAt === 'Sem timestamp') {
            return 'Sem timestamp';
        }

        if ($endsAt === 'Sem timestamp') {
            return $startsAt;
        }

        return $startsAt.' - '.Str::after($endsAt, ' ');
    }

    private function scheduleResource(array $row): string
    {
        $room = $this->safeText($row['room_id'] ?? 'sala ausente');
        $professional = $this->safeText($row['professional_id'] ?? 'profissional ausente');

        return 'Sala '.$room.' · Profissional '.$professional;
    }

    private function durationLabel(mixed $value): string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return 'Duracao nao informada';
        }

        return (int) $value.' min';
    }

    private function exceptionReasonLabel(string $value): string
    {
        return match ($value) {
            'unknown_tenant' => 'Tenant desconhecido',
            'duplicate_or_ambiguous_tenant' => 'Tenant ambiguo',
            'missing_required_data' => 'Dados obrigatorios ausentes',
            'out_of_scope' => 'Fora de escopo',
            'low_confidence' => 'Baixa confianca',
            'source_missing' => 'Fonte ausente',
            'clinical_limit' => 'Limite clinico',
            'schedule_unavailable' => 'Agenda indisponivel',
            'booking_conflict' => 'Conflito de agenda',
            'delivery_failed' => 'Falha de entrega',
            'crm_sync_failed' => 'Falha de sync CRM',
            'workflow_timeout' => 'Timeout de workflow',
            'lgpd_risk' => 'Risco LGPD',
            'opt_out' => 'Opt-out',
            'ambiguous_intent' => 'Intencao ambigua',
            'policy_blocked' => 'Bloqueio de politica',
            'contact_conflict' => 'Conflito de contato',
            'technical_failure' => 'Falha tecnica',
            default => Str::headline(str_replace('_', ' ', $value)),
        };
    }

    private function severityLabel(string $value): string
    {
        return match ($value) {
            'critical' => 'Critica',
            'high' => 'Alta',
            'low' => 'Baixa',
            default => 'Media',
        };
    }

    private function severityStatus(string $value): string
    {
        return match ($value) {
            'critical', 'high' => 'breached',
            'low' => 'empty',
            default => 'warning',
        };
    }

    private function exceptionStatusLabel(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'assigned' => 'Atribuida',
            'in_progress' => 'Em andamento',
            'resolved' => 'Resolvida',
            'cancelled' => 'Cancelada',
            default => 'Aberta',
        };
    }

    private function isOpenException(array $row): bool
    {
        return in_array(Str::lower(trim((string) ($row['status'] ?? 'open'))), ['open', 'assigned', 'in_progress'], true);
    }

    private function slaImpactLabel(string $value): string
    {
        return match ($value) {
            'breached' => 'SLA violado',
            'at_risk' => 'SLA em risco',
            'within_target' => 'SLA ok',
            default => 'SLA nao aplicavel',
        };
    }

    private function ownerLabel(array $row): string
    {
        $role = $this->safeText($row['owner_role'] ?? 'sem dono');
        $user = $this->safeText($row['owner_user_id'] ?? '');

        return $user === '' ? 'Responsavel: '.$role : 'Responsavel: '.$role.' · '.$user;
    }

    private function hasEvidenceMarker(array $row): bool
    {
        return Str::contains((string) ($row['event_type'] ?? ''), ['ssot', 'evidence'])
            || Str::contains((string) ($row['source_system'] ?? ''), ['ssot', 'rag']);
    }

    private function timeLabel(mixed $value, string $timezone): string
    {
        if (! is_scalar($value) || (string) $value === '') {
            return 'Sem timestamp';
        }

        try {
            return Carbon::parse((string) $value)->timezone($timezone)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return 'Sem timestamp';
        }
    }

    private function timezone(mixed $value): string
    {
        if (! is_scalar($value) || (string) $value === '') {
            return 'America/Sao_Paulo';
        }

        try {
            Carbon::now((string) $value);

            return (string) $value;
        } catch (\Throwable) {
            return 'America/Sao_Paulo';
        }
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
