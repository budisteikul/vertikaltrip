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

        if($webhook_app=="create_booking")
        {

            $text = '<head>
  <title>

  </title>
  <!--[if !mso]>
    <meta http-equiv=3D"X-UA-Compatible" content=3D"IE=3Dedge">
    <![endif]-->
  <meta http-equiv=3D"Content-Type" content=3D"text/html; charset=3DUTF-8">
  <meta name=3D"viewport" content=3D"width=3Ddevice-width, initial-scale=3D=
1">
  <style type=3D"text/css" data-embed=3D"">    #outlook a {
      padding: 0;
    }

    body {
      margin: 0;
      padding: 0;
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
    }

    table,
    td {
      border-collapse: collapse;
      mso-table-lspace: 0pt;
      mso-table-rspace: 0pt;
    }

    img {
      border: 0;
      height: auto;
      line-height: 100%;
      outline: none;
      text-decoration: none;
      -ms-interpolation-mode: bicubic;
    }

    img[class~=3Dx_hide-owa] {
      display: none;
    }

    p {
      display: block;
      margin: 13px 0;
    }

    td[class~=3Dx_button-block__container-modifier] {
      background-color: #0071eb !important;
      text-align: center !important;
      color: white !important;
    }

    a[class~=3Dx_button-block__link-modifier] {
      width: 180px !important;
      height: 40px !important;
      color: white !important;
    }

    .ExternalClass {
      width: 100%;
    }

    .ExternalClass,
    .ExternalClass p,
    .ExternalClass span,
    .ExternalClass font,
    .ExternalClass td,
    .ExternalClass div {
      line-height: 100%;
    }
  </style>
  <!--[if mso]>
      <style type=3D"text/css" data-embed>
        a { padding:0; }

        /* Review.vue */
        .button-block__link-modifier {color: white !important;}
        .button-block__container-modifier {
          background-color: #0071eb !important;
          text-align: center !important;
          color: white !important;
        }

        /* organisms/RecoHeader.vue */
        .gyg-header .logo {
          padding: 0 8px !important;
        }
        .gyg-header .header-text {
          padding: 24px 8px !important;
        }
        .gyg-header .header-text div {
          font-size: 56px !important;
          line-height: 64px !important;
        }
        .gyg-header .header-image {
          /*padding: 0 8px !important;*/
        }
        .gyg-header .header-description {
          padding: 24px !important;
        }

        /* organisms/SwimlaneSection.vue */

        /* molecules/SwimlaneBlock.vue */
        .swimlane-heading {
          padding-top: 56px !important;
        }
        .activity-card-container {
          width: 50%;
        }

        /* molecules/ClaimedInventiveCta.vue */
        .image-cta-code-text-block-mso {
          padding: 40px 15px !important;
        }

        .incentive-logo table tbody tr td img {
          width: 166px !important;
          height: 166px !important;
        }

        .incentive-logo-container {
          width: 33% !important;
        }
        .incentive-text-container {
          width: 66% !important;
        }

        /* organisms/FooterV2.vue */
        .separator tbody tr td {
          padding: 56px 0;
        }
        .contact-us tr td {

        }
        .contact-text p {
          padding: 0 !important;
        }
        .contact-us a table tr td {
          padding: 16px 0 24px !important;
        }

        /* organisms/TextAndImageBlock.vue */
        .text-and-image .text-block p {
          margin: 24px 24px 0 24px;
        }

        .text-and-image .cta-block {
          padding: 2px 24px 0 !important;
        }
      </style>
    <![endif]-->
  <!--[if mso]>
    <xml>
    <o:OfficeDocumentSettings>
      <o:AllowPNG/>
      <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
  <!--[if lte mso 11]>
    <style type=3D"text/css" data-embed>
      .mj-outlook-group-fix { width:100% !important; }
    </style>
    <![endif]-->
  <style type=3D"text/css" data-embed=3D"">    @font-face {
      font-family: \'g\';
      font-style: normal;
      font-weight: 200;
      src: url(https://cdn.getyourguide.com/tf/assets/static/fonts/GT-Eesti=
/GT-Eesti-Pro-Display-Light.woff2) format(\'woff\');
    }

    @font-face {
      font-family: \'g\';
      font-style: normal;
      font-weight: 400;
      src: url(https://cdn.getyourguide.com/tf/assets/static/fonts/GT-Eesti=
/GT-Eesti-Pro-Display-Regular.woff2) format(\'woff\');
    }

    @font-face {
      font-family: \'g\';
      font-style: normal;
      font-weight: 500;
      src: url(https://cdn.getyourguide.com/tf/assets/static/fonts/GT-Eesti=
/GT-Eesti-Pro-Display-Medium.woff2) format(\'woff\');
    }

    @font-face {
      font-family: \'g\';
      font-style: normal;
      font-weight: 600;
      src: url(https://cdn.getyourguide.com/tf/assets/static/fonts/GT-Eesti=
/GT-Eesti-Pro-Display-Bold.woff2) format(\'woff\');
    }

    @font-face {
      font-family: Lexend;
      font-style: normal;
      font-weight: 400;
      src: local(Lexend);
    }

    @font-face {
      font-family: Roboto;
      font-style: normal;
      font-weight: 400;
      src: local(Roboto);
    }

    /* TYPOGRAPHY */
    p,
    h1,
    h2,
    h3,
    h4,
    h5,
    h6,
    span,
    a,
    button {
      font-family: \'g\', Lexend, Roboto, sans-serif;
      line-height: 22px;
      font-size: 16px;
      font-weight: 400;
      margin: 0;
    }

    p {
      padding: 0;
      margin: 0;
      color: #1a2b49;
      font-size: 16px;
    }

    a {
      color: #0071EB;
      text-decoration: none;
      font-size: inherit;
    }

    sub a {
      font-size: inherit;
    }

    div.gyg-card {
      border-radius: 4px !important;
      overflow: hidden !important;
      border: 1px solid #F3F4F6 !important;
    }

    @media (min-width: 480px) {
      div.gyg-card {
        border-radius: 8px !important;
      }
    }
  </style>
  <!--preload-links-->
  <style type=3D"text/css">
@media (min-width: 480px) {
  .gyg-header {
    padding: 0 !important;
  }
  .gyg-header>table>tbody>tr>td {
    padding: 24px 0;
  }
  .gyg-header--banner>table>tbody>tr>td {
    padding: 0 10px;
  }
  .gyg-header--logo>table>tbody>tr>td>a>img {
    padding: 0 0 0 10px !important;
  }
  [dir=3Drtl] .gyg-header--logo>table>tbody>tr>td>a>img {
    padding: 0 10px 0 0 !important;
  }
}


@media (max-width: 480px) {
  .header-banner img {
    width: 170px !important;
    height: 170px !important;
  }
}
</style><style type=3D"text/css">
@media screen and (max-width: 420px) {
  .image-block__default {
    display: none !important;
  }
  .image-block__default.single {
    display: block !important;
  }
  .image-block__mobile {
    display: block;
  }
}
</style><style type=3D"text/css">
@media screen and (max-width: 544px) {
  .grid-row .grid-row__wrapper {
    display: block;
    padding: 8px 16px;
    width: auto;
  }
}


@media screen and (max-width: 544px) {
  .grid-row .grid-row__wrapper-left {
    display: block;
    padding: 8px;
    width: auto;
  }
}


@media screen and (max-width: 544px) {
  .grid-row .grid-row__wrapper-right {
    display: block;
    padding: 8px;
    width: auto;
  }
}


@media screen and (max-width: 544px) {
  .grid-row .grid-row__wrapper-single {
    padding: 8px;
  }
}


@media screen and (max-width: 544px) {
}


@media screen and (max-width: 544px) {
  .grid-section {
    width: 100%;
  }
}
</style>
 =20
 =20
</head>

<body data-v-d7604377=3D"">
  <!--[--><table role=3D"presentation" border=3D"0" cellpadding=3D"0" cells=
pacing=3D"0" width=3D"100%" class=3D"base" style=3D"border: none; border-sp=
acing: 0; width: 100%;"><tbody><tr><td align=3D"center"><!--[if mso]><table=
 role=3D"presentation" align=3D"center" width=3D"544" style=3D"width:480px;=
"><tr><td width=3D"544" style=3D"width:544px;"><![endif]--><!--[--><div dat=
a-v-d7604377=3D""><table align=3D"center" border=3D"0" cellpadding=3D"0" ce=
llspacing=3D"0" role=3D"presentation" width=3D"100%" style=3D"background-co=
lor:#ffffff;margin-bottom:16px;"><tbody><tr><td><div class=3D"gyg-header" s=
tyle=3D"margin: 0px auto; max-width: 496px; padding: 10px;"><table align=3D=
"left" border=3D"0" cellpadding=3D"0" cellspacing=3D"0" role=3D"presentatio=
n" width=3D"100%"><tbody><tr><td padding=3D"0" width=3D"20%"><table border=
=3D"0" cellpadding=3D"0" cellspacing=3D"0" role=3D"presentation" style=3D"b=
order-collapse:collapse;border-spacing:0px;" class=3D"logo"><tbody><tr alig=
n=3D"left"><td align=3D"left" style=3D""><a href=3D"https://u22105166.ct.se=
ndgrid.net/ls/click?upn=3Du001.cgvWDPhZtte8w9SmEnPFbHmBGxfFiF1pT69VNBsatiVn=
fQ5-2BKjcJB2vGWpw-2FAdIPg5zq_gPVpeftr358-2FiDObqCqMyqOYO42W7R9WgJTIVxyNoAdI=
W36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFbfz5KSmhpfBnr-2BFluWFPn5jFOgf4lnfDHr-=
2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZVGoSh-2FEm6CZAZszqAfXSByqm4Xw7fGACxp=
AFwnsOaKPmIOhoEKa2LeqSfPKK3Kft8iesNJqzReCY3NJ9B-2B8M5MxbAX3PzR-2BKMf7gFNEqZ=
KWVeDsvhZol1UaadqDfSHDj13SETVbjfqraYxIfzZMYpcyTPMTKQ4kXL1l0g87ZnTZJ9faoi9UA=
EUEm6XoItfo1ysTOKSLqgi0sfoeLD3k-3D"><img class=3D"single image-block__defau=
lt" height=3D"62" width=3D"82" src=3D"https://cdn.braze.eu/appboy/communica=
tion/assets/image_assets/images/60a77720fbbb0101b1f18621/original.png?16215=
87744" alt=3D"GetYourGuide" style=3D"border:0px;display:block;outline:none;=
text-decoration:none;height:62;width:82;font-size:13px;"><!----></a></td></=
tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></tabl=
e><!----></div><table class=3D"grid-section" border=3D"0" cellpadding=3D"0"=
 cellspacing=3D"0" role=3D"presentation" style=3D"margin: 0 auto; max-width=
: 544px; table-layout: fixed; width: 544px;" data-v-d7604377=3D""><tbody><!=
--[--><tr class=3D"grid-row" data-v-d7604377=3D""><!--[if mso]><td colspan=
=3D"2" width=3D"100%" style=3D"width:480px; padding: 8px 20px;"><table role=
=3D"presentation" width=3D"100%" style=3D"width:480px;"><tr><![endif]--><td=
 class=3D"grid-row__wrapper-middle grid-row__wrapper grid-row__wrapper-sing=
le" colspan=3D"2" style=3D"padding: 8px 32px; vertical-align: middle; width=
: 100%;"><div class=3D"grid-column" position=3D"0" width=3D"full" style=3D"=
height: 100%; width: 100%;"><!--[--><!--[--><h1 data-v-d7604377=3D"" style=
=3D"font-size: large; font-weight: 700;">Hi Supply Partner, great news! The=
 following offer has been booked:</h1><p data-v-d7604377=3D"" style=3D"marg=
in: .8em 0;"><strong data-v-d7604377=3D"">Yogyakarta: Nighttime Walk and Fo=
od Tour</strong><br data-v-d7604377=3D""> Option: <strong data-v-d7604377=
=3D"">Yogyakarta: Nighttime Walk and Food Tour Open Trip</strong></p><a hre=
f=3D"https://u22105166.ct.sendgrid.net/ls/click?upn=3Du001.cgvWDPhZtte8w9Sm=
EnPFbFowXFvBUTTl7RUbVBedYU9a9hsq8xtZTaNL9T8yDkgrEAKwEc7N-2Bf422JQIeRpH1ojXN=
xAF7YBKowFWYma8o4iRp0N0bdfpzgxBDVZbveuil-2BEHABFJWsWGPDYAXsFAm0UmU00wG5lL2t=
1ZpUayhphrqXZWgn-2B52-2BGV6HxiYrOVGXzms-2BRPqSNgQs4dCb2YoaQttoV4s8Z5fSePbHk=
b39eDNH9ICaz3G4mgz0e2gLXB9QEC_gPVpeftr358-2FiDObqCqMyqOYO42W7R9WgJTIVxyNoAd=
IW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFbfz5KSmhpfBnr-2BFluWFPn5jFOgf4lnfDHr=
-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZVGoSh-2FEm6CZAZszqAfURfApcx3qeMHQWZ=
BeeGzMxltZWjox9GgcN8th8mMBttmCxZ6RgssRqoYUcNn8C-2FgpzzMcW7ByYPOP0YzWwGp9cIP=
jv1yxHQVjmrMQWdsztz6E5SMBW60oMwZdkV3Cwisliu6PAljJ9N4pUy-2BoYgrD0y7Rf8-2Fl0B=
R7YNnsKoOXPlB1KeBXH3IuLNHXtYDn4kI8-3D" target=3D"_blank" universal=3D"false=
" class=3D"button--normal" data-v-d7604377=3D"" style=3D"background: #0071e=
b; border-radius: 22px; color: #fff; font-feature-settings: \'calt\' off; fon=
t-size: 16px; font-style: normal; font-weight: 500; line-height: 44px; padd=
ing: 11px 24px; text-align: center;"><!--[--><!--[-->View booking<!--]--><!=
--]--></a><div class=3D"section__row" data-v-d7604377=3D""><p data-v-d76043=
77=3D"" style=3D"margin: .8em 0;">Most important data for this booking:</p>=
<p data-v-d7604377=3D"" style=3D"margin: .8em 0;">Date: <strong data-v-d760=
4377=3D"">July 10, 2025 6:30 PM</strong></p><p data-v-d7604377=3D"" style=
=3D"margin: .8em 0;">Price: <strong data-v-d7604377=3D"">Rp 1,035,000.00</s=
trong></p><p data-v-d7604377=3D"" style=3D"margin: .8em 0;">Reference numbe=
r: <strong data-v-d7604377=3D"">GYGFWWVGVWBK</strong></p><p data-v-d7604377=
=3D"" style=3D"margin: .8em 0;">Number of participants: <br data-v-d7604377=
=3D""><span data-v-d7604377=3D""><strong>2 x</strong> Adults (Age 0 - 99) <=
span>(Rp 517500)</span><br></span></p><p data-v-d7604377=3D"" style=3D"marg=
in: .8em 0;">Main customer: <br data-v-d7604377=3D""><!--[-->Lilly<!--]--> =
  <!--[-->Genth<!--]--><br data-v-d7604377=3D""><!--[-->customer-yvnq67gfpo=
dvbrm2@reply.getyourguide.com <br data-v-d7604377=3D""><!--]--><!--[-->Lang=
uage: German <br data-v-d7604377=3D""><!--]--><!--[-->Phone: +4915785108981=
 <br data-v-d7604377=3D""><!--]--></p><p data-v-d7604377=3D"" style=3D"marg=
in: .8em 0;">Tour language: <strong data-v-d7604377=3D"">English</strong> (=
Live tour guide) </p><!----><!----><!----><!----><p data-v-d7604377=3D"" st=
yle=3D"margin: .8em 0;">Best regards,<br>The GetYourGuide Team</p><br data-=
v-d7604377=3D""><p data-v-d7604377=3D"" style=3D"margin: .8em 0;"><span dat=
a-v-d7604377=3D"">If the button above is not working, please use this link =
to view the booking of your offer <strong>Yogyakarta: Nighttime Walk and Fo=
od Tour</strong>:</span><br data-v-d7604377=3D""><a href=3D"https://u221051=
66.ct.sendgrid.net/ls/click?upn=3Du001.cgvWDPhZtte8w9SmEnPFbFowXFvBUTTl7RUb=
VBedYU9a9hsq8xtZTaNL9T8yDkgr24YGXHx-2FOqdxubWuM9S7JJrTw6cFwBInYmIGYffCJql6-=
2FzowmBKL14VaDQbj-2F80zuwr1Uo-2Bkci2cs3dpqThyVrxNxUTMtAdawgL1nAWnsf-2B5aKpS=
m9NJbwMitoLjpFtw4Fr0p7uTXPNdkF25Ge0XtJA7eYGZ0GQ-2FWYlme28CvNA-3DM8a9_gPVpef=
tr358-2FiDObqCqMyqOYO42W7R9WgJTIVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5=
CkFbfz5KSmhpfBnr-2BFluWFPn5jFOgf4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7o=
dTDZVGoSh-2FEm6CZAZszqAfVQlVV0ltNXHzkaUzE-2BsBtrdmFI9WOmStf6SaZ4F3gHMcPcVLK=
4jWwrNU1KTU76OikqNYGHb-2BnZuVCE7OxMiD-2F70StTnb5a0B6gXq3STJlCc0OPS-2FlJKDyd=
3kaAQ1Ddrh9wKK2pHWY9OWd7VYszOgS113MUuYPTIEH2Lmu7AXPPZR-2Bzq716BKSZlsAx7a9eF=
yM-3D" data-v-d7604377=3D"">https://supplier.getyourguide.com/bookings?tour=
_id=3D429708</a></p></div><!--]--><!--]--></div></td><!--[if mso]></tr></ta=
ble></td><![endif]--></tr><!--]--></tbody></table><!--]--><!--[if mso]></td=
></tr></table><![endif]--></td></tr></tbody></table><table class=3D"grid-se=
ction" border=3D"0" cellpadding=3D"0" cellspacing=3D"0" role=3D"presentatio=
n" style=3D"margin: 0 auto; max-width: 544px; table-layout: fixed; width: 5=
44px;" locale=3D"en-US" data-v-d7604377=3D""><tbody><!--[--><tr class=3D"gr=
id-row gyg-footer-social"><!--[if mso]><td colspan=3D"2" width=3D"100%" sty=
le=3D"width:480px; padding: 8px 20px;"><table role=3D"presentation" width=
=3D"100%" style=3D"width:480px;"><tr><![endif]--><td class=3D"grid-row__wra=
pper-middle grid-row__wrapper grid-row__wrapper-single" colspan=3D"2" style=
=3D"background-color: #f53; border-radius: 4px; padding: 8px 32px; vertical=
-align: middle; width: 100%;"><div class=3D"grid-column" position=3D"0" wid=
th=3D"full" style=3D"height: 100%; width: 100%;"><!--[--><!--[--><table bor=
der=3D"0" cellpadding=3D"0" cellspacing=3D"0" role=3D"presentation" style=
=3D"border-collapse: collapse; border-spacing: 0px; width: 100%;" class=3D"=
full-width-image"><tbody><tr align=3D"center"><td align=3D"center" style=3D=
"padding:6px 0 16px;"><a><img class=3D"single image-block__default" height=
=3D"auto" width=3D"144" src=3D"https://cdn.braze.eu/appboy/communication/as=
sets/image_assets/images/6308c6fa8be4cc46dfec6783/original.png?1661519610" =
alt=3D"GetYourGuide Logo" style=3D"border:0px;display:block;outline:none;te=
xt-decoration:none;height:auto;width:144;font-size:13px;"><!----></a></td><=
/tr></tbody></table><div><table align=3D"center" border=3D"0" cellpadding=
=3D"0" cellspacing=3D"0" role=3D"presentation"><tbody><tr><td class=3D"soci=
al-btn" style=3D"padding-right: 12px;"><table align=3D"center" border=3D"0"=
 cellpadding=3D"0" cellspacing=3D"0" role=3D"presentation" style=3D"float:n=
one;display:inline-table;"><tbody><tr><td style=3D"padding:0px 8px;vertical=
-align:middle;"><table border=3D"0" cellpadding=3D"0" cellspacing=3D"0" rol=
e=3D"presentation" style=3D"border-radius:3px;width:13px;"><tbody><tr><td s=
tyle=3D"font-size:0;height:13px;vertical-align:middle;width:13px;"><a href=
=3D"https://u22105166.ct.sendgrid.net/ls/click?upn=3Du001.cgvWDPhZtte8w9SmE=
nPFbGVTBBPQQru8OcY-2B4Bq3j-2B5YtchX9haL1ziLWEyYN9IJ8dFQ_gPVpeftr358-2FiDObq=
CqMyqOYO42W7R9WgJTIVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFbfz5KSmhpf=
Bnr-2BFluWFPn5jFOgf4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZVGoSh-2FE=
m6CZAZszqAfUrjzpMAfa8fixFfQvmGtp4I4RLeyTGGWG35KE8fOheMkQoIWTtv-2BxBzW2Fjrv-=
2Bl7hDtaoSjA1ThpdVA0feyXsARlDysk2L2LzHsKhDLUQQI7tJeuba7kVyjdihA0FExjTrtOsJG=
IZ696PCF3bx2a-2Bz1pTO-2Fo-2BDN-2BMYoQrsW-2FTRjDyPTkxbcml34K5ATacXMAo-3D" ta=
rget=3D"_blank"><img src=3D"https://cdn.braze.eu/appboy/communication/asset=
s/image_assets/images/5fad22efd57d053d7ce8a4fd/original.png?1605182191" sty=
le=3D"border-radius:3px;display:block;" width=3D"18" height=3D"18" alt=3D"f=
acebook"></a></td></tr></tbody></table></td></tr></tbody></table></td><td c=
lass=3D"social-btn" style=3D"padding-right: 12px;"><table align=3D"center" =
border=3D"0" cellpadding=3D"0" cellspacing=3D"0" role=3D"presentation" styl=
e=3D"float:none;display:inline-table;"><tbody><tr><td style=3D"padding:0px =
8px;vertical-align:middle;"><table border=3D"0" cellpadding=3D"0" cellspaci=
ng=3D"0" role=3D"presentation" style=3D"border-radius:3px;width:22px;"><tbo=
dy><tr><td style=3D"font-size:0;height:22px;vertical-align:middle;width:22p=
x;"><a href=3D"https://u22105166.ct.sendgrid.net/ls/click?upn=3Du001.cgvWDP=
hZtte8w9SmEnPFbEtxdJivqDFgojtDPrQ3DRJbhly6CTnkUVzV1oxDzYY-2BTzf5_gPVpeftr35=
8-2FiDObqCqMyqOYO42W7R9WgJTIVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFb=
fz5KSmhpfBnr-2BFluWFPn5jFOgf4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZ=
VGoSh-2FEm6CZAZszqAfVrqP0eXFNb1xS9guugbKmQZfFCfJ4b4xIyKAtUPkHWj9T2zYIjMRa5I=
PvsoFfHbD6KH-2FB-2FViSph8H4JPf3V4yXdjz3W8yztfK52ml5ZIKUgkN8XuoIM9gH41kLbz0h=
dKHhdzla0rogzCD-2FvSJCFJJKhPR-2BmvlXOcDKf5WFLXBq6KBpPAkbGjeeldpr9CO8-2BSA-3=
D" target=3D"_blank"><img src=3D"https://cdn.braze.eu/appboy/communication/=
assets/image_assets/images/5fad22efde3f105bf8f95e82/original.png?1605182191=
" style=3D"border-radius:3px;display:block;" width=3D"18" height=3D"18" alt=
=3D"twitter"></a></td></tr></tbody></table></td></tr></tbody></table></td><=
td class=3D"social-btn" style=3D"padding-right: 12px;"><table align=3D"cent=
er" border=3D"0" cellpadding=3D"0" cellspacing=3D"0" role=3D"presentation" =
style=3D"float:none;display:inline-table;"><tbody><tr><td style=3D"padding:=
0px 8px;vertical-align:middle;"><table border=3D"0" cellpadding=3D"0" cells=
pacing=3D"0" role=3D"presentation"><tbody><tr><td><a href=3D"https://u22105=
166.ct.sendgrid.net/ls/click?upn=3Du001.cgvWDPhZtte8w9SmEnPFbCjhHpk4RN-2F6z=
AcN-2BRTnP8E0uuKiaW86QkA6y2yXLoEGK5CJ_gPVpeftr358-2FiDObqCqMyqOYO42W7R9WgJT=
IVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFbfz5KSmhpfBnr-2BFluWFPn5jFOg=
f4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZVGoSh-2FEm6CZAZszqAfUUqz6jx=
fKEuPuzKzqxhUPGNTcvbEMpuV7LRI-2FUfFsJ2kJpfCvTtrLAjDB-2B-2BavMlG-2BCiDEyfNhm=
XSwoXZxoqeUSnvzFUa2ETwLjw-2FNrokNZHTPE3guBphbt9CJr-2Ft14I4talxDSBnjrx-2Fd8E=
-2Bf34yUoBrPO3PiJQ-2BznAxlzi4GMdyDYHaraD6RLc6s3kfvL8W4-3D" target=3D"_blank=
"><img src=3D"https://cdn.braze.eu/appboy/communication/assets/image_assets=
/images/5fad22ef3cf3a022c3f7eef9/original.png?1605182191" style=3D"border-r=
adius:3px;display:block;" width=3D"18" height=3D"18" alt=3D"instagram"></a>=
</td></tr></tbody></table></td></tr></tbody></table></td><td class=3D"socia=
l-btn social-btn-last" style=3D"padding-right: 0;"><table align=3D"center" =
border=3D"0" cellpadding=3D"0" cellspacing=3D"0" role=3D"presentation" styl=
e=3D"float:none;display:inline-table;"><tbody><tr><td style=3D"padding:0px =
8px;vertical-align:middle;"><table border=3D"0" cellpadding=3D"0" cellspaci=
ng=3D"0" role=3D"presentation" style=3D"border-radius:3px;width:17px;"><tbo=
dy><tr><td style=3D"font-size:0;height:17px;vertical-align:middle;width:17p=
x;"><a href=3D"https://u22105166.ct.sendgrid.net/ls/click?upn=3Du001.cgvWDP=
hZtte8w9SmEnPFbNpdg202SMuhJi1aSMkf2a2u1hHPA4a02fmkm01RvMaNzOKI_gPVpeftr358-=
2FiDObqCqMyqOYO42W7R9WgJTIVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFbfz=
5KSmhpfBnr-2BFluWFPn5jFOgf4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZVG=
oSh-2FEm6CZAZszqAfWvsUmX2IRETJpQ02RXc8mlLIz4h2gIaymhzpWTyu1HGazCl0JlZls4jco=
XzvPVa64ZFPmwJH7i0XrzjlOzRmoQg1n1SF0SzGeK7F8JHjai2rzhMtuPG9cYpFJv7mmngJ2c-2=
FpqV2Qsk0ejkkcLknxBuz3dXhoPWm88SUb203k9sQDFzpbNlfi9ByHizVvN5Nrk-3D" target=
=3D"_blank"><img src=3D"https://cdn.braze.eu/appboy/communication/assets/im=
age_assets/images/5fad22ef6242a17c0642686c/original.png?1605182191" style=
=3D"border-radius:3px;display:block;" width=3D"18" height=3D"18" alt=3D"pin=
terest"></a></td></tr></tbody></table></td></tr></tbody></table></td></tr><=
/tbody></table></div><div style=3D"font-family:g, Arial, sans-serif;text-al=
ign:center;padding:16px 0 6px;color:#FFF;font-weight:400;font-size:14px;lin=
e-height:20px;">2008-2025 =A9 All rights reserved.</div><!--]--><!--]--></d=
iv></td><!--[if mso]></tr></table></td><![endif]--></tr><tr class=3D"grid-r=
ow"><!--[if mso]><td colspan=3D"2" width=3D"100%" style=3D"width:480px; pad=
ding: 8px 20px;"><table role=3D"presentation" width=3D"100%" style=3D"width=
:480px;"><tr><![endif]--><td class=3D"grid-row__wrapper-middle grid-row__wr=
apper grid-row__wrapper-single" colspan=3D"2" style=3D"padding: 8px 32px; v=
ertical-align: middle; width: 100%;"><div class=3D"grid-column" position=3D=
"0" width=3D"full" style=3D"height: 100%; width: 100%;"><!--[--><!--[--><di=
v style=3D"font-family:g, Arial, sans-serif;text-align:center;padding:8px 0=
px;color:#63687a;font-size:12px;line-height:20px;" class=3D"util-links"><a =
href=3D"https://u22105166.ct.sendgrid.net/ls/click?upn=3Du001.cgvWDPhZtte8w=
9SmEnPFbFowXFvBUTTl7RUbVBedYU-2FIRHLUPcuGopt-2BsjDiq2ZWrMAsAryBxPLqxrmmYiH4=
wehywnn4SNUbjxGodO1XbvwYRKbhZ-2FGMYX239sOEnb1h9pWuV-2FwRBbZJoRRv9EU9l0XuaVr=
iSl4WxoIrT-2BzebGaZIOGqAPdn-2BESvRyTJ3jyW4aea_gPVpeftr358-2FiDObqCqMyqOYO42=
W7R9WgJTIVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFbfz5KSmhpfBnr-2BFluW=
FPn5jFOgf4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZVGoSh-2FEm6CZAZszqA=
fVgse2eoIV5HuZj3M6HX0DwyI4wsqKT5LBatxsvMD6WkBGGbBQRurEfEBQqdPaknQWUb-2FyCyx=
30nxEtejKA400XpfC6YqKwke5yJkqollTa5Q-2Bl1T1XylsNqX8iOQuMJpnC3rTBYkg4OUamjhH=
B30vzCzW5bPfPPZC-2FG8dEnQoj7mwrer-2FrzvcBF5p0YBskhY4-3D" target=3D"_blank" =
style=3D"font-size: 12px; text-decoration: none;">Become a Supply Partner</=
a> | <a href=3D"https://u22105166.ct.sendgrid.net/ls/click?upn=3Du001.cgvWD=
PhZtte8w9SmEnPFbFowXFvBUTTl7RUbVBedYU9ez4vVrBCM1jxyxcSBBWnsVEIBW0d9cl5FxsmO=
meJqG6nK3j4bhy8FbEb0Bh-2B3LsubADgr5kAdpMMoTnCZ58CDgnzk5tJqYJTtQzTmh5n-2FzFy=
p8Y4AlW4LcwG3GDm2p9g2lBnPfBE6m6UFlZheLCDMaWZ9NPy3wVPQS8DU-2FUbKVQ-3D-3DH_Lt=
_gPVpeftr358-2FiDObqCqMyqOYO42W7R9WgJTIVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtB=
p5V1rA5CkFbfz5KSmhpfBnr-2BFluWFPn5jFOgf4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLo=
SLbHt7odTDZVGoSh-2FEm6CZAZszqAfW6dND8l2Tkbgl3owvJmPkMBzE0GWG4iO5F8-2BhkKd9B=
MRkcLbN1POy8nfs9XYNsPE-2FfUKTptiwTY83G0tiY-2B8WOrk7JdtAMjuCy0i25xxOeEW7Jv6h=
hI2-2By9j0xASUP8q7UE1PdZd7ht29jyhjTWjEFgkFT1Oi0Zh8URKAnXCx-2Fh9yfFSdmeyZRji=
MOaAboe1g-3D" target=3D"_blank" style=3D"font-size: 12px; text-decoration: =
none;">Contact us</a> | <a href=3D"https://u22105166.ct.sendgrid.net/ls/cli=
ck?upn=3Du001.cgvWDPhZtte8w9SmEnPFbECt4FFVs29dmTEMvO6morTVzzyDGkvkU38PSi-2F=
aTlhMGtIOdpYJH6Rn75SRRKyD-2Bi1ojoeZwoFhfjZaMhKG6S4-3D9DAj_gPVpeftr358-2FiDO=
bqCqMyqOYO42W7R9WgJTIVxyNoAdIW36QULrzk9iDI9zkHcis1K9txNtBp5V1rA5CkFbfz5KSmh=
pfBnr-2BFluWFPn5jFOgf4lnfDHr-2Fzxy0xkDdJkxywgZaWqqS5-2FLoSLbHt7odTDZVGoSh-2=
FEm6CZAZszqAfWEjaq0hT4qfQn8lOfPVtnmFTI8DWrpGGNQJF0o0moCcP0jknSk1RTIXsHxQWFD=
pKES3hRXW3bak6RyFxpWihJs-2B9ppteTJ3Ge6wx0kLvnKQ0s55-2BTlgfSfBWHBH-2FksUkXED=
FMPNN3HwQBuup6DSqP2nSDufKKeStdtAHFfLJ-2FskIospf75rcpydMwczz9v-2ByI-3D" targ=
et=3D"_blank" style=3D"font-size: 12px; text-decoration: none;">FAQ</a></di=
v><div style=3D"font-family:g, Arial, sans-serif;text-align:center;padding:=
8px 0px;color:#63687a;font-size:12px;line-height:20px;"><!--[-->GetYourGuid=
e.com | GetYourGuide Deutschland GmbH, Sonnenburger Strasse 73, 10437 Berli=
n, Germany. Managing directors: Johannes Reck, Tao Tao, Nils Chrestin. Regi=
stered at Amtsgericht Charlottenburg under HRB 132059 B VAT ID No. DE276456=
081. The content of this email may be confidential and intended for the rec=
ipient specified in message only. It is strictly forbidden to share any par=
t of this message with any third party. If you received this message by mis=
take, please reply to this message and follow with its deletion along with =
its attachment<!--]--></div><!--]--><!--]--></div></td><!--[if mso]></tr></=
table></td><![endif]--></tr><!--]--></tbody></table><!--]-->


<img src=3D"https://u22105166.ct.sendgrid.net/wf/open?upn=3Du001.-2BGqGfY3y=
YVrxQKdrrzQkORWd6g7ChQuZd-2BOrCYSJz6WOFQmd-2FXrE3ieVgebYKqaYZFUr4JYuaapPuqi=
kGHczQ5UHo3Yn87H5yftp1iLFxNDaGYyAQ62WI5hKsJvE8ZRi-2BKaXGEtnRUsD7Mk2DBycnmET=
CvzjqRjTf6yqccp-2BU8KjHvGeRh-2Fupvo7-2Fkb15Pp-2FcLJEzDLfGyo6F7yUA46hrjvnYRz=
qaO4iskUIcdw65eHg74ibh8f1Bu8ql8E10Tsvpy-2BNMRa0M4CZK0U7vIdZg5pHaj9QcHEh4cSD=
-2BGOu7lo-2Fs2jrZvg6JRZ-2FEEif56-2BVR87ZaB9wByGio2QRkIsBNYNjxKs429lEHPoZ24J=
FP5g-3D" alt=3D"" width=3D"1" height=3D"1" border=3D"0" style=3D"height:1px=
 !important;width:1px !important;border-width:0 !important;margin-top:0 !im=
portant;margin-bottom:0 !important;margin-right:0 !important;margin-left:0 =
!important;padding-top:0 !important;padding-bottom:0 !important;padding-rig=
ht:0 !important;padding-left:0 !important;"/></body>';

            $command = 'Extract data with JSON object format as 

            {
                "booking_confirmation_code" : "TEST-123",
                "booking_channel" : "Airbnb",
                "booking_note" : "note booking",
                "tour_name" : "Jogja Night Food Tour",
                "tour_date" : "2025-07-05 18:30:00",
                "participant_name" : "John Doe",
                "participant_phone" : "+6285743112112",
                "participant_email" : "guide@vertikaltrip.com",
                "participant_total" : 2,
                "p_time" : "night or day",
                "p_location" : "yogyakarta or bali"
            }

            Set "" if don\'t have data';


            
            $openai = New OpenAIHelper;
            $data = $openai->openai($text,$command);
            //$data = str_ireplace("```php", "", $data);
            //$data = str_ireplace("```", "", $data);
            $booking_json = json_decode($data);
            

            
            if($booking_json->p_time=="night" && $booking_json->p_location=="yogyakarta")
            {
                $booking_json->p_product_id = 7424;
            }
            else if($booking_json->p_time=="day" && $booking_json->p_location=="yogyakarta")
            {
                $booking_json->p_product_id = 10091;
            }
            else
            {
                return response('DATA TIDAK LENGKAP', 200)->header('Content-Type', 'text/plain');
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
