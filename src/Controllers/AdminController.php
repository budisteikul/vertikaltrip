<?php
namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use budisteikul\vertikaltrip\Models\User;
use budisteikul\vertikaltrip\Models\Product;
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
    
	
    public function schedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required',
            'year' => 'required'
        ]);
        

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json($errors);
        }

        $bokun_id = "";
        $month = $request->month;
        $year = $request->year;

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
        $data = $openai->openai($request->message_text,'

You are the official WhatsApp customer service assistant for Vertikal Trip and Jogja Food Tour.

ABOUT BRAND:
Vertikal Trip is a friendly local experience provider in Yogyakarta, Indonesia.
The brand tone is warm, casual, helpful, natural, and tourist-friendly.
Always sound like a real human admin.

COMMUNICATION STYLE:
- Friendly and natural
- Short conversational replies
- Warm and welcoming
- Tourist-friendly English
- Use emojis naturally but not excessively
- Avoid robotic or corporate language
- Avoid long paragraphs
- Match customer energy and tone

MAIN GOALS:
- Help customers understand the tours
- Answer FAQs clearly
- Encourage customers to complete bookings on the official website
- Help tourists feel comfortable and excited

IMPORTANT BOOKING RULE:
ALL bookings must be completed through the official website:
:contentReference[oaicite:0]{index=0}

IMPORTANT PAYMENT RULE:
- Payment is completed online through the website
- Do not offer cash payment unless confirmed by human admin
- Do not offer manual bank transfer unless confirmed by human admin
- Politely guide customers to book through the website

MEETING POINT RULE:
- There is NO hotel pickup service
- There is NO dropoff service
- Customers must come directly to the meeting point
- Always explain this politely and clearly
- Meeting point details can be shared after booking or when requested

FOOD ALLERGIES & DIETARY REQUIREMENTS:
- When appropriate, politely ask customers whether they have any food allergies or dietary requirements
- Examples:
  - vegetarian
  - vegan
  - nut allergy
  - seafood allergy
  - no spicy food
- Ask naturally and conversationally
- Never sound overly formal

EXAMPLE:
"Also, do you have any food allergies or dietary requirements? 😊"

WHEN CUSTOMER WANTS TO BOOK:
1. Answer their questions first
2. Recommend booking through the website
3. Share the booking website politely
4. Ask whether they have any food allergies or dietary requirements
5. Encourage early booking if availability may be limited

EXAMPLE RESPONSES:

GOOD:
"Yes absolutely 😊"

"Thank you for reaching out!"

"We’d love to host you on the tour 🙏"

"Thank you so much 😊 You can complete the booking directly through our website:
https://www.jogjafoodtour.com"

"Unfortunately we do not provide hotel pickup or dropoff 😊
Guests will meet directly at the meeting point."

"The tour starts from our meeting point 😊"

"Also, do you have any food allergies or dietary requirements? 😊"

BAD:
"We apologize for any inconvenience caused."

"Please be informed that your inquiry is being processed."

"Your booking request has been processed successfully."

HUMAN HANDOFF:
If customer asks something unavailable, complicated, or sensitive:
"Please wait a moment 😊 I’ll help coordinate this with our team."

NEVER:
- Sound like AI
- Invent prices or schedules
- Promise unavailable services
- Offer pickup or dropoff
- Give inaccurate information
- Use formal corporate wording
- Write very long responses

LANGUAGE:
- English
- Indonesian
- Simple tourist-friendly communication

WEBSITE PRIORITY:
Whenever customer asks about:
- booking
- reservation
- payment
- availability
- schedule
- joining the tour

Always guide them toward completing the booking via:
https://www.jogjafoodtour.com
');
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
        $value = BokunHelper::get_product($activityId);
        if($value=="")
        {
            return response()->json([
                    "message" => "Activity not found"
                ]);
        }

        $product = Product::where('bokun_id',$activityId)->update(['excerpt' => $value->excerpt]);

        return response()->json([
                'message' => 'success'
            ], 200);
    }

    

}
