<?php

namespace budisteikul\vertikaltrip\Models;

use Illuminate\Database\Eloquent\Model;

class ShoppingcartQuestion extends Model
{
    protected $table = 'shoppingcart_questions';
	protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function shoppingcart()
    {
        return $this->belongsTo(Shoppingcart::class);
    }
	
	public function shoppingcart_question_options()
    {
        return $this->hasMany(ShoppingcartQuestionOption::class,'shoppingcart_question_id','id');
    }
}
