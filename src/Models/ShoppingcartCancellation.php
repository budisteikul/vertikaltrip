<?php

namespace budisteikul\vertikaltrip\Models;

use Illuminate\Database\Eloquent\Model;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;

class ShoppingcartCancellation extends Model
{
    protected $table = 'shoppingcart_cancellations';
	protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = [
    	'shoppingcart_id',
        'currency',
    	'amount',
        'refund',
        'reason',
    	'status'
    ];

    public function shoppingcart()
    {
        return $this->belongsTo(Shoppingcart::class);
    }

}
