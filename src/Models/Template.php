<?php

namespace budisteikul\vertikaltrip\Models;

//use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    //use HasFactory;
    protected $table = 'templates';
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
}

