<?php

namespace App\Enums;

enum ReviewFormArtifactStatus: string
{
    case Ready = 'ready';
    case Superseded = 'superseded';
}
