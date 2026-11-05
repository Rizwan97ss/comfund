<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Pending = 'pending';
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
}
