<?php

namespace budisteikul\vertikaltrip\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
}
