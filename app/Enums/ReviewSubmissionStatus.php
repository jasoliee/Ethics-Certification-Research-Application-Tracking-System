<?php

namespace App\Enums;

enum ReviewSubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
