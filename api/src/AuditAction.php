<?php

namespace App;

enum AuditAction: int
{
    case USER_LOGIN = 1;
    case DATA_INSERT = 2;
    case DATA_UPDATE = 3;
    case DATA_DELETE = 4;
    case TREE_UPDATE = 5;
}
