<?php
namespace budisteikul\vertikaltrip\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

use budisteikul\vertikaltrip\Models\ShoppingcartPayment;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\PaymentHelper;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;
use budisteikul\vertikaltrip\Helpers\PaypalHelper;
use budisteikul\vertikaltrip\Helpers\VoucherHelper;

class CallbackController extends Controller
{
    
    public function __construct()
    {
        
    }
    
    public function confirmpaymentxendit(Request $request)
    {
        $value = $request->header('x-callback-token');
        if(env('XENDIT_CALLBACK_TOKEN')!=$value)
        {
            return response()->json([
                'message' => "ERROR"
            ], 200);
        }

        $data = $request->all();
        
        if(isset($data['external_id']))
        {
            $external_id = $data['external_id'];
            $shoppingcart_payment = ShoppingcartPayment::where('payment_provider','xendit')->where('order_id',$external_id)->first();
            if($shoppingcart_payment){
                $shoppingcart_payment->shoppingcart->booking_status = "CONFIRMED";
                $shoppingcart_payment->shoppingcart->save();  
                $shoppingcart_payment->payment_status = 2;
                //$shoppingcart_payment->authorization_id = $data['data']['id'];
                $shoppingcart_payment->save();

                
                PaymentHelper::save_netPayment($shoppingcart_payment->shoppingcart);
                BookingHelper::shoppingcart_mail($shoppingcart_payment->shoppingcart);
                BookingHelper::shoppingcart_whatsapp($shoppingcart_payment->shoppingcart);
                BookingHelper::shoppingcart_notif($shoppingcart_payment->shoppingcart);
            }

            return response()->json([
                'message' => "success"
            ], 200);
        }

        $event = $data['event'];
        $channel_code = $data['data']['channel_code'];
        $reference_id = $data['data']['reference_id'];

        if($reference_id=="test-payload")
        {
            return response()->json([
                'message' => "TEST OK"
            ], 200);
        }

        if($reference_id=="testing_id_123")
        {
            return response()->json([
                'message' => "TEST OK"
            ], 200);
        }

        

        if($event=="qr.payment")
        {
                $shoppingcart_payment = ShoppingcartPayment::where('payment_provider','xendit')->where('order_id',$reference_id)->first();
                if($shoppingcart_payment){
                    if($data['data']['status']=="SUCCEEDED")
                    {
                        $shoppingcart_payment->shoppingcart->booking_status = "CONFIRMED";
                        $shoppingcart_payment->shoppingcart->save();  
                        $shoppingcart_payment->payment_status = 2;
                        $shoppingcart_payment->authorization_id = $data['data']['id'];
                        $shoppingcart_payment->save();

                        PaymentHelper::save_netPayment($shoppingcart_payment->shoppingcart);
                        
                        BookingHelper::shoppingcart_mail($shoppingcart_payment->shoppingcart);
                        BookingHelper::shoppingcart_whatsapp($shoppingcart_payment->shoppingcart);
                        BookingHelper::shoppingcart_notif($shoppingcart_payment->shoppingcart);
                    }
                }
        }
        

        return response()->json([
                'message' => "success"
            ], 200);
    }

    public function confirmpaymentpaypal(Request $request)
    {
            $validator = Validator::make($request->all(), [
                'orderID' => ['required', 'string', 'max:255'],
                'sessionId' => ['required', 'string', 'max:255'],
            ]);
        
            if ($validator->fails()) {
                $errors = $validator->errors();
                return response()->json($errors);
            }
        
            $orderID = $request->input('orderID');
            $sessionId = $request->input('sessionId');
        
            $shoppingcart = BookingHelper::read_shoppingcart($sessionId);

            $shoppingcart->payment->authorization_id = PaypalHelper::getCaptureId($orderID);;
            $shoppingcart->payment->payment_status = 2;
            $shoppingcart->booking_status = 'CONFIRMED';
            BookingHelper::save_shoppingcart($sessionId,$shoppingcart);
            
            $shoppingcart = BookingHelper::confirm_booking($sessionId);

            PaymentHelper::save_netPayment($shoppingcart);

            return response()->json([
                    "id" => "1",
                    "message" => "/booking/receipt/".$shoppingcart->session_id."/".$shoppingcart->confirmation_code
                ]);
    }

    public function confirmpaymentstripe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sessionId' => ['required', 'string', 'max:255'],
            'authorizationID' => ['required', 'string', 'max:255'],
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json($errors);
        }

        $sessionId = $request->input('sessionId');
        $authorizationID = $request->input('authorizationID');
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);

        if($shoppingcart->payment->authorization_id!=$authorizationID)
        {
            return response()->json([
                    "id" => "2",
                    "message" => 'Error'
                ]);
        }
        
        $shoppingcart->payment->payment_status = 2;
        $shoppingcart->booking_status = 'CONFIRMED';
        BookingHelper::save_shoppingcart($sessionId,$shoppingcart);

        $shoppingcart = BookingHelper::confirm_booking($sessionId);

        PaymentHelper::save_netPayment($shoppingcart);

        return response()->json([
                    "id" => "1",
                    "message" => "/booking/receipt/".$shoppingcart->session_id."/".$shoppingcart->confirmation_code
                ]);
    }

}
