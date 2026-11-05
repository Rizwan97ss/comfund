<?php

namespace App\Enums;

enum SubmissionSource: string
{
    case Member = 'member';
    case AdminManual = 'admin_manual';
}
