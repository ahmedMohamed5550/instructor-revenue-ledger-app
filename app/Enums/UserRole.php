<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Admin = 'admin';
}
