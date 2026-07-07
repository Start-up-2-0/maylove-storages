<?php

declare(strict_types=1);

namespace App\Domain\File;

enum FileStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Deleted = 'deleted';
}
