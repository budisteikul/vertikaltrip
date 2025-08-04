<?php
namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\PaymentHelper;
use budisteikul\vertikaltrip\Helpers\XenditHelper;
use budisteikul\vertikaltrip\Helpers\BokunHelper;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;
use budisteikul\vertikaltrip\Models\Shoppingcart;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;

class PaymentController extends Controller
{
    
	
    public function __construct()
    {
        
    }
    
    public function change($sessionId,$confirmationCode)
    {
            $shoppingcart = Shoppingcart::where('session_id',$sessionId)->where('confirmation_code',$confirmationCode)->where('booking_status','PENDING')->first();
            if($shoppingcart)
            {

                //==========================================================================
                //clear cart
                //FirebaseHelper::delete('receipt/'.$shoppingcart->session_id);
                $old_shoppingcart = FirebaseHelper::read('shoppingcart/'.$shoppingcart->session_id);
                foreach($old_shoppingcart->shoppingcarts[0]->products as $old_product)
                {
                    BokunHelper::get_removeactivity($shoppingcart->session_id,$old_product->booking_id);
                }
                
                //==========================================================================
                
                foreach($shoppingcart->shoppingcart_products as $shoppingcart_product)
                {
                    $data = [];
                    foreach($shoppingcart_product->shoppingcart_product_details as $shoppingcart_product_detail)
                    {
                        
                        $data_1 = [];
                        for($i=1;$i<=$shoppingcart_product_detail->qty;$i++)
                        {

                            $data_1[] = [
                                "pricingCategoryId" => $shoppingcart_product_detail->pricing_id,
                                "quantity" => 1
                            ];
                        }
                        
                    }

                    $data = [
                        "activityId" => $shoppingcart_product->product_id,
                        "date" => substr($shoppingcart_product->date,0,10),
                        "startTimeId" => $shoppingcart_product->start_time_id,
                        "rateId" => $shoppingcart_product->rate_id,
                        "pricingCategoryBookings" => $data_1
                    ];
                    
                    
                    //print_r($data);
                    BokunHelper::get_addshoppingcart($shoppingcart->session_id,$data);
                }

                
                //==========================================================================
                
                $url = $shoppingcart->url;
                
                $shoppingcart_json = BookingHelper::shoppingcart_dbtojson($shoppingcart->id);
                $shoppingcart_json = BookingHelper::save_shoppingcart($shoppingcart->session_id,$shoppingcart_json);

                //$shoppingcart->booking_status = 'CANCELED';
                //$shoppingcart->save();
                //$shoppingcart->shoppingcart_payment->payment_status = 3;
                //$shoppingcart->shoppingcart_payment->save();


                $shoppingcart->delete();

                //return redirect()->away($url.'/booking/checkout');
                
            }
            
            return response()->json([
                'message' => "success"
            ], 200);
            
            //return redirect()->away(env('APP_URL').'/booking/checkout');
            
    }

    public function checkout(Request $request)
    {
            $validator = Validator::make(json_decode($request->getContent(), true), [
                'sessionId' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();
                return response()->json($errors);
            }
            
            $data = json_decode($request->getContent(), true);

            $sessionId = $data['sessionId'];
            $trackingCode = $data['trackingCode'];
            
            $check_question = BookingHelper::check_question_json($sessionId,$data);
            if(count($check_question) > 0)
            {
                $check_question['message'] = '<span style="font-size:16px">Oops there was a problem, please check your input and try again.</span>';
                return response()->json($check_question);
            }

            $shoppingcart = BookingHelper::save_question_json($sessionId,$data);
            $shoppingcart = BookingHelper::save_trackingCode($sessionId,$trackingCode);


            FirebaseHelper::shoppingcart($sessionId);
            
            $payment = $data['payment'];

            switch($payment)
            {
                case 'paypal':
                    return response()->json([
                        'message' => 'success',
                        'payment' => 'paypal',
                        'id' => 3
                    ], 200);
                break;

                case 'stripe':
                    return response()->json([
                        'message' => 'success',
                        'payment' => 'stripe',
                        'id' => 3
                    ]);
                break;

                case 'xendit':
                    return response()->json([
                        'message' => 'success',
                        'payment' => 'xendit',
                        'id' => 3
                    ]);
                break;

                case 'qris':
                    return response()->json([
                        'message' => 'success',
                        'payment' => 'qris'
                    ]);
                break;

                case 'wise':
                    return response()->json([
                        'message' => 'success',
                        'payment' => 'wise'
                    ]);
                break;

                case 'bss':
                    return response()->json([
                        'message' => 'success',
                        'payment' => 'bss'
                    ]);
                break;

                default:
                    return response()->json([
                        'id' => "0",
                        'message' => "Oops there was a problem",
                    ]);
            }
            
    }
    
    public function createpaymentpaypal(Request $request)
    {
            $sessionId = $request->header('sessionId');
            BookingHelper::set_confirmationCode($sessionId);
            $response = PaymentHelper::create_payment($sessionId,"paypal");
            return response()->json($response->data);
    }

    public function createpaymentstripe(Request $request)
    {
            $sessionId = $request->header('sessionId');
            BookingHelper::set_confirmationCode($sessionId);
            $response = PaymentHelper::create_payment($sessionId,"stripe");
            return response()->json($response->data);
    }

    

    public function createpaymentxendit(Request $request)
    {
            $sessionId = $request->header('sessionId');
            $tokenId = $request->header('tokenId');

            BookingHelper::set_confirmationCode($sessionId);
            $response = PaymentHelper::create_payment($sessionId,"xendit","card",$tokenId);
            
            if($response->status->id==1)
            {
                $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
                $shoppingcart->booking_status = 'CONFIRMED';
                BookingHelper::save_shoppingcart($sessionId,$shoppingcart);
                $shoppingcart = BookingHelper::confirm_booking($sessionId);
                PaymentHelper::save_netPayment($shoppingcart);
                return response()->json([
                    "id" => "1",
                    "message" => "/booking/receipt/".$shoppingcart->session_id."/".$shoppingcart->confirmation_code
                ]);
            }
            else
            {
                return response()->json([
                    "id" => "0",
                    "message" => $response->status->message
                ]);
            }
            
    }


    public function wise_jscript($sessionId)
    {

        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
       
        $shoppingcart->booking_status = 'PENDING';

        BookingHelper::save_shoppingcart($sessionId,$shoppingcart);
        BookingHelper::set_confirmationCode($sessionId);
        
        $response = PaymentHelper::create_payment($sessionId,"wise");

        if($response->status->id=="1")
        {
            $shoppingcart = BookingHelper::confirm_booking($sessionId);
            $session_id = $shoppingcart->session_id;
            $confirmation_code = $shoppingcart->confirmation_code;
            $redirect = '/booking/receipt/'.$session_id.'/'.$confirmation_code;
            $jscript = '
                
                afterCheckout("'.$redirect.'");
            ';
            return response($jscript)->header('Content-Type', 'application/javascript');
        }
        else
        {
            $jscript = '
                $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><strong style="margin-bottom:10px; margin-top:10px; font-size:16px;"><i class="far fa-frown"></i> Oops there was a problem</strong></div>\');
                $(\'#alert-payment\').fadeIn("slow");
                setTimeout(function (){
                    changePaymentMethod();
                }, 1500);
            ';
            return response($jscript)->header('Content-Type', 'application/javascript');
        }
    }

    public function qris_jscript($sessionId)
    {
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $shoppingcart->booking_status = 'PENDING';
        BookingHelper::save_shoppingcart($sessionId,$shoppingcart);
        BookingHelper::set_confirmationCode($sessionId);
        
        $response = PaymentHelper::create_payment($sessionId,"xendit","qris");

        if($response->status->id=="1")
        {
            $shoppingcart = BookingHelper::confirm_booking($sessionId);
            $session_id = $shoppingcart->session_id;
            $confirmation_code = $shoppingcart->confirmation_code;
            $redirect = '/booking/receipt/'.$session_id.'/'.$confirmation_code;
            $jscript = '
                afterCheckout("'.$redirect.'");
            ';
            return response($jscript)->header('Content-Type', 'application/javascript');
        }
        else
        {
            $jscript = '
                $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><strong style="margin-bottom:10px; margin-top:10px; font-size:16px;"><i class="far fa-frown"></i> Oops there was a problem</strong></div>\');
                $(\'#alert-payment\').fadeIn("slow");
                setTimeout(function (){
                    changePaymentMethod();
                }, 1500);
            ';
            return response($jscript)->header('Content-Type', 'application/javascript');
        }
    }

    

    public function xendit_jscript($sessionId)
    {
        if(config('site.xendit_form')=="v2")
        {
            $jscript = XenditHelper::createFormV2($sessionId);
        }
        else if(config('site.xendit_form')=="v3")
        {
            $jscript = XenditHelper::createFormV3($sessionId);
        }
        else
        {
            $jscript = XenditHelper::createForm($sessionId);
        }
        return response($jscript)->header('Content-Type', 'application/javascript');
    }

    

    

    public function stripe_jscript($sessionId)
    {
        
        
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,'USD');
        $amount = $amount * 100;

        $payment_container = '<hr />
        <form id="payment-form">
            <div id="stripe-wallet" class="pt-2 pb-2 justify-content-center">
                <h2>Pay with</h2>
                <div id="payment-request-button"></div>
                <div class="mt-2 mb-2" style="width: 100%; height: 12px; border-bottom: 1px solid #D0D0D0; text-align: center">
                    <span style="color: #D0D0D0; font-size: 12px; background-color: #FFFFFF; padding: 0 10px;">or pay with card</span>
                </div>
            </div>
            <div class="form-control mt-2 mb-2" style="height:47px;" id="card-element"></div>
            <div id="card-errors" role="alert"></div>
            <button style="height:47px;" class="btn btn-lg btn-block btn-theme" id="submit">
                <i class="fas fa-lock"></i> <strong>Pay with card</strong>
            </button>
            <div id="change_payment" class="mt-2">
                <center><small><a href="#paymentMethod" class="text-theme" onClick="changePaymentMethod();">Click here</a> to change payment method</small>
                </center>
            </div>
        </form>

        <div id="loader" class="mb-4"></div>
        <div id="text-alert" class="text-center"></div>
        ';
        

        $jscript = '
        
            $("#submitCheckout").slideUp("slow");
            $("#paymentContainer").html(\''.str_replace(array("\r", "\n"), '', $payment_container).'\');

                 var stripe = Stripe(\''. env("STRIPE_PUBLISHABLE_KEY") .'\', {
                    apiVersion: "2020-08-27",
                 });

                 var paymentRequest = stripe.paymentRequest({
                    country: \'US\',
                    currency: \'usd\',
                    total: {
                        label: \''. env('APP_NAME') .'\',
                        amount: '. $amount .',
                    },
                    requestPayerName: true,
                    requestPayerEmail: true,
                 });
                 
                 var elements = stripe.elements();

                 var prButton = elements.create(\'paymentRequestButton\', {
                    paymentRequest: paymentRequest,
                 });

                 paymentRequest.canMakePayment().then(function(result) {
                    if (result) {
                        prButton.mount(\'#payment-request-button\');
                    } else {
                        document.getElementById(\'stripe-wallet\').style.display = \'none\';
                    }
                 });
                 
                 var style = {
                    base: {
                        color: "#32325d",
                        fontSize: "16px",
                        lineHeight: "34px"
                    }
                 };

                 var card = elements.create("card", { style: style });
                 card.mount("#card-element");

                var form = document.getElementById(\'payment-form\');
                form.addEventListener(\'submit\', function(ev) {
                   
                    ev.preventDefault();

                    $("#alert-payment").slideUp("slow");
                    $("#submit").attr("disabled", true);
                    $("#submit").html(\' <i class="fa fa-spinner fa-spin fa-fw"></i>  processing... \');

                    $.ajax({
                    beforeSend: function(request) {
                        request.setRequestHeader(\'sessionId\', \''. $shoppingcart->session_id .'\');
                    },
                    type: \'POST\',
                    url: \''. env('APP_API_URL') .'/payment/stripe\'
                }).done(function( data ) {
                    
                    $("#loader").show();
                    $("#payment-form").slideUp("slow");  
                    $("#proses").hide();
                    $("#loader").addClass("loader");
                    $("#text-alert").show();
                    $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );

                    stripe.confirmCardPayment(data.intent.client_secret, {
                        payment_method: {
                            card: card
                        }
                    }).then(function(result) {

                        if (result.error) {

                            $("#text-alert").hide();
                            $("#text-alert").empty();
                            $("#loader").hide();
                            $("#loader").removeClass("loader");
                            $("#payment-form").slideDown("slow");
                            $("#submit").attr("disabled", false);
                            $("#submit").html(\'<i class="fas fa-lock"></i> <strong>Pay with card</strong>\');
                            $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ result.error.message +\'</h2></div>\');
                            $(\'#alert-payment\').fadeIn("slow");

                        } else {
                            
                            if (result.paymentIntent.status === \'succeeded\' || result.paymentIntent.status === \'requires_capture\') {
                                
                                $.ajax({
                                data: {
                                    "authorizationID": result.paymentIntent.id,
                                    "sessionId": \''.$sessionId.'\',
                                },
                                type: \'POST\',
                                url: \''. url('/api') .'/payment/stripe/confirm\'
                                }).done(function(data) {
                                if(data.id=="1")
                                {
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $(\'#alert-payment\').html(\'<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                    setTimeout(function (){
                                        afterCheckout(data.message);
                                    }, 1000); 
                                }

                                }).fail(function(error) {
                                    
                                });

                            }
                        }
                    });
                });


                });



                paymentRequest.on(\'paymentmethod\', async(e) => {

                    const {intent} = await fetch("'. env('APP_API_URL') .'/payment/stripe", {
                        method: "POST",
                        credentials: \'same-origin\',
                        headers: {
                            "sessionId" : "'. $shoppingcart->session_id .'",
                        },
                    }).then(r => r.json());
                    
                    const {error,paymentIntent} = await stripe.confirmCardPayment(intent.client_secret,{
                            payment_method: e.paymentMethod.id
                        }, {handleActions:false});
                    
                    $("#payment-form").slideUp("slow");  
                    $("#proses").hide();
                    $("#loader").addClass("loader");
                    $("#text-alert").show();
                    $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );

                    if(error) {
                        e.complete("fail");
                        $("#text-alert").hide();
                        $("#text-alert").empty();
                        $("#loader").hide();
                        $("#loader").removeClass("loader");
                        $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Failed</h2></div>\');
                        $(\'#alert-payment\').fadeIn("slow");
                    }

                    e.complete("success");

                    if(paymentIntent.status == "requires_action")
                    {
                        stripe.confirmCardPayment(intent.client_secret).then(function(result){
                            if(result.error)
                            {
                                // failed
                                e.complete("fail");
                                $("#text-alert").hide();
                                $("#text-alert").empty();
                                $("#loader").hide();
                                $("#loader").removeClass("loader");
                                $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Failed</h2></div>\');
                                $(\'#alert-payment\').fadeIn("slow");
                            }
                            else
                            {
                                // success
                                $.ajax({
                                data: {
                                    "authorizationID": paymentIntent.id,
                                    "sessionId": \''.$sessionId.'\',
                                },
                                type: \'POST\',
                                url: \''. url('/api') .'/payment/stripe/confirm\'
                                }).done(function(data) {
                                if(data.id=="1")
                                {
                                    e.complete("success");
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $(\'#alert-payment\').html(\'<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                    setTimeout(function (){
                                        afterCheckout(data.message);
                                    }, 1000); 
                                }

                                }).fail(function(error) {
                                    
                                });
                            }
                        });
                        
                    } else {
                                // success
                                $.ajax({
                                data: {
                                    "authorizationID": paymentIntent.id,
                                    "sessionId": \''.$sessionId.'\',
                                },
                                type: \'POST\',
                                url: \''. url('/api') .'/payment/stripe/confirm\'
                                }).done(function(data) {
                                if(data.id=="1")
                                {
                                    e.complete("success");
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $(\'#alert-payment\').html(\'<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                    setTimeout(function (){
                                        afterCheckout(data.message);
                                    }, 1000); 
                                }

                                }).fail(function(error) {
                                    
                                });
                    }

                });
            

        ';
        return response($jscript)->header('Content-Type', 'application/javascript');
    }

    public function wa_jscript($sessionId)
    {
        
        $next_availability = BookingHelper::next_availability($data_flow->bokun_id,30);
        $availability_participant = 0;
        foreach($next_availability as $x)
        {
            if($x->date==$data_flow->date)
            {
                $availability_participant = $x->max_participant - $x->booking;
            }
                        
        }
        if($data_flow->participant>$availability_participant)
        {
            BookingHelper::shoppingcart_clear($sessionId);
            $jscript = 'window.openAppRoute("/payment/page/payment-expired")';
            return response($jscript)->header('Content-Type', 'application/javascript');
        }
        
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,'USD');
        $amount = $amount * 100;

        $payment_container = '<hr />
        <form id="payment-form">
            <div id="stripe-wallet" class="pt-2 pb-2 justify-content-center">
                <h2>Pay with</h2>
                <div id="payment-request-button"></div>
                <div class="mt-2 mb-2" style="width: 100%; height: 12px; border-bottom: 1px solid #D0D0D0; text-align: center">
                    <span style="color: #D0D0D0; font-size: 12px; background-color: #FFFFFF; padding: 0 10px;">or pay with card</span>
                </div>
            </div>
            <div class="form-control mt-2 mb-2 ml-0 mr-0" style="height:47px;" id="card-element"></div>
            <div id="card-errors" role="alert"></div>
            
            <button style="height:47px;" class="btn btn-lg btn-block btn-theme" id="submit">
                <i class="fas fa-lock"></i> <strong>Pay with card</strong>
            </button>
            
        </form>

        <div id="loader" class="mb-4"></div>
        <div id="text-alert" class="text-center"></div>
        ';
        

        $jscript = '
        
            $("#submitCheckout").slideUp("slow");
            $("#paymentContainer").html(\''.str_replace(array("\r", "\n"), '', $payment_container).'\');

                 var stripe = Stripe(\''. env("STRIPE_PUBLISHABLE_KEY") .'\', {
                    apiVersion: "2020-08-27",
                 });

                 var paymentRequest = stripe.paymentRequest({
                    country: \'US\',
                    currency: \'usd\',
                    total: {
                        label: \''. env('APP_NAME') .'\',
                        amount: '. $amount .',
                    },
                    requestPayerName: true,
                    requestPayerEmail: true,
                 });
                 
                 var elements = stripe.elements();

                 var prButton = elements.create(\'paymentRequestButton\', {
                    paymentRequest: paymentRequest,
                 });

                 paymentRequest.canMakePayment().then(function(result) {
                    if (result) {
                        prButton.mount(\'#payment-request-button\');
                    } else {
                        document.getElementById(\'stripe-wallet\').style.display = \'none\';
                    }
                 });
                 
                 var style = {
                    base: {
                        color: "#32325d",
                        fontSize: "18px",
                        lineHeight: "36px"
                    }
                 };

                 var card = elements.create("card", { style: style });
                 card.mount("#card-element");

                var form = document.getElementById(\'payment-form\');
                form.addEventListener(\'submit\', function(ev) {
                   
                    ev.preventDefault();

                    $("#alert-payment").slideUp("slow");
                    $("#submit").attr("disabled", true);
                    $("#submit").html(\' <i class="fa fa-spinner fa-spin fa-fw"></i>  processing... \');

                    $.ajax({
                    beforeSend: function(request) {
                        request.setRequestHeader(\'sessionId\', \''. $shoppingcart->session_id .'\');
                    },
                    type: \'POST\',
                    url: \''. env('APP_API_URL') .'/payment/stripe\'
                }).done(function( data ) {
                    
                    $("#loader").show();
                    $("#payment-form").slideUp("slow");  
                    $("#proses").hide();
                    $("#loader").addClass("loader");
                    $("#text-alert").show();
                    $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );

                    stripe.confirmCardPayment(data.intent.client_secret, {
                        payment_method: {
                            card: card
                        }
                    }).then(function(result) {

                        if (result.error) {

                            $("#text-alert").hide();
                            $("#text-alert").empty();
                            $("#loader").hide();
                            $("#loader").removeClass("loader");
                            $("#payment-form").slideDown("slow");
                            $("#submit").attr("disabled", false);
                            $("#submit").html(\'<i class="fas fa-lock"></i> <strong>Pay with card</strong>\');
                            $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ result.error.message +\'</h2></div>\');
                            $(\'#alert-payment\').fadeIn("slow");

                        } else {
                            
                            if (result.paymentIntent.status === \'succeeded\' || result.paymentIntent.status === \'requires_capture\') {
                                
                                $.ajax({
                                data: {
                                    "authorizationID": result.paymentIntent.id,
                                    "sessionId": \''.$sessionId.'\',
                                },
                                type: \'POST\',
                                url: \''. url('/api') .'/payment/stripe/confirm\'
                                }).done(function(data) {
                                if(data.id=="1")
                                {
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $(\'#alert-payment\').html(\'<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                    setTimeout(function (){
                                        window.openAppRoute("/payment/page/payment-successful");
                                    }, 1000); 
                                }

                                }).fail(function(error) {
                                    
                                });

                            }
                        }
                    });
                });


                });



                paymentRequest.on(\'paymentmethod\', async(e) => {

                    const {intent} = await fetch("'. env('APP_API_URL') .'/payment/stripe", {
                        method: "POST",
                        credentials: \'same-origin\',
                        headers: {
                            "sessionId" : "'. $shoppingcart->session_id .'",
                        },
                    }).then(r => r.json());
                    
                    const {error,paymentIntent} = await stripe.confirmCardPayment(intent.client_secret,{
                            payment_method: e.paymentMethod.id
                        }, {handleActions:false});
                    
                    $("#payment-form").slideUp("slow");  
                    $("#proses").hide();
                    $("#loader").addClass("loader");
                    $("#text-alert").show();
                    $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );

                    if(error) {
                        e.complete("fail");
                        $("#text-alert").hide();
                        $("#text-alert").empty();
                        $("#loader").hide();
                        $("#loader").removeClass("loader");
                        $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Failed</h2></div>\');
                        $(\'#alert-payment\').fadeIn("slow");
                    }

                    e.complete("success");

                    if(paymentIntent.status == "requires_action")
                    {
                        stripe.confirmCardPayment(intent.client_secret).then(function(result){
                            if(result.error)
                            {
                                // failed
                                e.complete("fail");
                                $("#text-alert").hide();
                                $("#text-alert").empty();
                                $("#loader").hide();
                                $("#loader").removeClass("loader");
                                $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Failed</h2></div>\');
                                $(\'#alert-payment\').fadeIn("slow");
                            }
                            else
                            {
                                // success
                                $.ajax({
                                data: {
                                    "authorizationID": paymentIntent.id,
                                    "sessionId": \''.$sessionId.'\',
                                },
                                type: \'POST\',
                                url: \''. url('/api') .'/payment/stripe/confirm\'
                                }).done(function(data) {
                                if(data.id=="1")
                                {
                                    e.complete("success");
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $(\'#alert-payment\').html(\'<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                    setTimeout(function (){
                                        window.openAppRoute("/payment/page/payment-successful");
                                    }, 1000); 
                                }

                                }).fail(function(error) {
                                    
                                });
                            }
                        });
                        
                    } else {
                                // success
                                $.ajax({
                                data: {
                                    "authorizationID": paymentIntent.id,
                                    "sessionId": \''.$sessionId.'\',
                                },
                                type: \'POST\',
                                url: \''. url('/api') .'/payment/stripe/confirm\'
                                }).done(function(data) {
                                if(data.id=="1")
                                {
                                    e.complete("success");
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $(\'#alert-payment\').html(\'<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                    setTimeout(function (){
                                        window.openAppRoute("/payment/page/payment-successful");
                                    }, 1000); 
                                }

                                }).fail(function(error) {
                                    
                                });
                    }

                });
            

        ';
        return response($jscript)->header('Content-Type', 'application/javascript');
    }


    public function paypal_jscript($sessionId)
    {
        
        $payment_container = '<hr />
        <div id="proses">
            <div id="paypal-button-container"></div>
            <div id="change_payment" class="mt-2">
                <center><small><a href="#paymentMethod" class="text-theme" onClick="changePaymentMethod();">Click here</a> to change payment method</small></center>
            </div>
        </div>

        <div id="loader" class="mb-4"></div>
        <div id="text-alert" class="text-center"></div>
        ';
        

        $jscript = '
        $(document).ready(function() {

            $("#submitCheckout").slideUp("slow");  
            $("#paymentContainer").html(\''. str_replace(array("\r", "\n"), '', $payment_container) .'\');
           
            paypal.Buttons({
                /*
                style: {
                    layout: "horizontal",
                    color: "gold",
                    label: "pay",
                    tagline: false
                },
                */
                createOrder: function() {
                    $("#alert-payment").html(\'\');
                    return fetch(\''. url('/api') .'/payment/paypal\', {
                        method: \'POST\',
                        credentials: \'same-origin\',
                        headers: {
                            \'sessionId\': \''.$sessionId.'\'
                            }
                    }).then(function(res) {
                            return res.json();
                    }).then(function(data) {
                            return data.result.id;
                    });
                },
                onError: function (err) {
                    $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Error!</h2></div>\');
                    $(\'#alert-payment\').fadeIn("slow");
                },
                onApprove: function(data, actions) {
                    
                    $("#proses").hide();
                    $("#loader").addClass("loader");
                    $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );

                    actions.order.capture().then(function(orderData) {
                            
                            $.ajax({
                                data: {
                                    "orderID": data.orderID,
                                    "sessionId": \''.$sessionId.'\',
                                },
                                type: \'POST\',
                                url: \''. url('/api') .'/payment/paypal/confirm\'
                            }).done(function(data) {
                                if(data.id=="1")
                                {
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $(\'#alert-payment\').html(\'<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                    setTimeout(function (){
                                        afterCheckout(data.message);
                                    }, 1000);
                                }
                                else
                                {
                                    $("#loader").hide();
                                    $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Failed!</h2></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                }
                            }).fail(function(error) {
                                
                            });

                    });
                }
            }).render(\'#paypal-button-container\');
        });';
        
        return response($jscript)->header('Content-Type', 'application/javascript');
    }


}
