<?php

namespace App\Enums;

enum CertificateVersionStatus: string
{
    case Ready = 'ready';
    case Superseded = 'superseded';
}
