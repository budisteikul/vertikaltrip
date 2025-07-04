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

use budisteikul\vertikaltrip\Models\Shoppingcart;
use budisteikul\vertikaltrip\Models\ShoppingcartProduct;
use budisteikul\vertikaltrip\Models\ShoppingcartProductDetail;
use budisteikul\vertikaltrip\Models\ShoppingcartQuestion;
use budisteikul\vertikaltrip\Models\ShoppingcartPayment;
use budisteikul\vertikaltrip\Models\Contact;

use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class WebhookController extends Controller
{
    
	
    public function __construct()
    {
        
    }
    
    public function webhook($webhook_app,Request $request)
    {
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

                            if(!empty($message->contacts))
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
                            

                            if(!empty($message->contacts))
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

        if($webhook_app=="create_booking_with_email")
        {
            $subject = $request->input("subject");
            $body = $request->input("body-plain");
            $text = $body;

            if($subject=="")
            {
                return response('DATA TIDAK LENGKAP STEP 1', 200)->header('Content-Type', 'text/plain');
            }

            //$booking_confirmation_code = GeneralHelper::get_string_between($text,"Reference number: "," ");

            $command = 'Extract data with JSON object format as 

            {
                "booking_confirmation_code" : get reference number or confirmation code,
                "booking_channel" : name of sender GetyourGuide or Airbnb,
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

            Set "" if don\'t have data';


            
            $openai = New OpenAIHelper;
            $data = $openai->openai($text,$command);
            $booking_json = json_decode($data);
            

            

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
                //$booking_json->booking_confirmation_code = BookingHelper::get_ticket();
            }
            
            print_r($booking_json);
            
            if(strtolower($booking_json->p_time)=="night" && strtolower($booking_json->p_location)=="yogyakarta")
            {
                $booking_json->p_product_id = 7424;
            }
            else if(strtolower($booking_json->p_time)=="evening" && strtolower($booking_json->p_location)=="yogyakarta")
            {
                $booking_json->p_product_id = 7424;
            }
            else if(strtolower($booking_json->p_time)=="morning" && strtolower($booking_json->p_location)=="yogyakarta")
            {
                $booking_json->p_product_id = 10091;
            }
            else
            {
                print_r("p_time". strtolower($booking_json->p_time));
                print_r("p_location". strtolower($booking_json->p_location));
                return response('DATA TIDAK LENGKAP STEP 3', 200)->header('Content-Type', 'text/plain');
            }

            $check_first = Shoppingcart::where('confirmation_code',$booking_json->booking_confirmation_code)->first();
            if($check_first)
            {
                return response('DUPLICATE', 200)->header('Content-Type', 'text/plain');
            }
            //print_r($booking_json->booking_channel);
            
            //exit();

            $shoppingcart = new Shoppingcart();
            $shoppingcart->booking_status = "CONFIRMED";
            $shoppingcart->session_id = Uuid::uuid4()->toString();
            $shoppingcart->booking_channel = $booking_json->booking_channel;
            $shoppingcart->confirmation_code = $booking_json->booking_confirmation_code;
            $shoppingcart->save();

            $shoppingcart_product = new ShoppingcartProduct();
            $shoppingcart_product->shoppingcart_id = $shoppingcart->id;
            $shoppingcart_product->product_id = $booking_json->p_product_id;
            $shoppingcart_product->title = $booking_json->tour_name;
            $shoppingcart_product->rate = "Open Trip";
            $shoppingcart_product->date = $booking_json->tour_date;
            $shoppingcart_product->cancellation = "Referring to ".$booking_json->booking_channel." policy";
            $shoppingcart_product->save();

            $shoppingcart_product_detail = new ShoppingcartProductDetail();
            $shoppingcart_product_detail->shoppingcart_product_id = $shoppingcart_product->id;
            $shoppingcart_product_detail->type = "product";
            $shoppingcart_product_detail->title = $booking_json->tour_name;
            $shoppingcart_product_detail->unit_price = "Persons";
            $shoppingcart_product_detail->people = $booking_json->participant_total;
            $shoppingcart_product_detail->qty = $booking_json->participant_total;
            $shoppingcart_product_detail->save();
            
            $shoppingcart_payment = new ShoppingcartPayment();
            $shoppingcart_payment->shoppingcart_id = $shoppingcart->id;
            $shoppingcart_payment->payment_provider = "none";
            $shoppingcart_payment->save();

            $shoppingcart_question = new ShoppingcartQuestion();
            $shoppingcart_question->shoppingcart_id = $shoppingcart->id;
            $shoppingcart_question->type = "mainContactDetails";
            $shoppingcart_question->when_to_ask = "booking";
            $shoppingcart_question->question_id = "firstName";
            $shoppingcart_question->answer = $booking_json->participant_name;
            $shoppingcart_question->save();

            $shoppingcart_question = new ShoppingcartQuestion();
            $shoppingcart_question->shoppingcart_id = $shoppingcart->id;
            $shoppingcart_question->type = "mainContactDetails";
            $shoppingcart_question->when_to_ask = "booking";
            $shoppingcart_question->question_id = "lastName";
            $shoppingcart_question->answer = null;
            $shoppingcart_question->save();

            $shoppingcart_question = new ShoppingcartQuestion();
            $shoppingcart_question->shoppingcart_id = $shoppingcart->id;
            $shoppingcart_question->type = "mainContactDetails";
            $shoppingcart_question->when_to_ask = "booking";
            $shoppingcart_question->question_id = "phoneNumber";
            $shoppingcart_question->answer = $booking_json->participant_phone;
            $shoppingcart_question->save();

            $shoppingcart_question = new ShoppingcartQuestion();
            $shoppingcart_question->shoppingcart_id = $shoppingcart->id;
            $shoppingcart_question->type = "mainContactDetails";
            $shoppingcart_question->when_to_ask = "booking";
            $shoppingcart_question->question_id = "email";
            $shoppingcart_question->answer = $booking_json->participant_email;
            $shoppingcart_question->save();

            $shoppingcart_question = new ShoppingcartQuestion();
            $shoppingcart_question->shoppingcart_id = $shoppingcart->id;
            $shoppingcart_question->type = "activityBookings";
            $shoppingcart_question->when_to_ask = "booking";
            $shoppingcart_question->question_id = "GENERAL";
            $shoppingcart_question->label = "Note";
            $shoppingcart_question->answer = $booking_json->booking_note;
            $shoppingcart_question->save();

            BookingHelper::shoppingcart_notif($shoppingcart);

            /*
            $json = json_decode($request->getContent());
            if(isset($json->webhook_key))
            {
                if($json->webhook_key==env('APP_KEY'))
                {
                    BookingHelper::confirm_transaction(null,$json);
                }
            }
            */
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
