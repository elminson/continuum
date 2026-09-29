<?php

namespace App\Enums;

enum IngestStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
