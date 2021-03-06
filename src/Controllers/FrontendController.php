<?php

namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use budisteikul\toursdk\Models\Category;
use budisteikul\toursdk\Models\Product;
use budisteikul\toursdk\Models\Review;
use budisteikul\toursdk\Models\Channel;
use budisteikul\toursdk\Models\Shoppingcart;
use budisteikul\toursdk\Models\Page;
use budisteikul\toursdk\Helpers\BokunHelper;
use budisteikul\toursdk\Helpers\BookingHelper;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class FrontendController extends Controller
{
	public function __construct()
    {
		$this->bookingChannelUUID = env("BOKUN_BOOKING_CHANNEL");
		$this->currency = env("BOKUN_CURRENCY");
		$this->lang = env("BOKUN_LANG");
	}

	public function index_vertikaltrip()
	{
		$count = Review::count();
		$categories = Category::where('parent_id',0)->get();
		return view('vertikaltrip::custom.vertikaltrip',['categories'=>$categories,'count'=>$count]);
	}

	public function index_jogjafoodtour()
    {
		$count = Review::count();
        return view('vertikaltrip::custom.jogjafoodtour')->with(['count'=>$count]);
    }

    public function reviews(Request $request)
	{
			$resources = Review::query();
			return Datatables::eloquent($resources)
				->addColumn('style', function ($resource) {
					$rating = $resource->rating;
					switch($rating)
					{
						case '1':
							$star ='<i class="fa fa-star"></i>';	
						break;
						case '2':
							$star ='<i class="fa fa-star"></i><i class="fa fa-star"></i>';	
						break;
						case '3':
							$star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>';	
						break;
						case '4':
							$star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>';	
						break;
						case '5':
							$star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>';	
						break;
						default:
							$star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>';	
					}
					
					
					$title = '<b>'.$resource->title.'</b><br>';
					$date = Carbon::parse($resource->date)->formatLocalized('%b, %Y');
					$user = '<b>'. $resource->user .'</b> <small><span class="text-muted">'.$date.'</span></small><br>';
					$rating = '<span class="text-warning">'. $star .'</span>‎<br>';
					$text =  nl2br($resource->text) .'<br>';
					$post_title = 'Review of : <b>'. Product::findOrFail($resource->product_id)->name.'</b><br>';
					$channel_name = Channel::find($resource->channel_id)->name;

					if($resource->link!="")
					{
						$from = '<a href="'. $resource->link .'" target="_blank" class="text-theme"><b>'.$channel_name.'</b></a>';
					}
					else
					{
						$from = '<b>'.$channel_name.'</b>';
					}

					$output = $user.$post_title.$rating.$title.$text.$from;
					
					return '<div class="bd-callout bd-callout-theme shadow-sm rounded" style="margin-top:5px;margin-bottom:5px;" >'. $output .'</div>';
				})
				->rawColumns(['style'])
				->toJson();
	}

	public function page($slug)
	{
		$page = Page::where('slug',$slug)->firstOrFail();
		return view('vertikaltrip::frontend.page',['page'=>$page]);
	}

	public function booking($slug)
	{
		$sessionId = BookingHelper::shoppingcart_session();

		$product = Product::where('slug',$slug)->firstOrFail();
        $content = BokunHelper::get_product($product->bokun_id);
        
        $pickup = '';
        if($content->meetingType=='PICK_UP' || $content->meetingType=='MEET_ON_LOCATION_OR_PICK_UP')
        {
			$pickup = BokunHelper::get_product_pickup($content->id);
        }

        $availability = BokunHelper::get_availabilityactivity($content->id,1);
		$first = '[{"date":'. $availability[0]->date .',"localizedDate":"'. $availability[0]->localizedDate .'","availabilities":';
		$middle = json_encode($availability);
		$last = '}]';
		$firstavailability = $first.$middle.$last;

		$microtime = $availability[0]->date;
		$month = date("n",$microtime/1000);
		$year = date("Y",$microtime/1000);
		$embedded = "false";

        return view('vertikaltrip::frontend.booking',[
        	'product'=>$product,
        	'content'=>$content,
        	'currency'=>$this->currency,
        	'lang'=>$this->lang,
        	'embedded'=>$embedded,
			'pickup'=>$pickup,
			'sessionId'=>$sessionId,
			'bookingChannelUUID'=>$this->bookingChannelUUID,
			'firstavailability'=>$firstavailability,
			'year'=>$year,
			'month'=>$month
        ]);
	}

	public function receipt($id,$sessionId)
	{
		$shoppingcart = Shoppingcart::where('id',$id)->where('session_id', $sessionId)->where(function($query){
			return $query->where('booking_status', 'CONFIRMED')
                         ->orWhere('booking_status', 'CANCELED');
		})->firstOrFail();
        return view('vertikaltrip::frontend.receipt',['shoppingcart'=>$shoppingcart]);
	}

	public function checkout()
	{
		$sessionId = BookingHelper::shoppingcart_session();
		$shoppingcart = Shoppingcart::where('session_id', $sessionId)
                        ->where('booking_status','CART')->firstOrFail();
        
        if($shoppingcart->shoppingcart_products()->count()==0)
        {
            return redirect('/booking/shoppingcart/empty');
        }

        return view('vertikaltrip::frontend.checkout',['shoppingcart'=>$shoppingcart]);
	}

	public function billing()
	{
		$sessionId = BookingHelper::shoppingcart_session();
		$shoppingcart = Shoppingcart::where('session_id', $sessionId)
                        ->where('booking_status','CART')->firstOrFail();
        
       	
        return view('vertikaltrip::frontend.billing',['shoppingcart'=>$shoppingcart]);
	}

    public function category($slug)
    {
        $category = Category::where('slug',$slug)->firstOrFail();
        return view('vertikaltrip::frontend.category',['category'=>$category]);
    }

    public function product($slug)
    {
    	$sessionId = BookingHelper::shoppingcart_session();

        $product = Product::where('slug',$slug)->firstOrFail();
        $content = BokunHelper::get_product($product->bokun_id);
        
        $pickup = '';
        if($content->meetingType=='PICK_UP' || $content->meetingType=='MEET_ON_LOCATION_OR_PICK_UP')
        {
			$pickup = BokunHelper::get_product_pickup($content->id);
        }

        $availability = BokunHelper::get_availabilityactivity($content->id,1);
		$first = '[{"date":'. $availability[0]->date .',"localizedDate":"'. $availability[0]->localizedDate .'","availabilities":';
		$middle = json_encode($availability);
		$last = '}]';
		$firstavailability = $first.$middle.$last;

		$microtime = $availability[0]->date;
		$month = date("n",$microtime/1000);
		$year = date("Y",$microtime/1000);
		$embedded = "true";

        return view('vertikaltrip::frontend.product',[
        	'product'=>$product,
        	'content'=>$content,
        	'currency'=>$this->currency,
        	'lang'=>$this->lang,
        	'embedded'=>$embedded,
			'pickup'=>$pickup,
			'sessionId'=>$sessionId,
			'bookingChannelUUID'=>$this->bookingChannelUUID,
			'firstavailability'=>$firstavailability,
			'year'=>$year,
			'month'=>$month
        ]);
    }
}
