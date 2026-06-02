<?php

namespace Webkul\NeuroFlow\Domain\Enums;

enum PipelineStage: string
{
    case New = 'novo';
    case Classified = 'classificado';
    case Qualifying = 'qualificando';
    case Qualified = 'qualificado';
    case AppointmentOffered = 'agendamento_oferecido';
    case Booked = 'agendado';
    case Exception = 'excecao';
    case HumanNeeded = 'humano_necessario';
    case Lost = 'perdido';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::Classified => 'Classificado',
            self::Qualifying => 'Qualificando',
            self::Qualified => 'Qualificado',
            self::AppointmentOffered => 'Agendamento oferecido',
            self::Booked => 'Agendado',
            self::Exception => 'Excecao',
            self::HumanNeeded => 'Humano necessario',
            self::Lost => 'Perdido',
        };
    }
}
