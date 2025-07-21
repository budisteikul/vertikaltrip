<?php
namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\PaymentHelper;
use budisteikul\vertikaltrip\Helpers\WiseHelper;
use budisteikul\vertikaltrip\Helpers\XenditHelper;
use budisteikul\vertikaltrip\Helpers\TaskHelper;
use budisteikul\vertikaltrip\Helpers\LogHelper;
use budisteikul\vertikaltrip\Helpers\WhatsappHelper;
use budisteikul\vertikaltrip\Helpers\OpenAIHelper;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;
use budisteikul\vertikaltrip\Helpers\SettingHelper;
use budisteikul\vertikaltrip\Helpers\BokunHelper;
use budisteikul\vertikaltrip\Models\Shoppingcart;
use budisteikul\vertikaltrip\Models\ShoppingcartProduct;
use budisteikul\vertikaltrip\Models\ShoppingcartProductDetail;
use budisteikul\vertikaltrip\Models\ShoppingcartQuestion;
use budisteikul\vertikaltrip\Models\ShoppingcartPayment;
use budisteikul\vertikaltrip\Models\Contact;
use budisteikul\vertikaltrip\Models\Product;
use budisteikul\vertikaltrip\Models\CloseOut;

use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

use Carbon\Carbon;

class WebhookController extends Controller
{
    
	
    public function __construct()
    {
        
    }
    
    public function webhook($webhook_app,Request $request)
    {
        
        if($webhook_app=="test")
        {
            exit();
            $data = [
                "booking_confirmation_code" => BookingHelper::get_ticket(),
                "booking_channel" => "whatsapp",
                "booking_note" => "",
                "tour_name" => "Jogja Night Food Tour",
                "tour_date" => "2025-07-22 18:30:00",
                "participant_name" => "budi",
                "participant_phone" => "+6285743112112",
                "participant_email" => "",
                "participant_total" => "2",
                "product_id" => "7424"
            ];
            $booking_json = (object)$data;
            //print_r($booking_json);
            BookingHelper::booking_by_json($booking_json);

            exit();
        }

        if($webhook_app=="whatsapp_booking_01")
        {

            $body = json_decode($request->getContent(), true);
            $whatsapp = new WhatsappHelper;
            $decryptedData = $whatsapp->decryptRequest($body);

            $tour_id = $request->input("tour_id");
            $payment = $request->input("payment");
            $product = Product::findOrFail($tour_id);
            $content = BokunHelper::get_product($product->bokun_id);

            $next_availability = BookingHelper::next_availability($product->bokun_id,20);
            foreach($next_availability as $x)
            {
                    $date[] = [
                        "id"=> $x->date,
                        "title"=> GeneralHelper::dateFormat($x->date,6)
                    ];
            }

            if($decryptedData["decryptedBody"]["action"]=="ping")
            {
                //health check
                $screen = [
                    "data" => [
                        "status" => "active"
                    ]
                ];
            }
            else if(isset($decryptedData["decryptedBody"]["data"]["step"]))
            {
                
                

                if($decryptedData["decryptedBody"]["data"]["step"]=="confirm_booking")
                {
                    //success
                    $screen = [
                        "screen" => "SUCCESS",
                        "data" => []
                    ];
                }
                else
                {
                    
                    if($payment!="")
                    {
                        $body_information = "Pay online";
                        $payment="on";
                    }
                    else
                    {
                        $body_information = "Payment Instruction :\nPlease pay in cash directly to your guide at the meeting point before the tour starts.";
                        $payment="off";
                    }
                    //summary
                    $price = $content->nextDefaultPriceMoney->amount;
                    $total_price = $price * $decryptedData["decryptedBody"]["data"]["participant"];
                    
                    $more_details = 'no dietary';
                    if(isset($decryptedData["decryptedBody"]["data"]["more_details"])) $more_details = $decryptedData["decryptedBody"]["data"]["more_details"];

                    $screen = [
                        "screen" => "SUMMARY",
                        "data" => [
                            "appointment"=> GeneralHelper::dateFormat($decryptedData["decryptedBody"]["data"]["date"],6) ."\n".$decryptedData["decryptedBody"]["data"]["time"]."\n". $decryptedData["decryptedBody"]["data"]["participant"] ." adults",
                            "more_details"=> $more_details,
                            "date"=> $decryptedData["decryptedBody"]["data"]["date"],
                            "time"=> $decryptedData["decryptedBody"]["data"]["time"],
                            "participant"=> $decryptedData["decryptedBody"]["data"]["participant"],
                            "head_information"=> "Total Price :\n".$content->nextDefaultPriceMoney->currency." ". GeneralHelper::numberFormat($total_price,$content->nextDefaultPriceMoney->currency),
                            "body_information"=> $body_information,
                            "session_id"=> $decryptedData["decryptedBody"]["data"]["session_id"],
                            "step"=> "confirm_booking",
                            "bokun_id"=> $decryptedData["decryptedBody"]["data"]["bokun_id"],
                            "tour_name"=> $decryptedData["decryptedBody"]["data"]["tour_name"],
                            "payment"=> $payment
                        ]
                    ];
                }
                
                    
            }
            else
            {
                
                if(isset($decryptedData["decryptedBody"]["data"]["trigger"]))
                {
                    //After selected date
                    //$decryptedData["decryptedBody"]["data"]["date"]
                    foreach($next_availability as $x)
                    {
                        if($x->date==$decryptedData["decryptedBody"]["data"]["date"])
                        {
                            $availability_participant = $x->max_participant - $x->booking;
                        }
                        
                    }

                    

                    for($i=1;$i<=$availability_participant;$i++)
                    {
                        $unit = "adult";
                        if($i>1) $unit = "adults";
                        $participant[] = [
                            "id"=> (string)$i,
                            "title"=> (string)$i." ".(string)$unit
                        ];
                    }
                   

                    
                }
                else
                {
                    //Init flow
                    $participant = [
                                        [
                                            "id"=> "1",
                                            "title"=> "1 adult"
                                        ],
                                        [
                                            "id"=> "2",
                                            "title"=> "2 adults"
                                        ],
                                        [
                                            "id"=> "3",
                                            "title"=> "3 adults"
                                        ],
                                        [
                                            "id"=> "4",
                                            "title"=> "4 adults"
                                        ],
                                        [
                                            "id"=> "5",
                                            "title"=> "5 adults"
                                        ],
                                        [
                                            "id"=> "6",
                                            "title"=> "6 adults"
                                        ],
                                        [
                                            "id"=> "7",
                                            "title"=> "7 adults"
                                        ],
                                        [
                                            "id"=> "8",
                                            "title"=> "8 adults"
                                        ]
                                    ];

                    
                }

                
                    $time = [
                                [
                                   "id"=> GeneralHelper::digitFormat($content->startTimes[0]->hour,2) .":". GeneralHelper::digitFormat($content->startTimes[0]->minute,2),
                                    "title"=> GeneralHelper::digitFormat($content->startTimes[0]->hour,2) .":". GeneralHelper::digitFormat($content->startTimes[0]->minute,2)
                                ]
                            ];
                
                
                $screen = [
                    "screen" => "APPOINTMENT",
                    "data" => [
                        "date" => $date,
                        "is_date_enabled" => true,
                        "time" => $time,
                        "is_time_enabled" => true,
                        "participant" => $participant,
                        "is_participant_enabled" => true,
                        "information"=> "Price : ".$content->nextDefaultPriceMoney->currency." ".GeneralHelper::numberFormat($content->nextDefaultPriceMoney->amount,$content->nextDefaultPriceMoney->currency)." / participant",
                        "session_id"=> Uuid::uuid4()->toString(),
                        "step"=> "init",
                        "tour_name"=> $product->name,
                        "bokun_id"=> (string)$product->bokun_id
                    ]
                ];

            }
            



            $resBody = $whatsapp->encryptResponse($screen, $decryptedData['aesKeyBuffer'], $decryptedData['initialVectorBuffer']);
            return $resBody;
        }

        if($webhook_app=="whatsapp")
        {
            

            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {

                $json = $request->getContent();
                $data = json_decode($json);

               
                $whatsapp = new WhatsappHelper;

                
                if(isset($data->entry[0]->changes[0]->value->messages[0]->id))
                {
                    $check = $whatsapp->check_wa_id($data->entry[0]->changes[0]->value->messages[0]->id);
                    if($check)
                    {
                        return response('OK', 200)->header('Content-Type', 'text/plain');
                    }
                }

                if(isset($data->entry[0]->changes[0]->value->statuses[0]))
                {
                    $message_id = $data->entry[0]->changes[0]->value->statuses[0]->id;
                    $status = $data->entry[0]->changes[0]->value->statuses[0]->status;
                    $whatsapp->setStatusMessage($message_id,$status);
                }



                if(isset($data->entry[0]->changes[0]->value->messages[0]))
                {
                    $type = $data->entry[0]->changes[0]->value->messages[0]->type;
                    $from = $data->entry[0]->changes[0]->value->messages[0]->from;
                    $message_id = $data->entry[0]->changes[0]->value->messages[0]->id;
                    $business_id = $data->entry[0]->changes[0]->value->metadata->phone_number_id;
                    $name = 'My Friend';
                    if(isset($data->entry[0]->changes[0]->value->contacts[0]->profile->name)) $name = $data->entry[0]->changes[0]->value->contacts[0]->profile->name;

                    $message = '';
                    switch($type)
                    {
                        case "text":
                            $message = $data->entry[0]->changes[0]->value->messages[0]->text->body;
                        break;
                        case "reaction":
                            $message = $data->entry[0]->changes[0]->value->messages[0]->reaction->emoji;
                        break;
                        case "image":
                            $media_id = $data->entry[0]->changes[0]->value->messages[0]->image->id;
                            $media = $whatsapp->getMedia($media_id,$from);
                            $caption = "";
                            if(isset($data->entry[0]->changes[0]->value->messages[0]->image->caption))$caption = $data->entry[0]->changes[0]->value->messages[0]->image->caption;
                            $message = $media->url."\n\n".$caption;
                            $data->entry[0]->changes[0]->value->messages[0]->image->link = $media->url;
                        break;
                        case "document":
                            $media_id = $data->entry[0]->changes[0]->value->messages[0]->document->id;
                            $media = $whatsapp->getMedia($media_id,$from);
                            $caption = "";
                            if(isset($data->entry[0]->changes[0]->value->messages[0]->document->caption)) $caption = $data->entry[0]->changes[0]->value->messages[0]->document->caption;
                            $message = $media->url."\n\n".$caption;
                            $data->entry[0]->changes[0]->value->messages[0]->document->link = $media->url;
                        break;
                        case "video":
                            $media_id = $data->entry[0]->changes[0]->value->messages[0]->video->id;
                            $media = $whatsapp->getMedia($media_id,$from);
                            $caption = "";
                            if(isset($data->entry[0]->changes[0]->value->messages[0]->video->caption)) $caption = $data->entry[0]->changes[0]->value->messages[0]->video->caption;
                            $message = $media->url."\n\n".$caption;
                            $data->entry[0]->changes[0]->value->messages[0]->video->link = $media->url;
                        break;
                        case "order":
                            $orders = $data->entry[0]->changes[0]->value->messages[0]->order->product_items;
                            $total = 0;
                            $message = "";
                            foreach($orders as $order)
                            {
                                $subtotal = $order->quantity * $order->item_price;
                                $total += $subtotal;
                            }
                            $xendit = new XenditHelper;
                            $xendit = $xendit->createInvoice($total);

                            $message = "Please follow this link below to make a payment.\n". $xendit->invoice_url;
                            $whatsapp->sendText($from,$message);
                        break;
                        case "request_welcome":
                            $message = "request_welcome";
                            $contact = Contact::where('wa_id',$from)->first();
                            if(!$contact)
                            {
                                $message = "Hello ". $name .",\nYour *3AM friend* is here!\nCan I help you? 🙏😊";
                                $whatsapp->sendText($from,$message);
                            }
                        break;
                        case "interactive":
                            $message = 'Not supported message. Type: '.$type;
                        break;
                        default:
                            $message = 'Not supported message. Type: '.$type;
                    }

                    $whatsapp->saveInboundMessage($data);
                    
                    
                    
                    //==================================================
                    $varmessage = explode(" ",$message);
                    switch(strtolower($varmessage[0]))
                    {
                        case "/ptcp":

                            if(isset($varmessage[1]))
                            {
                                if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$varmessage[1])) {
                                    $date = $varmessage[1];
                                } 
                                else 
                                {
                                    $date = date('Y-m-d');
                                }
                            }
                            else
                            {
                                $date = date('Y-m-d');
                            }

                            $message = BookingHelper::schedule_bydate($date);
                            $whatsapp->sendText($from,$message->text);

                            if(!empty($message->contacts) || $message->contacts!="")
                            {
                                $whatsapp->sendContact($from,$message->contacts);
                            }
                        
                        break;
                        case "/contacts":
                            if(isset($varmessage[1]))
                            {
                                if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$varmessage[1])) {
                                    $date = $varmessage[1];
                                } 
                                else 
                                {
                                    $date = date('Y-m-d');
                                }
                            }
                            else
                            {
                                $date = date('Y-m-d');
                            }

                            $message = BookingHelper::schedule_bydate($date);
                            

                            if(!empty($message->contacts) || $message->contacts!="")
                            {
                                $whatsapp->sendContact($from,$message->contacts);
                            }
                            else
                            {
                                $whatsapp->sendText($from,"There is no participant ". $date);
                            }
                        break;
                        default:
                    }
                    //==================================================
                }

                    
                
                    
                
                curl_setopt_array($ch = curl_init(), array(
                        CURLOPT_URL => "https://api.pushover.net/1/messages.json",
                        CURLOPT_POSTFIELDS => array(
                            "token" => env('PUSHOVER_TOKEN'),
                            "user" => env('PUSHOVER_USER'),
                            "title" => 'New Message: +'. $from,
                            "message" => $message,
                            "url" => env("APP_ADMIN_URL").'/cms/contact/'.$whatsapp->contact($from).'/edit/',
                            "url_title" => "Reply"
                        ),
                    ));
                curl_exec($ch);
                curl_close($ch);

                return response('OK', 200)->header('Content-Type', 'text/plain');
            }
            else
            {
                $mode = $request->input("hub_mode");
                $token = $request->input("hub_verify_token");
                $challenge = $request->input("hub_challenge");

                if ($mode == "subscribe" && $token == env("META_WHATSAPP_TOKEN")) {
                    return response($challenge, 200)->header('Content-Type', 'text/plain');
                } else {
                    return response('Forbidden', 403)->header('Content-Type', 'text/plain');
                }
            }
        }


        if($webhook_app=="cancel_booking_with_email")
        {
            $token = $request->input("token");
            $timestamp = $request->input("timestamp");
            $signature = $request->input("signature");

            $hmac = hash_hmac('sha256', $timestamp.$token, env("MAILGUN_WEBHOOK_SECRET"));

            if($hmac!=$signature)
            {
                return response('SIGNATURE INVALID', 200)->header('Content-Type', 'text/plain');
            }

            $subject = $request->input("subject");
            $body = $request->input("body-html");
            $text = $body;

            $command = 'Extract data with JSON object format as 

            {
                "booking_confirmation_code" : get the reference number or confirmation code usually the first letter uses ta or gyg
            }

            ';

            
            $openai = New OpenAIHelper;
            $data = $openai->openai($text,$command);
            $booking_json = json_decode($data);

            //coba 2 kali lagi
            if(!isset($booking_json->booking_confirmation_code))
            {
                $data = $openai->openai($text,$command);
                $booking_json = json_decode($data);
            }

            if(!isset($booking_json->booking_confirmation_code))
            {
                $data = $openai->openai($text,$command);
                $booking_json = json_decode($data);
            }

            if(!isset($booking_json->booking_confirmation_code))
            {
                return response('DATA TIDAK LENGKAP STEP 2', 200)->header('Content-Type', 'text/plain');
            }

            $shoppingcart = Shoppingcart::where('confirmation_code',$booking_json->booking_confirmation_code)->first();
            if($shoppingcart)
            {
                $shoppingcart->booking_status = 'CANCELED';
                $shoppingcart->save();
                
                BookingHelper::shoppingcart_notif($shoppingcart);
            }

            return response('OK', 200)->header('Content-Type', 'text/plain');
        }


        if($webhook_app=="create_booking_with_email")
        {

            $token = $request->input("token");
            $timestamp = $request->input("timestamp");
            $signature = $request->input("signature");

            $hmac = hash_hmac('sha256', $timestamp.$token, env("MAILGUN_WEBHOOK_SECRET"));

            if($hmac!=$signature)
            {
                return response('SIGNATURE INVALID', 200)->header('Content-Type', 'text/plain');
            }

            $subject = $request->input("subject");
            $body = $request->input("body-html");
            $text = $body;

            $command = 'Extract data with JSON object format as 

            {
                "booking_confirmation_code" : get the reference number or confirmation code usually the first letter uses ta or gyg,
                "booking_channel" : name of sender GetYourGuide or Airbnb,
                "booking_note" : "",
                "tour_name" : offer or booking name has been booked,
                "tour_date" : date of the tour, format YYYY-mm-dd HH:ii:ss,
                "participant_name" : get participant name,
                "participant_phone" : get participant phone,
                "participant_email" : get participant email,
                "participant_total" : get total participant,
                "p_time" : night or morning or evening from tour date,
                "p_location" : yogyakarta or bali from tour name
            }

            Set null if don\'t have data';

            
            $openai = New OpenAIHelper;
            $data = $openai->openai($text,$command);
            $booking_json = json_decode($data);
            
            //coba 2 kali lagi
            if(!isset($booking_json->booking_confirmation_code))
            {
                $data = $openai->openai($text,$command);
                $booking_json = json_decode($data);
            }

            if(!isset($booking_json->booking_confirmation_code))
            {
                $data = $openai->openai($text,$command);
                $booking_json = json_decode($data);
            }

            if(!isset($booking_json->booking_confirmation_code))
            {
                return response('DATA TIDAK LENGKAP STEP 2', 200)->header('Content-Type', 'text/plain');
            }

            
            if((strtolower($booking_json->p_time)=="night" || strtolower($booking_json->p_time)=="evening") && strtolower($booking_json->p_location)=="yogyakarta")
            {
                $product = Product::findOrFail(1);
                $booking_json->product_id = $product->bokun_id;
            }
            else if(strtolower($booking_json->p_time)=="morning" && strtolower($booking_json->p_location)=="yogyakarta")
            {
                $product = Product::findOrFail(44);
                $booking_json->product_id = $product->bokun_id;
            }
            else
            {
                return response('DATA TIDAK LENGKAP STEP 3', 200)->header('Content-Type', 'text/plain');
            }

            $check_first = Shoppingcart::where('confirmation_code',$booking_json->booking_confirmation_code)->first();
            if($check_first)
            {
                return response('DUPLICATE', 200)->header('Content-Type', 'text/plain');
            }
            

            BookingHelper::booking_by_json($booking_json);

            BookingHelper::shoppingcart_notif($shoppingcart);

            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        if($webhook_app=="wise")
        {
            
            //LogHelper::log(json_decode($request->getContent(), true),$webhook_app);

            
            $is_test = $request->header('X-Test-Notification');
            if($is_test)
            {
                return response('OK', 200)->header('Content-Type', 'text/plain');
            }

            $signature = $request->header('X-Signature-SHA256');
            $delivery_id = $request->header('X-Delivery-Id');
            $json      = $request->getContent();
            $tw = new WiseHelper();
            $verify = $tw->checkSignature($json,$signature);

            if($verify)
            {
                $data = json_decode($json);
                $amount = $data->data->amount;
                $currency = $data->data->currency;
                $profileId = $data->data->resource->profile_id;
                $customerTransactionId = $delivery_id;

                $shoppingcart_payment = ShoppingcartPayment::where('currency',$currency)->where('amount',$amount)->where('payment_status',4)->first();
                if($shoppingcart_payment)
                {
                    $shoppingcart_payment->shoppingcart->booking_status = "CONFIRMED";
                    $shoppingcart_payment->shoppingcart->save();  
                    $shoppingcart_payment->payment_status = 2;
                    $shoppingcart_payment->save();
                    BookingHelper::shoppingcart_mail($shoppingcart_payment->shoppingcart);
                    BookingHelper::shoppingcart_whatsapp($shoppingcart_payment->shoppingcart);
                    BookingHelper::shoppingcart_notif($shoppingcart_payment->shoppingcart); 
                }
                
            }
            
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }


        if($webhook_app=="bokun")
        {
            $data = json_decode($request->getContent(), true);

            //LogHelper::log($data,$webhook_app);

            $bookingChannel = '';
            if(isset($data['affiliate']['title']))
            {
                $bookingChannel = $data['affiliate']['title'];
            }
            else
            {
                $bookingChannel = $data['seller']['title'];
            }

            $confirmation_code = $data['confirmationCode'];

            if($bookingChannel=="Viator.com") $confirmation_code = 'BR-'. $data['externalBookingReference'];
            

            $status = $data['status'];

            switch($status)
            {
                case 'CONFIRMED':
                    
                    $notification = false;
                    $shoppingcart = Shoppingcart::where('confirmation_code',$confirmation_code)->where('booking_status','CONFIRMED')->first();

                    $created_at = date('Y-m-d H:i:s');

                    if($shoppingcart)
                    {
                        $created_at = $shoppingcart->created_at;
                        $shoppingcart->delete();
                    }
                    else
                    {
                        $notification = true;
                    }
                    
                    $shoppingcart = BookingHelper::webhook_bokun($data);
                    $shoppingcart->booking_status = "CONFIRMED";
                    $shoppingcart->created_at = $created_at;
                    $shoppingcart->save();

                    if($notification)
                    {
                        BookingHelper::shoppingcart_notif($shoppingcart);
                    }
                    
                    
                    return response('CONFIRMED OK', 200)->header('Content-Type', 'text/plain');
                break;
                case 'CANCELLED':

                    $shoppingcart = Shoppingcart::where('confirmation_code',$confirmation_code)->where('booking_status','CONFIRMED')->first();

                    if($shoppingcart)
                    {
                        $shoppingcart->booking_status = "CANCELED";
                        $shoppingcart->save();
                        BookingHelper::shoppingcart_notif($shoppingcart);
                    }

                    
                    return response('CANCELLED OK', 200)->header('Content-Type', 'text/plain');
                break;
            }
        }

        return response('ERROR', 200)->header('Content-Type', 'text/plain');
    }

}
