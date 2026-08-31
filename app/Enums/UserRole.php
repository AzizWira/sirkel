<?php
namespace App\Enums;
enum UserRole: string
{
    case USER = 'user';
    case PARTNER = 'partner';
    case ADMIN = 'admin';
}
