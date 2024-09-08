<?php

namespace budisteikul\vertikaltrip\Models;
use Illuminate\Database\Eloquent\Model;


class Slug extends Model
{
    protected $table = 'slugs';
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = ['link_id','slug','type'];

}
