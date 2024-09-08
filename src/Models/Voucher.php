<?php

namespace budisteikul\vertikaltrip\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $table = 'vouchers';
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = ['code','amount','is_percentage'];

    public function products()
    {
        return $this->belongsToMany('budisteikul\vertikaltrip\Models\Product','vouchers_products','voucher_id','product_id')->withPivot('type')->withTimestamps();
    }
}