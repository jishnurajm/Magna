<?php

declare(strict_types=1);

namespace Magna\Settings;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
