<?php

namespace App;

enum LogLevel: string
{
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
}
