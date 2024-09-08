<?php

namespace budisteikul\vertikaltrip\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'contacts';
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
}
