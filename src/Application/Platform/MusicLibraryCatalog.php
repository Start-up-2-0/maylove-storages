<?php

declare(strict_types=1);

namespace App\Application\Platform;

final class MusicLibraryCatalog
{
    /** @var list<string> */
    public const TRACK_SLUGS = [
        'acorde-do-coracao',
        'eterno-amor',
        'sussurro-de-amor',
        'festa-do-coracao',
        'momento-especial',
        'brinde-a-vida',
        'serenidade',
        'luz-da-manha',
        'paz-interior',
        'memorias',
        'tempo-bom',
        'saudade-boa',
        'alegria-pura',
        'sol-de-verao',
        'vibe-feliz',
    ];

    public static function relativePath(string $slug): string
    {
        return sprintf('platform/music/%s.mp3', $slug);
    }
}
