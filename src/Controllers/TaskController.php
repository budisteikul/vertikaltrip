<?php
namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use budisteikul\vertikaltrip\Helpers\TaskHelper;
use budisteikul\vertikaltrip\Helpers\WiseHelper;
use budisteikul\vertikaltrip\Helpers\WhatsappHelper;

use budisteikul\vertikaltrip\Models\Shoppingcart;

use Illuminate\Support\Facades\Mail;
use budisteikul\vertikaltrip\Mail\BookingConfirmedMail;

use budisteikul\vertikaltrip\Helpers\ProductHelper;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;

class TaskController extends Controller
{
	public function task(Request $request)
    {
    	
        
        $json = $request->getContent();
        
        //TaskHelper::delete($json);

		$data = json_decode($json);

        if($data->app=="wise")
        {
            if($data->token==env('WISE_TOKEN'))
            {
                if($data->profileId!=env('WISE_PROFILE_ID'))
                {
                    return response('OK', 200)->header('Content-Type', 'text/plain');
                }

                $tw = new WiseHelper();
                
                $quote = $tw->postCreateQuote($data->amount,$data->currency,null,'IDR',$data->profileId);
                if(isset($quote->error))
                {
                    return response('ERROR', 200)->header('Content-Type', 'text/plain');
                }

                $transferwise = $tw->postCreateTransfer($quote->id,$data->customerTransactionId);
                $fund = $tw->postFundTransfer($transferwise->id);
                
                return response('OK', 200)->header('Content-Type', 'text/plain');

            }
            return response('ERROR', 200)->header('Content-Type', 'text/plain');
        }

        if($data->app=="mail")
        {
            $shoppingcart = Shoppingcart::where('session_id',$data->session_id)->where('confirmation_code',$data->confirmation_code)->first();
            $email = $shoppingcart->shoppingcart_questions()->select('answer')->where('type','mainContactDetails')->where('question_id','email')->first()->answer;
            if($email!="")
            {
                Mail::to($email)->cc([env("MAIL_FROM_ADDRESS")])->send(new BookingConfirmedMail($shoppingcart));
            }
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        if($data->app=="mail_question")
        {
            Mail::to($data->email)->cc([env("MAIL_FROM_ADDRESS")])->send(new JogjaFoodTourQuestionMail($data));
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        if($data->app=="whatsapp")
        {
            $shoppingcart = Shoppingcart::where('session_id',$data->session_id)->where('confirmation_code',$data->confirmation_code)->first();
            if($shoppingcart)
            {
                

                $firstName = $shoppingcart->shoppingcart_questions()->select('answer')->where('type','mainContactDetails')->where('question_id','firstName')->first()->answer;
                $phoneNumber = $shoppingcart->shoppingcart_questions()->select('answer')->where('type','mainContactDetails')->where('question_id','phoneNumber')->first()->answer;
                $bookingNumber = $shoppingcart->confirmation_code;
                $bookingChannel = $shoppingcart->booking_channel;
                $link_url = $shoppingcart->session_id .'/'. $shoppingcart->confirmation_code;

                if($phoneNumber=="")
                {
                    return response('OK', 200)->header('Content-Type', 'text/plain');
                }

                $phoneNumber = GeneralHelper::phoneNumber($phoneNumber);
                
                $parameters = (object)[
                                    (object)["type"=>"text","text"=> ucwords(strtolower($firstName))],
                                    (object)["type"=>"text","text"=> ucwords(strtolower($bookingChannel))],
                                    (object)["type"=>"text","text"=> $bookingNumber]
                              ];
                
                $string = config("site.wa_confirmed");
                
                
                    $components = (object)[
                                    (object)[
                                        "type"=> "body",
                                        "parameters" => $parameters
                                    ],
                                    (object)[
                                        "type"=> "button",
                                        "sub_type"=> "url",
                                        "index"=>0,
                                        "parameters" => (object)[
                                            (object)[
                                                "type" => "text",
                                                "text" => $link_url
                                            ]
                                        ]
                                    ]
                              ];
                

                

                $whatsapp = new WhatsappHelper;
                $whatsapp->sendTemplate($phoneNumber,config("site.wa_confirmed"), $components);
            }
            
            
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        if($data->app=="pushover")
        {
            $confirmation_code = $data->confirmation_code;
            $session_id = $data->session_id;

            $shoppingcart = Shoppingcart::where('session_id',$session_id)->where('confirmation_code',$confirmation_code)->first();
            if($shoppingcart)
            {
                $booking_status = "New";
                if($shoppingcart->booking_status=="CANCELED") $booking_status = "Canceled";
                if($shoppingcart->booking_status=="PENDING") $booking_status = "Pending";
                
                foreach($shoppingcart->shoppingcart_products as $product)
                {
                    $title = $booking_status . " Booking: ". ProductHelper::datetotext($product->date) .' ('.$confirmation_code.')';
                    

                    $message = $product->title .'
';
                    $message .= ProductHelper::datetotext($product->date) .'
';

                    foreach($product->shoppingcart_product_details as $product_detail)
                    {
                        //Product
                        if($product_detail->type=="product"|| $product_detail->type=="extra")
                        {
                            if($product_detail->unit_price == "Price per booking")
                            {
                                $message .= $product_detail->qty .' '. $product_detail->unit_price .' ('. $product_detail->people .' Person)
';
                            }
                            else
                            {
                                $message .= $product_detail->qty .' '. $product_detail->unit_price .'
';
                            }
                        }
                        elseif($product_detail->type=="pickup")
                        {
                            $message .= $product_detail->title .'
';
                        }

                        $message .= '
';
                        //Contact
                        $main_contact = BookingHelper::get_answer_contact($shoppingcart);

                        $message .= 'Name: '. $main_contact->firstName .' '. $main_contact->lastName .'
';
                        $message .= 'Phone: '. $main_contact->phoneNumber  .'
';
                        $message .= 'Email: '. $main_contact->email  .'
';

                        $message .= '
';
                        //Question
                        foreach($shoppingcart->shoppingcart_questions()->where('when_to_ask','booking')->where('booking_id',$product->booking_id)->whereNotNull('label')->get() as $shoppingcart_question)
                        {
                                $message .= $shoppingcart_question->label .'
'. $shoppingcart_question->answer .'
';
                        }
                        $participants = $shoppingcart->shoppingcart_questions()->where('when_to_ask','participant')->where('booking_id',$product->booking_id)->select('participant_number')->groupBy('participant_number')->get();
                        foreach($participants as $participant)
                        {
                            $message .= 'Participant '. $participant->participant_number .'
';
                            foreach($shoppingcart->shoppingcart_questions()->where('when_to_ask','participant')->where('booking_id',$product->booking_id)->where('participant_number',$participant->participant_number)->get() as $participant_detail)
                            {
                                $message .= ''.$participant_detail->label .' : '. $participant_detail->answer .'
';
                            }
                        }
        
        
                    }

                    curl_setopt_array($ch = curl_init(), array(
                    CURLOPT_URL => "https://api.pushover.net/1/messages.json",
                    CURLOPT_POSTFIELDS => array(
                        "token" => env('PUSHOVER_TOKEN'),
                        "user" => env('PUSHOVER_USER'),
                        "title" => $title,
                        "message" => $message,
                    ),
                    ));
                    curl_exec($ch);
                    curl_close($ch);
                    
                }
                
            }
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        return response('ERROR', 200)->header('Content-Type', 'text/plain');
    }
}
?>

