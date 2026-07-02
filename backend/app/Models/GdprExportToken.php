<?php

namespace HiEvents\Models;

class GdprExportToken extends BaseModel
{
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
