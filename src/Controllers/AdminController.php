<?php
namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use budisteikul\vertikaltrip\Models\User;
use budisteikul\vertikaltrip\Models\ShoppingcartProduct;
use budisteikul\vertikaltrip\Models\ShoppingcartProductDetail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;
use Illuminate\Support\Facades\Cache;
use budisteikul\vertikaltrip\Helpers\BokunHelper;
use budisteikul\vertikaltrip\Helpers\OpenAIHelper;
use Illuminate\Support\Facades\Validator;


class AdminController extends Controller
{
    
	
    public function get_schedule()
    {
        $bokun_id = "";
        $month = 7;
        $year = 2025;

        //$data = [];
        

        $a_date = $year ."-".$month."-23";
        $a_date = date("Y-m-t", strtotime($a_date));
        $lastdate = substr($a_date,8,2);

        for($i=01;$i<=$lastdate;$i++)
        {
            $b_date = $year ."-".$month."-". $i;
            $date = date("Y-m-d", strtotime($b_date));

            $total = 0;
            
            

            if($bokun_id!="")
            {
                $products = ShoppingcartProduct::whereHas('shoppingcart', function ($query) {
                    return $query->where('booking_status','CONFIRMED');
                })->where('product_id',$bokun_id)->whereDate('date',$date)->get();
            }
            else
            {
                $products = ShoppingcartProduct::whereHas('shoppingcart', function ($query) {
                    return $query->where('booking_status','CONFIRMED');
                })->whereDate('date',$date)->get();
            }
            

            foreach($products as $product)
            {
                $product_details = ShoppingcartProductDetail::where('shoppingcart_product_id',$product->id)->get();
                foreach($product_details as $product_detail)
                {
                    $total += $product_detail->people;
                }
            }

            $data[] = [
                'full_date' => $date,
                'date' => substr($date,8,2),
                'total' => $total
            ];
            //print_r($date.'<br />');
        }


        
        return response()->json([
                'data' => $data
            ], 200);
        //echo $lastdate;

        /*
        $total = 0;
        $products = ShoppingcartProduct::whereHas('shoppingcart', function ($query) {
            return $query->where('booking_status','CONFIRMED');
        })->where('product_id',$id->bokun_id)->whereDate('date',$date)->get();
        foreach($products as $product)
        {
            $product_details = ShoppingcartProductDetail::where('shoppingcart_product_id',$product->id)->get();
            foreach($product_details as $product_detail)
            {
                $total += $product_detail->people;
            }
        }
        return $total;
        */
    }

    public function __construct()
    {
        
    }
    
    public function createToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        $user = User::where('email', $request->email)->first();
        
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response([
                    'success'   => false,
                    'message' => ['These credentials do not match our records.']
                ], 404);
            }
            
            $token = $user->createToken('VertikalTripToken')->plainTextToken;
        
            $response = [
                'success'   => true,
                'token'     => $token
            ];
        
        return response($response, 201);
    }

    public function openai(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'message_text' => 'required'
        ]);
        

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json($errors);
        }

        $openai = New OpenAIHelper;
        $data = $openai->openai($request->message_text,'Make it in english and polished');
        return response()->json([
                'id' => 1,
                'message_text' => $data
            ], 200);
    }

    public function product_sync(Request $request)
    {
        $request->validate([
            'activityId' => 'required'
        ]);
        $activityId = $request->activityId;
        Cache::forget('_bokunProductById_'. config('site.currency') .'_'. env("BOKUN_LANG") .'_'.$activityId);
        BokunHelper::get_product($activityId);
        return response()->json([
                'message' => 'success'
            ], 200);
    }

    

}
