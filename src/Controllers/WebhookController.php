<?php
namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\PaymentHelper;
use budisteikul\vertikaltrip\Models\Shoppingcart;
use budisteikul\vertikaltrip\Models\ShoppingcartPayment;
use budisteikul\vertikaltrip\Models\Contact;
use budisteikul\vertikaltrip\Helpers\WiseHelper;
use budisteikul\vertikaltrip\Helpers\XenditHelper;
use budisteikul\vertikaltrip\Helpers\TaskHelper;
use budisteikul\vertikaltrip\Helpers\LogHelper;
use budisteikul\vertikaltrip\Helpers\WhatsappHelper;
use Illuminate\Support\Facades\Storage;

class WebhookController extends Controller
{
    
	
    public function __construct()
    {
        
    }
    
    public function webhook($webhook_app,Request $request)
    {
        if($webhook_app=="whatsapp")
        {
            $whatsapp = new WhatsappHelper;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {

                $json = $request->getContent();
                $data = json_decode($json);
               
                
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
                    

                }

                    
                if(isset($data->entry[0]->changes[0]->value->statuses[0]))
                {
                    $message_id = $data->entry[0]->changes[0]->value->statuses[0]->id;
                    $status = $data->entry[0]->changes[0]->value->statuses[0]->status;
                    $whatsapp->setStatusMessage($message_id,$status);
                }
                    
                
                //==================================================
                switch(strtolower($message))
                {
                    case "/participant":
                        $message = BookingHelper::schedule_bydate(date('Y-m-d'));
                        $whatsapp->sendText($from,$message);
                    break;
                    case "who are the tour participants today?":
                        $message = BookingHelper::schedule_bydate(date('Y-m-d'));
                        $whatsapp->sendText($from,$message);
                    break;
                    default:
                }
                //==================================================

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

        if($webhook_app=="create_booking")
        {
            $json = json_decode($request->getContent());
            if(isset($json->webhook_key))
            {
                if($json->webhook_key==env('APP_KEY'))
                {
                    BookingHelper::confirm_transaction(null,$json);
                }
            }
            
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        if($webhook_app=="wise")
        {
            
            LogHelper::log(json_decode($request->getContent(), true),$webhook_app);

            
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

            $confirmation_code = '';
            if(isset($data['externalBookingReference']))
            {
                $confirmation_code = $data['externalBookingReference'];
            }
            else
            {
                $confirmation_code = $data['confirmationCode'];
            }

            if($bookingChannel=="Viator.com") $confirmation_code = 'BR-'. $data['externalBookingReference'];
            

            $status = $data['status'];

            switch($status)
            {
                case 'CONFIRMED':
                    
                    $notification = false;
                    $shoppingcart = Shoppingcart::where('confirmation_code',$confirmation_code)->where('booking_status','CONFIRMED')->first();

                    if($shoppingcart)
                    {
                        $shoppingcart->delete();
                    }
                    else
                    {
                        $notification = true;
                    }
                    
                    $shoppingcart = BookingHelper::webhook_bokun($data);
                    $shoppingcart->booking_status = "CONFIRMED";
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
