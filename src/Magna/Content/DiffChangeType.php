<?php

declare(strict_types=1);

namespace Magna\Content;

enum DiffChangeType: string
{
    case CreateTable = 'create_table';
    case AddColumn = 'add_column';
    case RemoveColumn = 'remove_column';
    case ChangeColumn = 'change_column';
}
