<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Enums;

enum RevisionType: string
{
    case Attached = 'attached';
    case Updated = 'updated';
    case Detached = 'detached';
}
