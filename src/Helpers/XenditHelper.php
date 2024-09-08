<?php
namespace budisteikul\vertikaltrip\Helpers;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;
use Carbon\Carbon;
use budisteikul\vertikaltrip\Helpers\LogHelper;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;

class XenditHelper {

    private $xendit;

    public function __construct() {

    	$this->xendit = new \stdClass();
    	$this->xendit->secret_key = env("XENDIT_SECRET_KEY");
        $this->xendit->endpoint = 'https://api.xendit.co';
    }

    public static function createPayment($data)
    {
        $data_json = new \stdClass();
        $status_json = new \stdClass();
        $response_json = new \stdClass();

        if($data->transaction->bank=="qris")
        {
            $data->transaction->mins_expired = 30;
            $data->transaction->date_expired = Carbon::parse($data->transaction->date_now)->addMinutes($data->transaction->mins_expired);

            $expired_at = GeneralHelper::dateFormat($data->transaction->date_expired,12);
            $data1 = (new self)->createQrcode($data->transaction->amount,$expired_at);

            if(isset($data1->error_code))
            {
                $status_json->id = '0';
                $status_json->message = 'error';
            }
            else
            {
                $data_json->authorization_id = $data1->id;
                $data_json->order_id = $data1->reference_id;
                $data_json->qrcode = $data1->qr_string;

                $status_json->id = '1';
                $status_json->message = 'success';
            }
        }

        if($data->transaction->bank=="bss")
        {
            $data->transaction->mins_expired = 30;
            $data->transaction->date_expired = Carbon::parse($data->transaction->date_now)->addMinutes($data->transaction->mins_expired);

            $expired_at = GeneralHelper::dateFormat($data->transaction->date_expired,12);
            $name = $data->contact->name;
            $bank_code = 'SAHABAT_SAMPOERNA';

            $data1 = (new self)->createVirtualAccount($bank_code,$data->transaction->amount,$name,$expired_at);

            if(isset($data1->error_code))
            {
                $status_json->id = '0';
                $status_json->message = 'error';
            }
            else
            {
                $data_json->authorization_id = $data1->id;
                $data_json->va_number = $data1->account_number;
                $data_json->order_id = $data1->external_id;

                $status_json->id = '1';
                $status_json->message = 'success';
            }
        }

        if($data->transaction->bank=="invoice")
        {
            
            $data1 = (new self)->createInvoice($data->transaction->amount,$data->transaction->param1,$data->transaction->second_expired);

            if(isset($data1->error_code))
            {
                $status_json->id = '0';
                $status_json->message = 'error';
            }
            else
            {
                $data_json->redirect = $data1->invoice_url;
                $data_json->authorization_id = $data1->id;
                $data_json->order_id = $data1->external_id;
                $data_json->success_redirect_url = $data->transaction->finish_url_full;
                $data_json->failure_redirect_url = $data->transaction->finish_url_full;

                $status_json->id = '1';
                $status_json->message = 'success';
            }
        }

        if($data->transaction->bank=="card")
        {
            $data_json = new \stdClass();
            $status_json = new \stdClass();
            $response_json = new \stdClass();
            
            

            $data1 = (new self)->createChargeCard($data->transaction->param1,$data->transaction->amount);
            LogHelper::log($data1,'xdt-charge');

            if($data1->status=="CAPTURED")
            {
                 $status_json->id = '1';
                 $status_json->message = $data1;
                 $data_json->authorization_id = $data1->id;
                 $data_json->order_id = $data1->external_id;
                 $data_json->payment_status = 2;
            }
            else
            {
                 $status_json->id = '0';
                 $message = 'Failed to charge card. Please change to another payment method and try again';
                 if($data1->failure_reason=="EXPIRED_CARD") $message = 'The card has expired.';
                 if($data1->failure_reason=="INSUFFICIENT_BALANCE") $message = 'The card does not have enough balance.';
                 if($data1->failure_reason=="INVALID_CVV") $message = 'The card is declined due to unmatched CVV / CVC.';
                 $status_json->message = $message;
            }

        }

        $data_json->expiration_date = $data->transaction->date_expired;
        
        $response_json->status = $status_json;
        $response_json->data = $data_json;

        return $response_json;
    }


    public static function createForm($sessionId)
    {
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $first_name = BookingHelper::get_answer($shoppingcart,'firstName');
        $last_name = BookingHelper::get_answer($shoppingcart,'lastName');
        $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,'IDR');
        
        $billing_form = '
            <div id="billing_form1" class="row no-gutters mt-2">
                <div class="col-md-12 mb-2">
                    <h2 class=" mt-2">Cardholder Information</h2>
                </div>
                <div class="col-md-6 mb-2 pr-1">
                    <label for="cc-givenName"><strong>First name</strong></label>
                    <input value="'.$first_name.'" type="text" class="form-control" id="cc-givenName" required="" placeholder="Given Name" style="height: 47px;border-radius: 0;">
                    <div id="givenNameFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-6 mb-2 pr-1">
                    <label for="cc-surname"><strong>Last name</strong></label>
                    <input value="'.$last_name.'" type="text" class="form-control" id="cc-surname" required="" placeholder="Last Name"  style="height: 47px;border-radius: 0;">
                    <div id="lastNameFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-streetLine1"><strong>Street line 1</strong></label>
                    <input type="text" class="form-control" id="cc-streetLine1" required="" placeholder="Address" style="height: 47px;border-radius: 0;">
                    <div id="streetLineFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-postalCode"><strong>Postal code</strong></label>
                    <input type="text" class="form-control" id="cc-postalCode" required="" placeholder="Postal code" style="height: 47px;border-radius: 0;">
                    <div id="zipCodeFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
            </div>

        ';


        $payment_container = '
        <hr />
        <form id="payment-form">

            <div class="row">
                <div class="col-md-12 mb-2">
                    <h2 class=" mt-2">Card Information</h2>
                </div>
                <div class="col-md-12 mb-2">
                    <label for="card-number"><strong>Card number</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i id="cardBrand" class="far fa-credit-card fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input class="form-control" type="text" id="card-number" placeholder="Card number" value="" style="height: 47px;border-radius: 0;" onKeyUp="return checkCardNumber();">
                        <div id="cardNumberFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
            </div>

            <div class="row no-gutters">
                <div class="col-md-6 mb-2">
                    <label for="cc-expiration"><strong>Valid thru</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="far fa-calendar fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="cc-expiration" placeholder="MM / YY" required="" style="height: 47px;border-radius: 0;" onKeyUp="return checkExpiration();">
                        <div id="expirationFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <label for="cc-cvv"><strong>CVV / CVN / CVC</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-key fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="cc-cvv" placeholder="3-4 digits code" required="" style="height: 47px;border-radius: 0;" onKeyUp="return checkCvv();">
                        <div id="cvvFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
            </div>

            <div id="billing_form"></div>
            

            <button style="height:47px;" class="mt-2 btn btn-lg btn-block btn-theme" id="submit">
                <i class="fas fa-lock"></i> <strong>Pay with card</strong>
            </button>

            <div id="change_payment" class="mt-2">
                <center>
                    <small><a href="#paymentMethod" class="text-theme" onClick="changePaymentMethod();">Click here</a> to change payment method</small>
                </center>
            </div>

        </form>

        <div id="loader" class="mb-4"></div>
        <div id="text-alert" class="text-center"></div>
        <div id="three-ds-container" class="modal" style="display: none;"></div>
        ';

        

        $jscript = '


        $("#submitCheckout").slideUp("slow");
        $("#paymentContainer").html(\''. str_replace(array("\r", "\n"), '', $payment_container) .'\');
        

        payform.cardNumberInput(document.getElementById("card-number"));
        payform.expiryInput(document.getElementById("cc-expiration"));
        payform.cvcInput(document.getElementById("cc-cvv"));

        $(\'#card-number\').on(\'input\', function() {
            if($(\'#card-number\').val().length >=8)
            {
                var card_brand = payform.parseCardType($(\'#card-number\').val());
                if(card_brand=="visa")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-visa  fa-lg fa-fw\');
                }
                else if(card_brand=="mastercard")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-mastercard  fa-lg fa-fw\');
                }
                else if(card_brand=="jcb")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-jcb  fa-lg fa-fw\');
                }
                else
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'far\').addClass(\'fa-credit-card  fa-lg fa-fw\');
                }
            }
            else
            {
                $("#cardBrand").removeClass();
                $("#cardBrand").addClass(\'far\').addClass(\'fa-credit-card  fa-lg fa-fw\');
            }
        });

        function xenditResponseHandler (err, creditCardToken) {
            if (err) {
                enableButton();
                var error_message = err.message;
                $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ error_message +\'</h2></div>\');
                $(\'#alert-payment\').fadeIn("slow");
                return;
            }

            if (creditCardToken.status === "APPROVED" || creditCardToken.status === "VERIFIED") {
                            $("#three-ds-container").hide();
                            $("#loader").show();
                            $("#payment-form").slideUp("slow");  
                            $("#proses").hide();
                            $("#loader").addClass("loader");
                            $("#text-alert").show();
                            $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );
                            
                            postBilling(creditCardToken.id,creditCardToken.card_info.country);

                            $.ajax({
                                beforeSend: function(request) {
                                    request.setRequestHeader(\'sessionId\', \''. $shoppingcart->session_id .'\');
                                    request.setRequestHeader(\'tokenId\', creditCardToken.id);
                                },
                                type: \'POST\',
                                url: \''. env('APP_API_URL') .'/payment/xendit\'
                            }).done(function( data ) {
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
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $("#payment-form").slideDown("slow");
                                    enableButton();
                                    $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><strong style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ data.message +\'</strong></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                }
                            });
            } else if (creditCardToken.status === "IN_REVIEW") {

                            $("#three-ds-container").hide();
                            $("#three-ds-container").html("<iframe id=\"3ds-inline-frame\" name=\"3ds-inline-frame\" scrolling=\"no\"></iframe>");
                            $("#3ds-inline-frame").css("background-color", "#FFFFFF");
                            $("#3ds-inline-frame").css("top", "0px");
                            $("#3ds-inline-frame").css("left", "0px");
                            $("#3ds-inline-frame").css("width", "100%");
                            $("#3ds-inline-frame").css("height", "100%");
                            $("#3ds-inline-frame").css("position", "absolute");
                            window.open(creditCardToken.payer_authentication_url, "3ds-inline-frame");
                            $("#three-ds-container").show();
            } else if (creditCardToken.status === "FRAUD") {
                            enableButton();
            } else if (creditCardToken.status === "FAILED") {

                            enableButton();
                            var error_message = creditCardToken.failure_reason;

                            if(creditCardToken.failure_reason=="AUTHENTICATION_FAILED")
                            {
                                error_message = "Authentication Failed";
                            }
                            
                            $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ error_message +\'</h2></div>\');
                            $(\'#alert-payment\').fadeIn("slow");
                           
            }
        }
        
        function randomNumber()
        {
            var randomNumber = Date.now() + Math.random();
            randomNumber = randomNumber.toString().replace(".","");
            return randomNumber;
        }

        function cleanFeedback()
        {
            $("#card-number").removeClass("is-invalid");
            $("#cc-expiration").removeClass("is-invalid");
            $("#cc-cvv").removeClass("is-invalid");
        }

        
        function addBillingForm()
        {
            $("#billing_form").html(\''. str_replace(array("\r", "\n"), '', $billing_form) .'\');
            
        }
        function removeBillingForm()
        {
            $("#billing_form").html(\'\');
            
        }

        

        var cardNumber_keypress = false;
        var expiration_keypress = false;
        var cvv_keypress = false;
        var oldBin = "";
        $("#card-number").on("blur", function() {
            var cardNumber = $("#card-number").val();
            cardNumber_keypress = true;
            if(!payform.validateCardNumber(cardNumber))
            {
                $("#card-number").addClass("is-invalid");
                return;
            }
            else
            {
                $("#card-number").removeClass("is-invalid");
                cardNumber_keypress = false;
            }
            if (oldBin != this.value) {
                checkBin();
                oldBin = this.value;
            }
        });
        
        function checkCardNumber()
        {
            if(cardNumber_keypress)
            {
                var cardNumber = $("#card-number").val();
                if(!payform.validateCardNumber(cardNumber))
                {
                    $("#card-number").addClass("is-invalid");
                }
                else
                {
                    $("#card-number").removeClass("is-invalid");
                    if (oldBin != this.value) {
                        checkBin();
                        oldBin = this.value;
                    }
                }
            }
        }

        $("#cc-expiration").on("blur", function() {
            var expiry = $("#cc-expiration").val();
            expiration_keypress = true;
            var expiryArray = expiry.split("/");
            if(expiryArray.length>1)
            {
                
                var expiryMonth = expiryArray[0].trim();
                var expiryYear = expiryArray[1].trim();
                
                if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                {
                    $("#cc-expiration").addClass("is-invalid");
                }
                else
                {
                    $("#cc-expiration").removeClass("is-invalid");
                    expiration_keypress = false;
                }
            }
            else
            {
                $("#cc-expiration").addClass("is-invalid");
            }
        });

        function checkExpiration()
        {
            if(expiration_keypress)
            {
                var expiry = $("#cc-expiration").val();
                var expiryArray = expiry.split("/");
                if(expiryArray.length>1)
                {  
                    var expiryMonth = expiryArray[0].trim();
                    var expiryYear = expiryArray[1].trim();
                    if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                    {
                        $("#cc-expiration").addClass("is-invalid");
                    }
                    else
                    {
                        $("#cc-expiration").removeClass("is-invalid");
                    }
                }
                else
                {
                    $("#cc-expiration").addClass("is-invalid");
                }
            }
        }

        $("#cc-cvv").on("blur", function() {
            var cvvNumber = $("#cc-cvv").val();
            cvv_keypress = true;
            if(!payform.validateCardCVC(cvvNumber))
            {
                $("#cc-cvv").addClass("is-invalid");
            }
            else
            {
                $("#cc-cvv").removeClass("is-invalid");
                cvv_keypress = false;
            }
        });

        function checkCvv()
        {
            if(cvv_keypress)
            {
                var cvvNumber = $("#cc-cvv").val();
                if(!payform.validateCardCVC(cvvNumber))
                {
                    $("#cc-cvv").addClass("is-invalid");
                }
                else
                {
                    $("#cc-cvv").removeClass("is-invalid");
                }
            }
        }




        function checkBin()
        {
            
            var cardNumber = $("#card-number").val();
            cardNumber = cardNumber.replace(/\s/g,"").trim();
            var bin = cardNumber.substring(0, 8);

            if(bin.length!=8)
            {
                removeBillingForm();
                return false;
            }


            $.ajax({
                    url: "'. env('APP_API_URL') .'/tool/bin",
                    method: "POST",
                    data: { 
                        bin: bin
                    },
                }).done(function(response) {
                    var country_code = response.country_code;
                    if(country_code == "US" || country_code == "CA" || country_code == "GB")
                    {
                        addBillingForm();
                    }
                    else
                    {
                        removeBillingForm();
                    }
                }).fail(function( jqXHR, textStatus ) {

                });
            
            
        }



        function postBilling(id,country)
        {
            if($("#billing_form1").length==1)
            {
                var givenName = $("#cc-givenName").val().trim();
                var surname = $("#cc-surname").val().trim();
                var streetLine1 = $("#cc-streetLine1").val().trim();
                var postalCode = $("#cc-postalCode").val().trim();

                $.ajax({
                    url: "'. env('APP_API_URL') .'/tool/billing/"+ id,
                    method: "POST",
                    data: { 
                        tokenId: id,
                        givenName: givenName,
                        surname: surname,
                        streetLine1: streetLine1,
                        postalCode: postalCode,
                        country: country
                    },
                }).done(function(response) {
  
                }).fail(function( jqXHR, textStatus ) {

                });
            }
            
        }

        function enableButton()
        {
            $("#three-ds-container").hide();
            $("#card-number").attr("disabled", false);
            $("#cc-expiration").attr("disabled", false);
            $("#cc-cvv").attr("disabled", false);
            $("#cc-givenName").attr("disabled", false);
            $("#cc-surname").attr("disabled", false);
            $("#cc-streetLine1").attr("disabled", false);
            $("#cc-postalCode").attr("disabled", false);
            $("#loader").hide();
            $("#loader").removeClass("loader");
            $("#payment-form").slideDown("slow");
            $("#submit").attr("disabled", false);
            $("#submit").html(\'<i class="fas fa-lock"></i> <strong>Pay with card</strong>\');
        }

        var form = document.getElementById(\'payment-form\');
        form.addEventListener(\'submit\', function(ev) {
                ev.preventDefault();
                cleanFeedback();
                
                $("#alert-payment").slideUp("slow");
                $("#submit").attr("disabled", true);
                $("#submit").html(\' <i class="fa fa-spinner fa-spin fa-fw"></i>  processing... \');

                Xendit.setPublishableKey("'. env("XENDIT_PUBLIC_KEY") .'");
                
                var cardNumber = $("#card-number").val();
                var expiry = $("#cc-expiration").val();
                var expiryArray = expiry.split("/");
                var expiryMonth = expiryArray[0].trim();
                var expiryYear = expiryArray[1].trim();
                var cvvNumber = $("#cc-cvv").val();

                var external_id = randomNumber();
                
                if(expiryYear.length==2)
                {
                    expiryYear = "'. substr(date('Y'),0,2) .'"+ expiryYear;
                }

                if(!payform.validateCardNumber(cardNumber))
                {
                    $("#card-number").addClass("is-invalid");
                    enableButton();
                    return false;
                }
            
                if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                {
                    $("#cc-expiration").addClass("is-invalid");
                    enableButton();
                    return false;
                }

                if(!payform.validateCardCVC(cvvNumber))
                {
                    $("#cc-cvv").addClass("is-invalid");
                    enableButton();
                    return false;
                }


                $("#card-number").attr("disabled", true);
                $("#cc-expiration").attr("disabled", true);
                $("#cc-cvv").attr("disabled", true);

                $("#cc-givenName").attr("disabled", true);
                $("#cc-surname").attr("disabled", true);
                $("#cc-streetLine1").attr("disabled", true);
                $("#cc-postalCode").attr("disabled", true);

                cardNumber = cardNumber.replace(/\s/g,"");
                expiryMonth = expiryMonth.trim();
                expiryYear = expiryYear.trim();
                cvvNumber = cvvNumber.trim();

                Xendit.card.createToken({
                    amount: '.$amount.',
                    card_number: cardNumber,
                    card_exp_month: expiryMonth,
                    card_exp_year: expiryYear,
                    card_cvn: cvvNumber,
                    is_multiple_use: false,
                    external_id: external_id
                }, xenditResponseHandler);

                return false;
            });
        

        ';
        return $jscript;
    }

    public static function createFormV2($sessionId)
    {
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $first_name = BookingHelper::get_answer($shoppingcart,'firstName');
        $last_name = BookingHelper::get_answer($shoppingcart,'lastName');
        $email = BookingHelper::get_answer($shoppingcart,'email');
        $phoneNumber = BookingHelper::get_answer($shoppingcart,'phoneNumber');
        $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,'IDR');
        
        $billing_form = '
            <div id="billing_form1" class="row no-gutters mt-2">
                <div class="col-md-12 mb-2">
                    <h2 class=" mt-2">Cardholder Information</h2>
                </div>
                <div class="col-md-6 mb-2 pr-1">
                    <label for="cc-givenName"><strong>First name</strong></label>
                    <input value="'.$first_name.'" type="text" class="form-control" id="cc-givenName" required="" placeholder="Given Name" style="height: 47px;border-radius: 0;">
                    <div id="givenNameFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-6 mb-2 pr-1">
                    <label for="cc-surname"><strong>Last name</strong></label>
                    <input value="'.$last_name.'" type="text" class="form-control" id="cc-surname" required="" placeholder="Last Name"  style="height: 47px;border-radius: 0;">
                    <div id="lastNameFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-streetLine1"><strong>Street line 1</strong></label>
                    <input type="text" class="form-control" id="cc-streetLine1" required="" placeholder="Address" style="height: 47px;border-radius: 0;">
                    <div id="streetLineFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-postalCode"><strong>Postal code</strong></label>
                    <input type="text" class="form-control" id="cc-postalCode" required="" placeholder="Postal code" style="height: 47px;border-radius: 0;">
                    <div id="zipCodeFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
            </div>

        ';


        $payment_container = '
        <hr />
        <form id="payment-form">

            <div class="row">
                <div class="col-md-12 mb-2">
                    <h2 class=" mt-2">Card Information</h2>
                </div>
                <div class="col-md-12 mb-2">
                    <label for="card-number"><strong>Card number</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i id="cardBrand" class="far fa-credit-card fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input class="form-control" type="text" id="card-number" placeholder="Card number" value="" style="height: 47px;border-radius: 0;" onKeyUp="return checkCardNumber();">
                        <div id="cardNumberFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
            </div>

            <div class="row no-gutters">
                <div class="col-md-6 mb-2">
                    <label for="cc-expiration"><strong>Valid thru</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="far fa-calendar fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="cc-expiration" placeholder="MM / YY" required="" style="height: 47px;border-radius: 0;" onKeyUp="return checkExpiration();">
                        <div id="expirationFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <label for="cc-cvv"><strong>CVV / CVN / CVC</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-key fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="cc-cvv" placeholder="3-4 digits code" required="" style="height: 47px;border-radius: 0;" onKeyUp="return checkCvv();">
                        <div id="cvvFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
            </div>

            <div id="billing_form"></div>
            

            <button style="height:47px;" class="mt-2 btn btn-lg btn-block btn-theme" id="submit">
                <i class="fas fa-lock"></i> <strong>Pay with card</strong>
            </button>

            <div id="change_payment" class="mt-2">
                <center>
                    <small><a href="#paymentMethod" class="text-theme" onClick="changePaymentMethod();">Click here</a> to change payment method</small>
                </center>
            </div>

        </form>

        <div id="loader" class="mb-4"></div>
        <div id="text-alert" class="text-center"></div>
        <div id="three-ds-container" class="modal" style="display: none;"></div>
        ';

        

        $jscript = '


        $("#submitCheckout").slideUp("slow");
        $("#paymentContainer").html(\''. str_replace(array("\r", "\n"), '', $payment_container) .'\');
        

        payform.cardNumberInput(document.getElementById("card-number"));
        payform.expiryInput(document.getElementById("cc-expiration"));
        payform.cvcInput(document.getElementById("cc-cvv"));

        $(\'#card-number\').on(\'input\', function() {
            if($(\'#card-number\').val().length >=8)
            {
                var card_brand = payform.parseCardType($(\'#card-number\').val());
                if(card_brand=="visa")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-visa  fa-lg fa-fw\');
                }
                else if(card_brand=="mastercard")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-mastercard  fa-lg fa-fw\');
                }
                else if(card_brand=="jcb")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-jcb  fa-lg fa-fw\');
                }
                else
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'far\').addClass(\'fa-credit-card  fa-lg fa-fw\');
                }
            }
            else
            {
                $("#cardBrand").removeClass();
                $("#cardBrand").addClass(\'far\').addClass(\'fa-credit-card  fa-lg fa-fw\');
            }
        });

        function xenditResponseHandler (err, creditCardToken) {
            if (err) {
                enableButton();
                var error_message = err.message;
                $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ error_message +\'</h2></div>\');
                $(\'#alert-payment\').fadeIn("slow");
                return;
            }

            if (creditCardToken.status === "APPROVED" || creditCardToken.status === "VERIFIED") {
                            $("#three-ds-container").hide();
                            $("#loader").show();
                            $("#payment-form").slideUp("slow");  
                            $("#proses").hide();
                            $("#loader").addClass("loader");
                            $("#text-alert").show();
                            $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );
                            
                            postBilling(creditCardToken.id,creditCardToken.card_info.country);

                            $.ajax({
                                beforeSend: function(request) {
                                    request.setRequestHeader(\'sessionId\', \''. $shoppingcart->session_id .'\');
                                    request.setRequestHeader(\'tokenId\', creditCardToken.id);
                                },
                                type: \'POST\',
                                url: \''. env('APP_API_URL') .'/payment/xendit\'
                            }).done(function( data ) {
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
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $("#payment-form").slideDown("slow");
                                    enableButton();
                                    $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><strong style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ data.message +\'</strong></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                }
                            });
            } else if (creditCardToken.status === "IN_REVIEW") {

                            $("#three-ds-container").hide();
                            $("#three-ds-container").html("<iframe id=\"3ds-inline-frame\" name=\"3ds-inline-frame\" scrolling=\"no\"></iframe>");
                            $("#3ds-inline-frame").css("background-color", "#FFFFFF");
                            $("#3ds-inline-frame").css("top", "0px");
                            $("#3ds-inline-frame").css("left", "0px");
                            $("#3ds-inline-frame").css("width", "100%");
                            $("#3ds-inline-frame").css("height", "100%");
                            $("#3ds-inline-frame").css("position", "absolute");
                            window.open(creditCardToken.payer_authentication_url, "3ds-inline-frame");
                            $("#three-ds-container").show();
            } else if (creditCardToken.status === "FRAUD") {
                            enableButton();
            } else if (creditCardToken.status === "FAILED") {

                            enableButton();
                            var error_message = creditCardToken.failure_reason;

                            if(creditCardToken.failure_reason=="AUTHENTICATION_FAILED")
                            {
                                error_message = "Authentication Failed";
                            }
                            
                            $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ error_message +\'</h2></div>\');
                            $(\'#alert-payment\').fadeIn("slow");
                           
            }
        }
        
        function randomNumber()
        {
            var randomNumber = Date.now() + Math.random();
            randomNumber = randomNumber.toString().replace(".","");
            return randomNumber;
        }

        function cleanFeedback()
        {
            $("#card-number").removeClass("is-invalid");
            $("#cc-expiration").removeClass("is-invalid");
            $("#cc-cvv").removeClass("is-invalid");
        }

        
        function addBillingForm()
        {
            $("#billing_form").html(\''. str_replace(array("\r", "\n"), '', $billing_form) .'\');
            
        }
        function removeBillingForm()
        {
            $("#billing_form").html(\'\');
            
        }

        

        var cardNumber_keypress = false;
        var expiration_keypress = false;
        var cvv_keypress = false;
        var oldBin = "";
        $("#card-number").on("blur", function() {
            var cardNumber = $("#card-number").val();
            cardNumber_keypress = true;
            if(!payform.validateCardNumber(cardNumber))
            {
                $("#card-number").addClass("is-invalid");
                return;
            }
            else
            {
                $("#card-number").removeClass("is-invalid");
                cardNumber_keypress = false;
            }
            if (oldBin != this.value) {
                checkBin();
                oldBin = this.value;
            }
        });
        
        function checkCardNumber()
        {
            if(cardNumber_keypress)
            {
                var cardNumber = $("#card-number").val();
                if(!payform.validateCardNumber(cardNumber))
                {
                    $("#card-number").addClass("is-invalid");
                }
                else
                {
                    $("#card-number").removeClass("is-invalid");
                    if (oldBin != this.value) {
                        checkBin();
                        oldBin = this.value;
                    }
                }
            }
        }

        $("#cc-expiration").on("blur", function() {
            var expiry = $("#cc-expiration").val();
            expiration_keypress = true;
            var expiryArray = expiry.split("/");
            if(expiryArray.length>1)
            {
                
                var expiryMonth = expiryArray[0].trim();
                var expiryYear = expiryArray[1].trim();
                
                if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                {
                    $("#cc-expiration").addClass("is-invalid");
                }
                else
                {
                    $("#cc-expiration").removeClass("is-invalid");
                    expiration_keypress = false;
                }
            }
            else
            {
                $("#cc-expiration").addClass("is-invalid");
            }
        });

        function checkExpiration()
        {
            if(expiration_keypress)
            {
                var expiry = $("#cc-expiration").val();
                var expiryArray = expiry.split("/");
                if(expiryArray.length>1)
                {  
                    var expiryMonth = expiryArray[0].trim();
                    var expiryYear = expiryArray[1].trim();
                    if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                    {
                        $("#cc-expiration").addClass("is-invalid");
                    }
                    else
                    {
                        $("#cc-expiration").removeClass("is-invalid");
                    }
                }
                else
                {
                    $("#cc-expiration").addClass("is-invalid");
                }
            }
        }

        $("#cc-cvv").on("blur", function() {
            var cvvNumber = $("#cc-cvv").val();
            cvv_keypress = true;
            if(!payform.validateCardCVC(cvvNumber))
            {
                $("#cc-cvv").addClass("is-invalid");
            }
            else
            {
                $("#cc-cvv").removeClass("is-invalid");
                cvv_keypress = false;
            }
        });

        function checkCvv()
        {
            if(cvv_keypress)
            {
                var cvvNumber = $("#cc-cvv").val();
                if(!payform.validateCardCVC(cvvNumber))
                {
                    $("#cc-cvv").addClass("is-invalid");
                }
                else
                {
                    $("#cc-cvv").removeClass("is-invalid");
                }
            }
        }




        function checkBin()
        {
            
            var cardNumber = $("#card-number").val();
            cardNumber = cardNumber.replace(/\s/g,"").trim();
            var bin = cardNumber.substring(0, 8);

            if(bin.length!=8)
            {
                removeBillingForm();
                return false;
            }


            $.ajax({
                    url: "'. env('APP_API_URL') .'/tool/bin",
                    method: "POST",
                    data: { 
                        bin: bin
                    },
                }).done(function(response) {
                    var country_code = response.country_code;
                    if(country_code == "US" || country_code == "CA" || country_code == "GB")
                    {
                        addBillingForm();
                    }
                    else
                    {
                        removeBillingForm();
                    }
                }).fail(function( jqXHR, textStatus ) {

                });
            
            
        }



        function postBilling(id,country)
        {
            if($("#billing_form1").length==1)
            {
                var givenName = $("#cc-givenName").val().trim();
                var surname = $("#cc-surname").val().trim();
                var streetLine1 = $("#cc-streetLine1").val().trim();
                var postalCode = $("#cc-postalCode").val().trim();

                $.ajax({
                    url: "'. env('APP_API_URL') .'/tool/billing/"+ id,
                    method: "POST",
                    data: { 
                        tokenId: id,
                        givenName: givenName,
                        surname: surname,
                        streetLine1: streetLine1,
                        postalCode: postalCode,
                        country: country
                    },
                }).done(function(response) {
  
                }).fail(function( jqXHR, textStatus ) {

                });
            }
            
        }

        function enableButton()
        {
            $("#three-ds-container").hide();
            $("#card-number").attr("disabled", false);
            $("#cc-expiration").attr("disabled", false);
            $("#cc-cvv").attr("disabled", false);
            $("#cc-givenName").attr("disabled", false);
            $("#cc-surname").attr("disabled", false);
            $("#cc-streetLine1").attr("disabled", false);
            $("#cc-postalCode").attr("disabled", false);
            $("#loader").hide();
            $("#loader").removeClass("loader");
            $("#payment-form").slideDown("slow");
            $("#submit").attr("disabled", false);
            $("#submit").html(\'<i class="fas fa-lock"></i> <strong>Pay with card</strong>\');
        }

        var form = document.getElementById(\'payment-form\');
        form.addEventListener(\'submit\', function(ev) {
                ev.preventDefault();
                cleanFeedback();
                
                $("#alert-payment").slideUp("slow");
                $("#submit").attr("disabled", true);
                $("#submit").html(\' <i class="fa fa-spinner fa-spin fa-fw"></i>  processing... \');

                Xendit.setPublishableKey("'. env("XENDIT_PUBLIC_KEY") .'");
                
                var cardNumber = $("#card-number").val();
                var expiry = $("#cc-expiration").val();
                var expiryArray = expiry.split("/");
                var expiryMonth = expiryArray[0].trim();
                var expiryYear = expiryArray[1].trim();
                var cvvNumber = $("#cc-cvv").val();

                var external_id = randomNumber();
                
                if(expiryYear.length==2)
                {
                    expiryYear = "'. substr(date('Y'),0,2) .'"+ expiryYear;
                }

                if(!payform.validateCardNumber(cardNumber))
                {
                    $("#card-number").addClass("is-invalid");
                    enableButton();
                    return false;
                }
            
                if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                {
                    $("#cc-expiration").addClass("is-invalid");
                    enableButton();
                    return false;
                }

                if(!payform.validateCardCVC(cvvNumber))
                {
                    $("#cc-cvv").addClass("is-invalid");
                    enableButton();
                    return false;
                }


                $("#card-number").attr("disabled", true);
                $("#cc-expiration").attr("disabled", true);
                $("#cc-cvv").attr("disabled", true);

                $("#cc-givenName").attr("disabled", true);
                $("#cc-surname").attr("disabled", true);
                $("#cc-streetLine1").attr("disabled", true);
                $("#cc-postalCode").attr("disabled", true);

                cardNumber = cardNumber.replace(/\s/g,"");
                expiryMonth = expiryMonth.trim();
                expiryYear = expiryYear.trim();
                cvvNumber = cvvNumber.trim();

                Xendit.card.createToken({
                    amount: '.$amount.',
                    card_number: cardNumber,
                    card_exp_month: expiryMonth,
                    card_exp_year: expiryYear,
                    card_cvn: cvvNumber,

                    card_holder_email: "'.$email.'",
                    card_holder_first_name: "'.$first_name.'",
                    card_holder_last_name: "'.$last_name.'",
                    card_holder_phone_number: "'.$phoneNumber.'",

                    is_multiple_use: false,
                    external_id: external_id
                }, xenditResponseHandler);

                return false;
            });
        

        ';
        return $jscript;
    }

    public static function createFormV3($sessionId)
    {
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $first_name = BookingHelper::get_answer($shoppingcart,'firstName');
        $last_name = BookingHelper::get_answer($shoppingcart,'lastName');
        $email = BookingHelper::get_answer($shoppingcart,'email');
        $phoneNumber = BookingHelper::get_answer($shoppingcart,'phoneNumber');
        $amount = BookingHelper::convert_currency($shoppingcart->due_now,$shoppingcart->currency,'IDR');
        
        $billing_form = '
            <div id="billing_form1" class="row no-gutters mt-2">
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-streetLine1"><strong>Street line 1</strong></label>
                    <input type="text" class="form-control" id="cc-streetLine1" required="" placeholder="Address" style="height: 47px;border-radius: 0;">
                    <div id="streetLineFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-postalCode"><strong>Postal code</strong></label>
                    <input type="text" class="form-control" id="cc-postalCode" required="" placeholder="Postal code" style="height: 47px;border-radius: 0;">
                    <div id="zipCodeFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
            </div>

        ';


        $payment_container = '
        <hr />
        <form id="payment-form">

            <div class="row">
                <div class="col-md-12 mb-2">
                    <h2 class=" mt-2">Card Information</h2>
                </div>
                <div class="col-md-12 mb-2">
                    <label for="card-number"><strong>Card number</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i id="cardBrand" class="far fa-credit-card fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input class="form-control" type="text" id="card-number" placeholder="Card number" value="" style="height: 47px;border-radius: 0;" onKeyUp="return checkCardNumber();">
                        <div id="cardNumberFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
            </div>

            <div class="row no-gutters">
                <div class="col-md-6 mb-2">
                    <label for="cc-expiration"><strong>Valid thru</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="far fa-calendar fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="cc-expiration" placeholder="MM / YY" required="" style="height: 47px;border-radius: 0;" onKeyUp="return checkExpiration();">
                        <div id="expirationFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <label for="cc-cvv"><strong>CVV / CVN / CVC</strong></label>
                    <div class="input-group pr-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-key fa-lg fa-fw"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="cc-cvv" placeholder="3-4 digits code" required="" style="height: 47px;border-radius: 0;" onKeyUp="return checkCvv();">
                        <div id="cvvFeedback" class="invalid-feedback">
                            Invalid value
                        </div>
                    </div>
                </div>
            </div>

            <div class="row no-gutters mt-2">
                <div class="col-md-12 mb-2">
                    <h2 class=" mt-2">Cardholder Information</h2>
                </div>
                <div class="col-md-6 mb-2 pr-1">
                    <label for="cc-givenName"><strong>First name</strong></label>
                    <input value="'.$first_name.'" type="text" class="form-control" id="cc-givenName" required="" placeholder="Given Name" style="height: 47px;border-radius: 0;">
                    <div id="givenNameFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-6 mb-2 pr-1">
                    <label for="cc-surname"><strong>Last name</strong></label>
                    <input value="'.$last_name.'" type="text" class="form-control" id="cc-surname" required="" placeholder="Last Name"  style="height: 47px;border-radius: 0;">
                    <div id="lastNameFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-email"><strong>Email</strong></label>
                    <input value="'.$email.'" type="email" class="form-control" id="cc-email" required="" placeholder="Email" style="height: 47px;border-radius: 0;">
                    <div id="emailFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
                <div class="col-md-12 mb-2 pr-1">
                    <label for="cc-phone"><strong>Phone</strong></label>
                    <input value="'.$phoneNumber.'" type="tel" class="form-control" id="cc-phone" required="" style="height: 47px;border-radius: 0;">
                    <div id="phoneFeedback" class="invalid-feedback">
                        Invalid value
                    </div>
                </div>
            </div>

            <div id="billing_form"></div>
            

            <button style="height:47px;" class="mt-2 btn btn-lg btn-block btn-theme" id="submit">
                <i class="fas fa-lock"></i> <strong>Pay with card</strong>
            </button>

            <div id="change_payment" class="mt-2">
                <center>
                    <small><a href="#paymentMethod" class="text-theme" onClick="changePaymentMethod();">Click here</a> to change payment method</small>
                </center>
            </div>

        </form>

        <div id="loader" class="mb-4"></div>
        <div id="text-alert" class="text-center"></div>
        <div id="three-ds-container" class="modal" style="display: none;"></div>
        ';

        

        $jscript = '


        $("#submitCheckout").slideUp("slow");
        $("#paymentContainer").html(\''. str_replace(array("\r", "\n"), '', $payment_container) .'\');
        
        try
        {

        const inputCCPhone = document.querySelector("#cc-phone");
                const iti = window.intlTelInput(inputCCPhone, {
                    utilsScript: "'. config('site.assets') .'/js/utils.js",
                    separateDialCode: true,
                    initialCountry: "id",
                    hiddenInput: function(telInputName) {
                        return {
                            phone: "cc_phone_full",
                            country: "cc_country_code"
                        };
                    }
                });
        }
        catch
        {

        }

        payform.cardNumberInput(document.getElementById("card-number"));
        payform.expiryInput(document.getElementById("cc-expiration"));
        payform.cvcInput(document.getElementById("cc-cvv"));

        $(\'#card-number\').on(\'input\', function() {
            if($(\'#card-number\').val().length >=8)
            {
                var card_brand = payform.parseCardType($(\'#card-number\').val());
                if(card_brand=="visa")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-visa  fa-lg fa-fw\');
                }
                else if(card_brand=="mastercard")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-mastercard  fa-lg fa-fw\');
                }
                else if(card_brand=="jcb")
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'fab\').addClass(\'fa-cc-jcb  fa-lg fa-fw\');
                }
                else
                {
                    $("#cardBrand").removeClass();
                    $("#cardBrand").addClass(\'far\').addClass(\'fa-credit-card  fa-lg fa-fw\');
                }
            }
            else
            {
                $("#cardBrand").removeClass();
                $("#cardBrand").addClass(\'far\').addClass(\'fa-credit-card  fa-lg fa-fw\');
            }
        });

        function xenditResponseHandler (err, creditCardToken) {
            if (err) {
                enableButton();
                var error_message = err.message;
                $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ error_message +\'</h2></div>\');
                $(\'#alert-payment\').fadeIn("slow");
                return;
            }



            if (creditCardToken.status === "APPROVED" || creditCardToken.status === "VERIFIED") {
                            $("#three-ds-container").hide();
                            $("#loader").show();
                            $("#payment-form").slideUp("slow");  
                            $("#proses").hide();
                            $("#loader").addClass("loader");
                            $("#text-alert").show();
                            $("#text-alert").prepend( "Please wait and do not close the browser or refresh the page" );
                            
                            postBilling(creditCardToken.id,creditCardToken.card_info.country);

                            $.ajax({
                                beforeSend: function(request) {
                                    request.setRequestHeader(\'sessionId\', \''. $shoppingcart->session_id .'\');
                                    request.setRequestHeader(\'tokenId\', creditCardToken.id);
                                },
                                type: \'POST\',
                                url: \''. env('APP_API_URL') .'/payment/xendit\'
                            }).done(function( data ) {
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
                                    $("#text-alert").hide();
                                    $("#text-alert").empty();
                                    $("#loader").hide();
                                    $("#loader").removeClass("loader");
                                    $("#payment-form").slideDown("slow");
                                    enableButton();
                                    $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><strong style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ data.message +\'</strong></div>\');
                                    $(\'#alert-payment\').fadeIn("slow");
                                }
                            });
            } else if (creditCardToken.status === "IN_REVIEW") {

                            $("#three-ds-container").hide();
                            $("#three-ds-container").html("<iframe id=\"3ds-inline-frame\" name=\"3ds-inline-frame\" scrolling=\"no\"></iframe>");
                            $("#3ds-inline-frame").css("background-color", "#FFFFFF");
                            $("#3ds-inline-frame").css("top", "0px");
                            $("#3ds-inline-frame").css("left", "0px");
                            $("#3ds-inline-frame").css("width", "100%");
                            $("#3ds-inline-frame").css("height", "100%");
                            $("#3ds-inline-frame").css("position", "absolute");
                            window.open(creditCardToken.payer_authentication_url, "3ds-inline-frame");
                            $("#three-ds-container").show();
            } else if (creditCardToken.status === "FRAUD") {
                            enableButton();
            } else if (creditCardToken.status === "FAILED") {

                            enableButton();
                            var error_message = creditCardToken.failure_reason;

                            if(creditCardToken.failure_reason=="AUTHENTICATION_FAILED")
                            {
                                error_message = "Authentication Failed";
                            }
                            
                            $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ error_message +\'</h2></div>\');
                            $(\'#alert-payment\').fadeIn("slow");
                           
            }
        }
        
        function randomNumber()
        {
            var randomNumber = Date.now() + Math.random();
            randomNumber = randomNumber.toString().replace(".","");
            return randomNumber;
        }

        function cleanFeedback()
        {
            $("#card-number").removeClass("is-invalid");
            $("#cc-expiration").removeClass("is-invalid");
            $("#cc-cvv").removeClass("is-invalid");
        }

        
        function addBillingForm()
        {
            $("#billing_form").html(\''. str_replace(array("\r", "\n"), '', $billing_form) .'\');
            
        }
        function removeBillingForm()
        {
            $("#billing_form").html(\'\');
            
        }

        

        var cardNumber_keypress = false;
        var expiration_keypress = false;
        var cvv_keypress = false;
        var oldBin = "";
        $("#card-number").on("blur", function() {
            var cardNumber = $("#card-number").val();
            cardNumber_keypress = true;
            if(!payform.validateCardNumber(cardNumber))
            {
                $("#card-number").addClass("is-invalid");
                return;
            }
            else
            {
                $("#card-number").removeClass("is-invalid");
                cardNumber_keypress = false;
            }
            if (oldBin != this.value) {
                checkBin();
                oldBin = this.value;
            }
        });
        
        function checkCardNumber()
        {
            if(cardNumber_keypress)
            {
                var cardNumber = $("#card-number").val();
                if(!payform.validateCardNumber(cardNumber))
                {
                    $("#card-number").addClass("is-invalid");
                }
                else
                {
                    $("#card-number").removeClass("is-invalid");
                    if (oldBin != this.value) {
                        checkBin();
                        oldBin = this.value;
                    }
                }
            }
        }

        $("#cc-expiration").on("blur", function() {
            var expiry = $("#cc-expiration").val();
            expiration_keypress = true;
            var expiryArray = expiry.split("/");
            if(expiryArray.length>1)
            {
                
                var expiryMonth = expiryArray[0].trim();
                var expiryYear = expiryArray[1].trim();
                
                if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                {
                    $("#cc-expiration").addClass("is-invalid");
                }
                else
                {
                    $("#cc-expiration").removeClass("is-invalid");
                    expiration_keypress = false;
                }
            }
            else
            {
                $("#cc-expiration").addClass("is-invalid");
            }
        });

        function checkExpiration()
        {
            if(expiration_keypress)
            {
                var expiry = $("#cc-expiration").val();
                var expiryArray = expiry.split("/");
                if(expiryArray.length>1)
                {  
                    var expiryMonth = expiryArray[0].trim();
                    var expiryYear = expiryArray[1].trim();
                    if(!payform.validateCardExpiry(expiryMonth,expiryYear))
                    {
                        $("#cc-expiration").addClass("is-invalid");
                    }
                    else
                    {
                        $("#cc-expiration").removeClass("is-invalid");
                    }
                }
                else
                {
                    $("#cc-expiration").addClass("is-invalid");
                }
            }
        }

        $("#cc-cvv").on("blur", function() {
            var cvvNumber = $("#cc-cvv").val();
            cvv_keypress = true;
            if(!payform.validateCardCVC(cvvNumber))
            {
                $("#cc-cvv").addClass("is-invalid");
            }
            else
            {
                $("#cc-cvv").removeClass("is-invalid");
                cvv_keypress = false;
            }
        });

        function checkCvv()
        {
            if(cvv_keypress)
            {
                var cvvNumber = $("#cc-cvv").val();
                if(!payform.validateCardCVC(cvvNumber))
                {
                    $("#cc-cvv").addClass("is-invalid");
                }
                else
                {
                    $("#cc-cvv").removeClass("is-invalid");
                }
            }
        }




        function checkBin()
        {
            
            var cardNumber = $("#card-number").val();
            cardNumber = cardNumber.replace(/\s/g,"").trim();
            var bin = cardNumber.substring(0, 8);

            if(bin.length!=8)
            {
                removeBillingForm();
                return false;
            }


            $.ajax({
                    url: "'. env('APP_API_URL') .'/tool/bin",
                    method: "POST",
                    data: { 
                        bin: bin
                    },
                }).done(function(response) {
                    var country_code = response.country_code;
                    if(country_code == "US" || country_code == "CA" || country_code == "GB")
                    {
                        addBillingForm();
                    }
                    else
                    {
                        removeBillingForm();
                    }
                }).fail(function( jqXHR, textStatus ) {

                });
            
            
        }



        function postBilling(id,country)
        {
            if($("#billing_form1").length==1)
            {
                var givenName = $("#cc-givenName").val().trim();
                var surname = $("#cc-surname").val().trim();
                var streetLine1 = $("#cc-streetLine1").val().trim();
                var postalCode = $("#cc-postalCode").val().trim();

                $.ajax({
                    url: "'. env('APP_API_URL') .'/tool/billing/"+ id,
                    method: "POST",
                    data: { 
                        tokenId: id,
                        givenName: givenName,
                        surname: surname,
                        streetLine1: streetLine1,
                        postalCode: postalCode,
                        country: country
                    },
                }).done(function(response) {
  
                }).fail(function( jqXHR, textStatus ) {

                });
            }
            
        }

        function enableButton()
        {
            $("#three-ds-container").hide();
            $("#card-number").attr("disabled", false);
            $("#cc-expiration").attr("disabled", false);
            $("#cc-cvv").attr("disabled", false);
            $("#cc-givenName").attr("disabled", false);
            $("#cc-surname").attr("disabled", false);
            $("#cc-streetLine1").attr("disabled", false);
            $("#cc-postalCode").attr("disabled", false);
            $("#cc-phone").attr("disabled", false);
            $("#cc-email").attr("disabled", false);
            $("#loader").hide();
            $("#loader").removeClass("loader");
            $("#payment-form").slideDown("slow");
            $("#submit").attr("disabled", false);
            $("#submit").html(\'<i class="fas fa-lock"></i> <strong>Pay with card</strong>\');
        }

        var form = document.getElementById(\'payment-form\');
        form.addEventListener(\'submit\', function(ev) {
                ev.preventDefault();
                cleanFeedback();
                
                $("#alert-payment").slideUp("slow");
                $("#submit").attr("disabled", true);
                $("#submit").html(\' <i class="fa fa-spinner fa-spin fa-fw"></i>  processing... \');

                Xendit.setPublishableKey("'. env("XENDIT_PUBLIC_KEY") .'");
                
                var card_number = $("#card-number").val();
                var expiry = $("#cc-expiration").val();
                var expiryArray = expiry.split("/");
                var card_exp_month = expiryArray[0].trim();
                var card_exp_year = expiryArray[1].trim();
                var card_cvn = $("#cc-cvv").val();

                var card_holder_email = $("#cc-email").val();
                var card_holder_phone_number = document.querySelector("[name=\'cc_phone_full\']").value;
                var card_holder_first_name = $("#cc-givenName").val();
                var card_holder_last_name = $("#cc-surname").val();

                var external_id = randomNumber();
                
                if(card_exp_year.length==2)
                {
                    card_exp_year = "'. substr(date('Y'),0,2) .'"+ card_exp_year;
                }

                if(!payform.validateCardNumber(card_number))
                {
                    $("#card-number").addClass("is-invalid");
                    enableButton();
                    return false;
                }
            
                if(!payform.validateCardExpiry(card_exp_month,card_exp_year))
                {
                    $("#cc-expiration").addClass("is-invalid");
                    enableButton();
                    return false;
                }

                if(!payform.validateCardCVC(card_cvn))
                {
                    $("#cc-cvv").addClass("is-invalid");
                    enableButton();
                    return false;
                }


                $("#card-number").attr("disabled", true);
                $("#cc-expiration").attr("disabled", true);
                $("#cc-cvv").attr("disabled", true);

                $("#cc-givenName").attr("disabled", true);
                $("#cc-surname").attr("disabled", true);
                $("#cc-phone").attr("disabled", true);
                $("#cc-email").attr("disabled", true);

                $("#cc-streetLine1").attr("disabled", true);
                $("#cc-postalCode").attr("disabled", true);

                card_number = card_number.replace(/\s/g,"");
                card_exp_month = card_exp_month.trim();
                card_exp_year = card_exp_year.trim();
                card_cvn = card_cvn.trim();

                

                Xendit.card.createToken({
                    amount: '.$amount.',
                    card_number: card_number,
                    card_exp_month: card_exp_month,
                    card_exp_year: card_exp_year,
                    card_cvn: card_cvn,

                    card_holder_email: card_holder_email,
                    card_holder_first_name: card_holder_first_name,
                    card_holder_last_name: card_holder_last_name,
                    card_holder_phone_number: card_holder_phone_number,

                    is_multiple_use: false,
                    external_id: external_id
                }, xenditResponseHandler);

                return false;
            });
        

        ';
        return $jscript;
    }

    public function getTokenCard($token_id)
    {
        return json_decode($this->GET('/credit_card_tokens/'.$token_id));
    }

    public function createChargeCard($token_id,$amount)
    {
        $data = new \stdClass();
        $data->external_id = Uuid::uuid4()->toString();
        $data->amount = $amount;
        $data->token_id = $token_id;

        
        $billing = FirebaseHelper::read('billing/'.$token_id);
        $country = "";
        if(isset($billing->country)) $country = $billing->country;
        if($country=="US" || $country=="CA" || $country=="GB")
        {
            $given_name = "";
            $surname = "";
            $street_line1 = "";
            $postal_code = "";

            if(isset($billing->given_name)) $given_name = $billing->given_name;
            if(isset($billing->surname)) $surname = $billing->surname;
            if(isset($billing->street_line1)) $street_line1 = $billing->street_line1;
            if(isset($billing->postal_code)) $postal_code = $billing->postal_code;


            $data->billing_details = new \stdClass();
            $data->billing_details->given_names = $given_name;
            $data->billing_details->surname = $surname;
            $data->billing_details->address = new \stdClass();
            $data->billing_details->address->country = $country;
            $data->billing_details->address->street_line1 = $street_line1;
            $data->billing_details->address->postal_code = $postal_code;

        }
        //FirebaseHelper::delete('billing/'.$token_id);
        

        return json_decode($this->POST('/credit_card_charges',$data));
    }
    
    public function createRefund($shoppingcart)
    {
        $payment_type = $shoppingcart->shoppingcart_payment->payment_type;
        $amount = $shoppingcart->shoppingcart_payment->amount;
        $external_id = $shoppingcart->shoppingcart_payment->order_id;
        $token_id = $shoppingcart->shoppingcart_payment->authorization_id;

        if($payment_type=="card")
        {
            $data = new \stdClass();
            $data->external_id = $external_id;
            $data->amount = $amount;
            return json_decode($this->POST('/credit_card_charges/'.$token_id.'/refunds',$data,['api-version: 2019-05-01','X-IDEMPOTENCY-KEY: '.$external_id]));
        }

        if($payment_type=="qrcode")
        {
            $data = new \stdClass();
            $data->amount = $amount;
            return json_decode($this->POST('/qr_codes/payments/'.$token_id.'/refunds',$data,['api-version: 2019-05-01','X-IDEMPOTENCY-KEY: '.$external_id]));
        }
    }

    public function getNet($shoppingcart)
    {
        $net = 0;
        $amount = $shoppingcart->shoppingcart_payment->amount;
        if($shoppingcart->shoppingcart_payment->payment_type=="card")
        {
            $fee = ($amount * 2.9 / 100) + 2000;
            $vat = floor($fee * 11 / 100);
            $net = $amount - $fee - $vat;
        }
        else if($shoppingcart->shoppingcart_payment->payment_type=="qrcode")
        {
            $fee = $amount * 0.7 / 100;
            $net = $amount - $fee;
        }
        else
        {
            $fee = 4000;
            $vat = floor($fee * 11 / 100);
            $net = $amount - $fee - $vat;
        }

        return $net;
    }

    public function createInvoice($amount, $payment_method=['CREDIT_CARD'], $invoice_duration=86400)
    {
        $data = new \stdClass();
        $data->external_id = Uuid::uuid4()->toString();
        $data->amount = $amount;
        $data->payment_methods = $payment_method;
        $data->invoice_duration = $invoice_duration;
        return json_decode($this->POST('/v2/invoices',$data));
    }

    public function createQrcode($amount,$expired_at)
    {
        $data = new \stdClass();
        $data->reference_id = Uuid::uuid4()->toString();
        $data->type = 'DYNAMIC';
        $data->amount = $amount;
        $data->currency = 'IDR';
        $data->expired_at = $expired_at;

        return json_decode($this->POST('/qr_codes',$data,['api-version: 2022-07-31']));
    }

    public function createVirtualAccount($bank_code,$amount,$name,$expired_at)
    {
        $data = new \stdClass();
        $data->external_id = Uuid::uuid4()->toString();
        $data->bank_code = $bank_code;
        $data->name = $name;
        $data->is_closed = true;
        $data->expected_amount = $amount;
        $data->expiration_date = $expired_at;

        return json_decode($this->POST('/callback_virtual_accounts',$data));
    }

    public function createEWalletOvoCharge($amount,$mobile_number)
    {
        $data = new \stdClass();
        $data->reference_id = Uuid::uuid4()->toString();
        $data->currency = 'IDR';
        $data->amount = $amount;
        $data->checkout_method = 'ONE_TIME_PAYMENT';
        $data->channel_code = 'ID_OVO';
        $data->channel_properties = new \stdClass();
        $data->channel_properties->mobile_number = $mobile_number;
        
        return json_decode($this->POST('/ewallets/charges',$data));
    }

    private function POST($url,$data,$headers=NULL){
        return $this->curl('POST',$url,$data,$headers);
    }

    private function GET($url){
        return $this->curl('GET',$url);
    }
    
    private function DELETE($url){
        return $this->curl('DELETE',$url);
    }
    
    private function PUT($url){
        return $this->curl('PUT',$url);
    }

    private function curl($mode, $curl_url,$data=NULL,$headers=NULL)
    {
    	$ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_URL, $this->xendit->endpoint."$curl_url");

        $headerArray[] = "Authorization: Basic ". base64_encode($this->xendit->secret_key.':');

        if($mode=='POST'){

            $payload = json_encode($data);

            $headerArray[] = "Content-Type: application/json";
            $headerArray[] = 'Content-Length: ' . strlen($payload);
            
            if($headers){
                foreach($headers as $header){
                    $headerArray[] = $header;
                }
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        
        $response = curl_exec($ch);
        
        if($response === false){
            echo 'Curl error: ' . curl_error($ch);
        }
        curl_close($ch);
        
        return  $response;
    }

    
}
?>
