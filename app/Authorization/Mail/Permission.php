<?php

namespace App\Authorization\Mail;

enum Permission: string
{
    case ConfigureSenders = 'mail.configure-senders';
}
