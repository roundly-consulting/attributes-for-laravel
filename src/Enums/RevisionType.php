<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Enums;

use RoundlyConsulting\Enums\Helpers;

enum RevisionType: string
{
    use Helpers;

    case Attached = 'attached';
    case Updated = 'updated';
    case Detached = 'detached';
}
