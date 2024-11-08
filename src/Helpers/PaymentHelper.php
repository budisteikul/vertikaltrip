<?php
namespace budisteikul\vertikaltrip\Helpers;

use budisteikul\vertikaltrip\Helpers\PaypalHelper;
use budisteikul\vertikaltrip\Helpers\XenditHelper;
use budisteikul\vertikaltrip\Helpers\StripeHelper;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PaymentHelper {
    
    public static function save_netPayment($shoppingcart)
    {
        $net = 0;
        $provider = $shoppingcart->shoppingcart_payment->payment_provider;
        if($provider=="stripe")
        {
            $stripe = New StripeHelper;
            $net = $stripe->getNet($shoppingcart);
        }

        if($provider=="xendit")
        {
            $xendit = New XenditHelper;
            $net = $xendit->getNet($shoppingcart);
        }

        if($provider=="paypal")
        {
            $paypal = New PaypalHelper;
            $net = $paypal->getNet($shoppingcart);
        }

        $shoppingcart->shoppingcart_payment->net = $net;
        $shoppingcart->shoppingcart_payment->save();
    }

    public static function set_paymentStatus($sessionId,$payment_status='')
    {
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $shoppingcart->payment->payment_status = $payment_status;
        BookingHelper::save_shoppingcart($sessionId,$shoppingcart);
        return $shoppingcart;
    }

    public static function have_payment($shoppingcart)
    {
        $status = false;
        if(isset($shoppingcart->shoppingcart_payment))
        {
            $status = true;
        }
        return $status;
    }

    public static function get_paymentStatus($shoppingcart)
    {
        if(self::have_payment($shoppingcart))
        {
            

            if($shoppingcart->shoppingcart_payment->payment_provider=="none")
            {
                switch($shoppingcart->shoppingcart_payment->payment_status)
                {
                    
                    case 2:
                        return '<div class="card mb-4">
                                <span class="badge badge-success invoice-color-success" style="font-size:20px;"><i class="fa fa-check-circle" aria-hidden="true"></i> PAID </span>
                                </div>';
                    case 4:
                        return '<div class="card mb-4">
                                <span class="badge badge-info invoice-color-info" style="font-size:20px; ">
                                Waiting for payment</span>
                                </div>';
                    default:
                        return '<div class="card mb-4">
                            <span class="badge badge-danger invoice-color-danger" style="font-size:20px;"><i class="fa fa-times-circle" aria-hidden="true"></i>
 INVOICE CANCELED</span>
                            </div>';

                }
                
            }


            $text = '';
            if($shoppingcart->shoppingcart_payment->rate_from!=$shoppingcart->shoppingcart_payment->rate_to)
            {
                $text .= '<b>Total :</b> '.$shoppingcart->shoppingcart_payment->currency.' '. GeneralHelper::numberFormat($shoppingcart->shoppingcart_payment->amount,$shoppingcart->shoppingcart_payment->currency) .'<br />';
                $text .= '<b>Rate :</b> '. BookingHelper::get_rate($shoppingcart) .'<br />';
                $text = '<div class="card-body bg-light">'. $text .'</div>';
            }
            else
            {
                $text .= '<b>Total :</b> '.$shoppingcart->shoppingcart_payment->currency.' '. GeneralHelper::numberFormat($shoppingcart->shoppingcart_payment->amount,$shoppingcart->shoppingcart_payment->currency) .'<br />';
                $text = '<div class="card-body bg-light">'. $text .'</div>';
            }

            switch($shoppingcart->shoppingcart_payment->payment_status)
            {
                case 1:
                    return '
                                <div class="card mb-4">
                                <span class="badge badge-success invoice-color-success" style="font-size:20px;"><i class="fa fa-info-circle" aria-hidden="true"></i> AUTHORIZED </span>
                                '. $text .'
                                </div>';
                break;
                case 2:
                    return '
                                <div class="card mb-4">
                                <span class="badge badge-success invoice-color-success" style="font-size:20px;"><i class="fa fa-check-circle" aria-hidden="true"></i> PAID </span>
                                '. $text .'
                                </div>';
                break;
                case 3:
                    return '
                                <div class="card mb-4">
                                <span class="badge badge-danger invoice-color-danger" style="font-size:20px;"><i class="fa fa-times-circle" aria-hidden="true"></i>
 UNPAID </span>
                                '. $text .'
                                </div>';

                break;
                case 5:
                    return '
                                <div class="card mb-4">
                                <span class="badge badge-danger invoice-color-danger" style="font-size:20px;"><i class="fas fa-sync-alt"></i>
 REFUNDED </span>
                                '. $text .'
                                
                                </div>';
                break;
                case 4:
                    // ===========================================================
                    
                    if($shoppingcart->shoppingcart_payment->payment_type=="bank_transfer")
                    {
                        $amount_text = GeneralHelper::formatRupiah($shoppingcart->shoppingcart_payment->amount);
                        $account_number_text = 'Virtual Account Number';
                        
                        return '
                                <div class="card mb-1">
                                <span class="badge badge-info invoice-color-info" style="font-size:18px; ">
                                Waiting for payment <br /><b id="payment_timer" class="text-white"  style="font-size:12px; font-weight: lighter;"><i class="fa fa-spinner fa-spin fa-fw"></i></b></span>
                                </div>
                                <div class="card mb-4">
                                <input type="hidden" id="va_number" value="'. $shoppingcart->shoppingcart_payment->va_number .'">
                                <input type="hidden" id="va_total" value="'. $shoppingcart->shoppingcart_payment->amount .'">
                                <div class="card-body bg-light">

                                <div>Bank Name : </div>
                                <div class="mb-2"><b>'. Str::upper($shoppingcart->shoppingcart_payment->bank_name) .'</b></div>
                                <div>'. $account_number_text .' : </div>
                                <div class="mb-2"><b id="va">'. GeneralHelper::splitSpace($shoppingcart->shoppingcart_payment->va_number,4,0) .'</b> 
                                <button id="va_number_button" onclick="copyToClipboard(\'#va_number\')" title="Copied" data-toggle="tooltip" data-placement="right" data-trigger="click" class="btn btn-light btn-sm invoice-hilang"><i class="far fa-copy"></i></button>
                                
                                 </div>
                                <div>Total Bill : </div>
                                <div class="mb-2"><b>'. $amount_text .'</b> <button onclick="copyToClipboard(\'#va_total\')" id="va_total_button" data-toggle="tooltip" data-placement="right" title="Copied" data-trigger="click" class="btn btn-light btn-sm invoice-hilang"><i class="far fa-copy"></i></button></div>

                                
                                </div>
                                </div>
                                ';
                    }
                    
                    
                    // ===========================================================
                    if($shoppingcart->shoppingcart_payment->payment_type=="qrcode")
                    {
                        
                            return '
                                <div class="card mb-1">
                                <span class="badge badge-info invoice-color-info" style="font-size:18px; ">
                                Waiting for payment <br /><b id="payment_timer" class="text-white"  style="font-size:12px; font-weight: lighter;"><i class="fa fa-spinner fa-spin fa-fw"></i></b></span>
                                </div>
                                <div class="card mb-1 img-fluid invoice-hilang"  style="min-height:360px; ">
                                
                                <div class="card-img-overlay">
                                    <div class="row h-100">
                                        <div class="col-12 text-center">

                                            <img class="img-fluid border border-white mt-2" alt="QRIS LOGO" style="max-width:250px; height:30px; image-rendering: -webkit-optimize-contrast;" src="'.config('site.assets').'/img/payment/qris-logo.png">
                                            <br />
                                            <img class="img-fluid border border-white mt-2" alt="QRIS" style="max-width:250px; image-rendering: -webkit-optimize-contrast;" src="'. BookingHelper::generate_qrcode($shoppingcart) .' ">
                                            <br />
                                            
                                        </div>
                                    </div>
                                </div>
                                
                                </div>
                                <div class="card mb-4">
                                <a href="'. env("APP_API_URL") .'/qrcode/'.$shoppingcart->session_id.'/'. $shoppingcart->confirmation_code .'" type="button" class="invoice-hilang btn btn-success invoice-hilang ">or Download QRCODE <i class="fas fa-download"></i> </a>
                                </div>
                                ';
                        
                    }

                    if($shoppingcart->shoppingcart_payment->payment_type=="link")
                    {
                        return '
                            <div class="card mb-1">
                                <span class="badge badge-info invoice-color-info" style="font-size:18px; ">
                                Waiting for payment <br /><b id="payment_timer" class="text-white"  style="font-size:12px; font-weight: lighter;"><i class="fa fa-spinner fa-spin fa-fw"></i></b></span>
                                </div>
                                <div class="card mb-2 pb-2 invoice-hilang"  style="min-height:290px; ">
                                
                                <div class="card-img-overlay">
                                    <div class="row h-100">
                                        <div class="col-12 text-center">
                                            
                                            <div>

                                            <br />
                                            <i>We add a small amount to identify transaction</i>
                                            <br />
                                            Amount to pay : <b>'. $shoppingcart->shoppingcart_payment->currency .' '. GeneralHelper::numberFormat($shoppingcart->shoppingcart_payment->amount,'IDR') .'</b>
                                            <br />
                                             Click the button below and pay with correct amount
                                            <br />
                                            <br />
                                            <a href="https://wise.com/pay/business/vertikaltripllc?amount='. $shoppingcart->shoppingcart_payment->amount .'&currency='. $shoppingcart->shoppingcart_payment->currency .'"><img src="'.config('site.assets').'/img/payment/pww-button.svg"></a>

                                            <!-- br /><br />
                                            <small><a href="'.  env('APP_API_URL') .'/payment/change/'.$shoppingcart->session_id.'/'.$shoppingcart->confirmation_code.'" class="text-theme">Click here</a> to change payment method</small -->
                                            
                                            <br />
                                            </div>
                                            
                                        </div>
                                    </div>
                                </div>
                                </div>
                        ';
                    }

                    
                break;
                default:
                    return '';
            }


            
        }
        return '';
    }
    
    
    public static function create_refund($shoppingcart)
    {
        
        if($shoppingcart->shoppingcart_payment->payment_provider=="xendit")
        {
            $payment = new XenditHelper;
            $payment->createRefund($shoppingcart);
        }

        if($shoppingcart->shoppingcart_payment->payment_provider=="stripe")
        {
            $payment = new StripeHelper;
            $payment->createRefund($shoppingcart);
        }

        if($shoppingcart->shoppingcart_payment->payment_provider=="paypal")
        {
            $payment = new PaypalHelper;
            $payment->createRefund($shoppingcart);
        }

        $shoppingcart->shoppingcart_payment->payment_status = 5;
        $shoppingcart->shoppingcart_payment->save();
    }

    public static function create_payment($sessionId,$payment_provider="none",$bank="",$param1="")
    {
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);

        $first_name = BookingHelper::get_answer($shoppingcart,'firstName');
        $last_name = BookingHelper::get_answer($shoppingcart,'lastName');
        $email = BookingHelper::get_answer($shoppingcart,'email');
        $phone = BookingHelper::get_answer($shoppingcart,'phoneNumber');

        $contact = new \stdClass();
        $contact->first_name = $first_name;
        $contact->last_name = $last_name;
        $contact->name = $first_name .' '. $last_name;
        $contact->email = $email;
        $contact->phone = $phone;
        
        $due_date = BookingHelper::due_date($shoppingcart);

        $date1 = Carbon::now();
        $date2 = Carbon::parse($due_date);
        $second_expired = $date2->diffInSeconds($date1, true);
        $mins_expired  = $date2->diffInMinutes($date1, true);
        $date_expired = Carbon::parse($due_date)->format('Y-m-d H:i:s');
        $date_now = Carbon::parse($date1)->format('Y-m-d H:i:s');

        $response = NULL;
        $payment_type = NULL;
        $bank_name = NULL;
        $bank_code = NULL;
        $va_number = NULL;
        $payment_description = NULL;
        $qrcode = NULL;
        $link = NULL;
        $authorization_id = NULL;

        $order_id = Uuid::uuid4()->toString();
        $amount = $shoppingcart->due_now;
        $currency = $shoppingcart->currency;
        $rate = 1;
        $rate_from = $shoppingcart->currency;
        $rate_to = $shoppingcart->currency;
        $expiration_date = $date_expired;
        $payment_status = 0;
        if($shoppingcart->booking_status=="CONFIRMED") $payment_status = 2;
        $redirect = '/booking/receipt/'. $sessionId .'/'. $shoppingcart->confirmation_code;


        $transaction = new \stdClass();
        $transaction->id = $order_id;
        $transaction->amount = $amount;
        $transaction->currency = $currency;
        $transaction->confirmation_code = $shoppingcart->confirmation_code;
        $transaction->payment_provider = $payment_provider;
        $transaction->bank = $bank;
        $transaction->second_expired = $second_expired;
        $transaction->mins_expired = $mins_expired;
        $transaction->date_expired = $date_expired;
        $transaction->date_now = $date_now;
        $transaction->finish_url = $redirect;
        $transaction->finish_url_full = $shoppingcart->url . $redirect;

        //============================================
        $products = array();
        foreach($shoppingcart->products as $product)
        {
            foreach($product->product_details as $product_detail)
            {
                $products[] = [
                    'title' => $product_detail->title,
                    'price' => $product_detail->price,
                    'unit' => $product_detail->unit_price,
                    'qty' => $product_detail->qty,
                    'subtotal' => $product_detail->subtotal,
                    'discount' => $product_detail->discount,
                    'total' => $product_detail->total,
                ];
            }
        }
        $transaction->products = $products;
        //============================================


        $data = new \stdClass();
        $data->contact = $contact;
        $data->transaction = $transaction;

        if($data->transaction->mins_expired==0) {
            $data->transaction->second_expired = 3600;
            $data->transaction->mins_expired = 60;
            $data->transaction->date_expired = Carbon::parse($data->transaction->date_now)->addMinutes($data->transaction->mins_expired);
        }

        switch($payment_provider)
        {
            case "xendit":

                $payment_provider = 'xendit';
                $currency = 'IDR';

                $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,$currency);
                $rate = number_format((float)$shoppingcart->due_now / $amount, 2, '.', '');
                $rate_from = $shoppingcart->currency;
                $rate_to = $currency;

                $data->transaction->param1 = $param1;
                $data->transaction->amount = round($amount);
                $data->transaction->currency = $currency;

                
                if($data->transaction->bank == 'bss')
                {
                    $payment_type = 'bank_transfer';
                    $bank_name = 'Bank Sahabat Sampoerna';
                    $bank_code = '523';
                    $payment_status = 4;
                    $response = XenditHelper::createPayment($data);
                }

                if($data->transaction->bank == 'qris')
                {
                    $payment_type = 'qrcode';
                    $bank_name = 'qris';
                    $payment_status = 4;
                    $response = XenditHelper::createPayment($data);
                }

                if($data->transaction->bank == 'invoice')
                {
                    $payment_type = 'bank_redirect';
                    $payment_status = 4;
                    $response = XenditHelper::createPayment($data);
                }

                if($data->transaction->bank == 'card')
                {
                    $payment_type = 'card';
                    $payment_status = 0;
                    $response = XenditHelper::createPayment($data);
                }



            break;
            case "wise":
                $payment_provider = 'wise';
                $currency = 'IDR';

                $smallamount = substr($shoppingcart->confirmation_code,9,3);

                $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,$currency)+(float)$smallamount;
                $rate = number_format((float)$shoppingcart->due_now / $amount, 2, '.', '');
                $rate_from = $shoppingcart->currency;
                $rate_to = $currency;
                $payment_type = 'link';
                $bank_name = 'wise';
                $payment_status = 4;
                $expiration_date = Carbon::parse($date_now)->addMinutes(30);

                $response = new \stdClass();
                $status_json = new \stdClass();

                $status_json->id = 1;
                $status_json->message = 'success';

                $response->status = $status_json;
                $response->data = null;

            break;
            case "paypal":

                $payment_provider = 'paypal';
                $currency = env("PAYPAL_CURRENCY");

                $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,$currency);
                $rate = number_format((float)$shoppingcart->due_now / $amount, 2, '.', '');
                $rate_from = $shoppingcart->currency;
                $rate_to = $currency;

                $data->transaction->amount = $amount;
                $data->transaction->currency = $currency;

                $payment_status = 0;

                $response = PaypalHelper::createPayment($data);

            break;
            case "stripe":
            
                $payment_provider = 'stripe';
                $currency = 'USD';

                $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,$currency);
                $rate = number_format((float)$shoppingcart->due_now / $amount, 2, '.', '');
                $rate_from = $shoppingcart->currency;
                $rate_to = $currency;

                $data->transaction->amount = $amount;
                $data->transaction->currency = $currency;

                $payment_status = 0;

                $response = StripeHelper::createPayment($data);

            break;
            default:
                $response = new \stdClass();
                $status_json = new \stdClass();

                $status_json->id = 1;
                $status_json->message = 'success';

                $response->status = $status_json;
                $response->data = null;
        }

        if($response->status->id=="0")
        {
            unset($response->data);
            return $response;
        }
        
        if(isset($response->data->payment_type)) $payment_type = $response->data->payment_type;
        if(isset($response->data->currency)) $currency = $response->data->currency;
        if(isset($response->data->rate)) $rate = $response->data->rate;
        if(isset($response->data->rate_from)) $rate_from = $response->data->rate_from;
        if(isset($response->data->rate_to)) $rate_to = $response->data->rate_to;
        if(isset($response->data->bank_name)) $bank_name = $response->data->bank_name;
        if(isset($response->data->bank_code)) $bank_code = $response->data->bank_code;
        if(isset($response->data->va_number)) $va_number = $response->data->va_number;
        if(isset($response->data->qrcode)) $qrcode = $response->data->qrcode;
        if(isset($response->data->link)) $link = $response->data->link;
        if(isset($response->data->redirect)) $redirect = $response->data->redirect;
        if(isset($response->data->expiration_date)) $expiration_date = $response->data->expiration_date;
        if(isset($response->data->order_id)) $order_id = $response->data->order_id;
        if(isset($response->data->authorization_id)) $authorization_id = $response->data->authorization_id;
        if(isset($response->data->amount)) $amount = $response->data->amount;
        if(isset($response->data->payment_description)) $payment_description = $response->data->payment_description;
        if(isset($response->data->payment_status)) $payment_status = $response->data->payment_status;

        $ShoppingcartPayment = (object) array(
            'payment_provider' => $payment_provider,
            'payment_type' => $payment_type,
            'bank_name' => $bank_name,
            'bank_code' => $bank_code,
            'va_number' => $va_number,
            'qrcode' => $qrcode,
            'link' => $link,
            'redirect' => $redirect,
            'order_id' => $order_id,
            'authorization_id' => $authorization_id,
            'amount' => $amount,
            'currency' => $currency,
            'rate' => $rate,
            'rate_from' => $rate_from,
            'rate_to' => $rate_to,
            'expiration_date' => $expiration_date,
            'payment_description' => $payment_description,
            'payment_status' => $payment_status,
        );

        $shoppingcart->payment = $ShoppingcartPayment;
        BookingHelper::save_shoppingcart($sessionId,$shoppingcart);

        return $response;
    }





}
?>