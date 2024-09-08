<?php

namespace budisteikul\vertikaltrip\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $table = 'channels';
	protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = ['name','description','invoice'];

    public function reviews()
    {
        return $this->hasMany(Review::class,'channel_id');
    }
}
