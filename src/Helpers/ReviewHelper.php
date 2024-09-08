<?php
namespace budisteikul\vertikaltrip\Helpers;
use budisteikul\vertikaltrip\Models\Review;
class ReviewHelper {

	public static function review_count($product_id=null)
    {
    	if($product_id==null)
    	{
    		$count = Review::count();
    	}
    	else
    	{
    		$count = Review::where('product_id',$product_id)->count();
    	}
        
        return $count;
    }

	

    public static function review_rate($product_id=null)
    {
    	if($product_id==null)
    	{
    		$rating = Review::sum('rating');
        	$count = self::review_count();
    	}
    	else
    	{
    		$rating = Review::where('product_id',$product_id)->sum('rating');
        	$count = self::review_count($product_id);
    	}
        
        if($count==0) $count = 1;

        $rate = $rating/$count;
        if ( strpos( $rate, "." ) !== false ) {
            $rate = number_format((float)$rate, 2, '.', '');
        }

        $data = [
        	'rate' => round($rate, 2),
            'count' => $count,
        	'star' => self::star($rate)
        ];
        return $data;
    }

    public static function star($rating){
    	if($rating>=4.5)
    	{
    		$star = '<i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i>';

    	}
    	else if($rating>=3.5)
    	{
    		$star ='<i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-muted"></i>';
    	}
    	else if($rating>=2.5)
    	{
    		$star ='<i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i>';
    	}
    	else if($rating>=1.5)
    	{
    		$star ='<i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i>';
    	}
    	else if($rating>=0.5)
    	{
    		$star ='<i class="fa fa-star text-warning"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i>';
    	}
    	else
    	{
    		$star ='';
    	}
    	return $star;
    }

	

	public static function product_have_review($product){
        $status = false;
        if($product->reviews()->exists()) $status = true;
        return $status;
    }

    public static function channel_have_review($channel){
        $status = false;
        if($channel->reviews()->exists()) $status = true;
        return $status;
    }
}