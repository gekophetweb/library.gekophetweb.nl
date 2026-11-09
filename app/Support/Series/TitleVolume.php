<?php

namespace App\Support\Series;

final readonly class TitleVolume
{
    public function __construct(
        public string $title,
        public VolumeNumber $volume,
    ) {}
}
