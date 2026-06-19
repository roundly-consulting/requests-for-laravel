<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Enums;

enum Status: string
{
    case New = 'New';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
}
