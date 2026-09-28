<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
