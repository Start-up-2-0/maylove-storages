<?php

declare(strict_types=1);

namespace App\Domain\File;

enum FileVisibility: string
{
    case Private = 'private';
    case Public = 'public';
}
