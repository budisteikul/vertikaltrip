<?php

namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;

use budisteikul\vertikaltrip\Helpers\BokunHelper;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\PaymentHelper;
use budisteikul\vertikaltrip\Helpers\ContentHelper;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;
use budisteikul\vertikaltrip\Helpers\LogHelper;
use budisteikul\vertikaltrip\Helpers\ReviewHelper;
use budisteikul\vertikaltrip\Helpers\ImageHelper;

use budisteikul\vertikaltrip\Models\Category;
use budisteikul\vertikaltrip\Models\Review;
use budisteikul\vertikaltrip\Models\Product;
use budisteikul\vertikaltrip\Models\Channel;
use budisteikul\vertikaltrip\Models\Page;
use budisteikul\vertikaltrip\Models\Slug;
use budisteikul\vertikaltrip\Models\Partner;
use budisteikul\vertikaltrip\Models\Shoppingcart;
use budisteikul\vertikaltrip\Models\ShoppingcartProduct;
use budisteikul\vertikaltrip\Models\ShoppingcartCancellation;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;


use budisteikul\vertikaltrip\Helpers\ProductHelper;

class APIController extends Controller
{
    
    public function __construct(Request $request)
    {
        
    }

    

    public function cancellation($sessionId,$confirmationCode)
    {
        $shoppingcart = Shoppingcart::where('session_id',$sessionId)->where('confirmation_code',$confirmationCode)->first();
        if($shoppingcart)
        {
            $check = ShoppingcartCancellation::where('shoppingcart_id', $shoppingcart->id)->first();
            if(!$check)
            {
                $shoppingcart_cancellation = new ShoppingcartCancellation();
                $shoppingcart_cancellation->status = 1;
                $shoppingcart_cancellation->shoppingcart_id = $shoppingcart->id;
                $shoppingcart_cancellation->amount = $shoppingcart->shoppingcart_payment->amount;
                $shoppingcart_cancellation->save();
            }
        }
    }

    public function config(Request $request)
    {

        
        $analytic = LogHelper::analytic();

        if(str_contains(GeneralHelper::url(), 'jogjafoodtour') || str_contains(GeneralHelper::url(), 'vertikaltrip'))
        {

            
            $headerBox = '
            <img src="'.config('site.assets').'/img/header/'.config('site.logo').'" alt="Jogja Food Tour" />
            <hr class="hr-theme" />
            <p class="text-faded">
                Join us on this experience to try authentic dishes, play traditional games, travel on a becak, learn interesting fun facts about city, interact with locals and many more.
                <br />
                Enjoy Jogja Like Locals!
            </p>';

            $featured = '
                <div class="row pb-0">
                    <div class="col-lg-8 text-center mx-auto">
                        <h3 class="section-heading" style="margin-top:50px;">Yogyakarta: The way to this city’s heart is through its food</h3>
                        <div class="col-lg-8 text-center mx-auto">
                            Perhaps better known for being a bastion of history and culture, Yogyakarta is also the unofficial culinary capital of Indonesia
                        </div>
                        <br />
                        <hr class="hr-theme" />
                    </div>
                </div>

                 <div class="row text-center">
                    <div class="col-md-8 mx-auto">
                        <img src="'.config('site.assets').'/img/content/silkwinds.jpg" alt="Silkwinds | Jogja Food Tour" class="img-fluid rounded" />
                        <img src="'.config('site.assets').'/img/content/silkwinds-magazine-logo.png" alt="Silkwinds | Jogja Food Tour" style={{ marginTop: "4px" }} class="img-fluid rounded" />
                        <span class="caption text-muted"><a class="text-muted" rel="noreferrer" target="_blank" href="https://www.silverkris.com/yogyakarta-the-way-to-this-citys-heart-is-through-its-food/">Silkwinds Magazine</a></span>
                    </div>
                </div>
            ';

            $siteTitle = config('site.title');

            $headerBackground = config('site.assets').'/img/header/background.jpg';

            $tourGuide_title = 'Meet The Jogja Foodie Guide';
            $tourGuide_description = 'Is a group of unique individuals who share the same passions. We do believe you will have fun and experience something new with us';

            $tourGuides[] = [
                'image' => config('site.assets').'/img/guide/kalika02.jpg',
                'name' => 'Kalika',
                'description' => '',
            ];

            $tourGuides[] = [
                'image' => config('site.assets').'/img/guide/anisa01.jpeg',
                'name' => 'Anisa',
                'description' => '',
            ];
        
            
            $services[] = [
                'icon' => '<i class="fa fa-4x fa-bolt mb-2" style="color: #c53c46"></i>',
                'name' => 'Instant Confirmation',
                'description' => 'To secure your spot while keeping your plans flexible. Your booking are confirmed automatically!',
            ];

            $services[] = [
                'icon' => '<i class="fas fa-4x fa-thumbs-up mb-2" style="color: #c53c46"></i>',
                'name' => 'Great Local Food',
                'description' => 'Too many options and afraid of tourist traps? We only take you to great places where locals go!',
            ];

            $services[] = [
                'icon' => '<i class="fa fa-4x fa-utensils mb-2" style="color: #c53c46"></i>',
                'name' => 'Customizable',
                'description' => 'We can accommodate many food restrictions — Just fill it on the booking form!',
            ];

            $services[] = [
                'icon' => '<i class="fas fa-4x fa-history mb-2" style="color: #c53c46"></i>',
                'name' => 'Full Refund',
                'description' => 'Have your plans changed? No worries! You can cancel the booking anytime!',
            ];

            $company = '<strong>VERTIKAL TRIP</strong><br />Perum Guwosari Blok XII No 190<br>Bantul  55751 INDONESIA<br /><i class="fas fa-envelope"></i> Email : guide@vertikaltrip.com<br /><i class="fab fa-whatsapp-square"></i> WhatsApp : +62 895 3000 0030';
            $footerTitle = '<span style="font-size:12px;">© 2018 - 2024 Jogja Food Tour (Vertikal Trip). All Rights Reserved</span>';

            /*
            $footerPaymentChannels = [
                '<img height="30" class="mt-2" src="'.config('site.assets').'/img/footer/line-7.png" alt="Payment Channels" /><br />'
            ];
            */
            $footerPaymentChannels = [
                '<img height="30" class="mt-2" src="'.config('site.assets').'/img/footer/line-1.png" alt="Payment Channels" /><br />',
                '<img height="30" class="mt-2" src="'.config('site.assets').'/img/footer/line-5.png" alt="Payment Channels" /><br />'
            ];
            
            $usefullLink[] = [
                'title' => 'Meeting Point',
                'link' => 'https://map.jogjafoodtour.com',
                'type' => 'outsite'
            ];

            $usefullLink[] = [
                'title' => 'Careers',
                'link' => '/page/careers',
                'type' => 'insite'
            ];
        }
        else
        {


            

            $headerBox = '
            <img src="'.config('site.assets').'/img/header/vertikaltrip.png" alt="Vertikal Trip" width="250" />
            <hr class="hr-theme" />
            
            <h3 class="text-white" style="line-height:1.5">MUST TRY THE COMBINATION CULTURE AND GASTRONOMY ACTIVIES IN INDONESIA</h3>
            
            <p class="text-faded">
                Join us on this activity to try authentic local dishes, learn interesting fun facts about city, interact with locals and many more. Our team will accompany your journey and making you feel like a local!
            </p>
            
            ';

            $featured = '
                
                <div class="row pb-0">
                    <div class="col-lg-8 text-center mx-auto">
                        <h3 class="section-heading" style="margin-top:50px;">Featured on </h3>
                        <hr class="hr-theme" />
                        <a class="text-muted" rel="noreferrer" target="_blank" href="https://www.silverkris.com/yogyakarta-the-way-to-this-citys-heart-is-through-its-food/"><img src="'.config('site.assets').'/img/content/silkwinds-magazine-logo.png" alt="Silkwinds | Jogja Food Tour" style={{ marginTop: "4px" }} class="img-fluid rounded img-thumbnail" /></a>
                    </div>
                </div>

                 <div class="row text-center">
                    <div class="col-md-8 mx-auto">
                        
                    </div>
                </div>
                
            ';

            $siteTitle = env('APP_NAME');

            $headerBackground = config('site.assets').'/img/header/background-food.jpg';

            $tourGuide_title = 'Meet the Vertikal Trip Team';
            $tourGuide_description = 'Is a group of unique individuals who share the same passions. We do believe you will have fun and experience something new with us';


            $tourGuides[] = [
                'image' => config('site.assets').'/img/guide/kalika02.jpg',
                'name' => 'Kalika',
                'description' => '',
            ];

            $tourGuides[] = [
                'image' => config('site.assets').'/img/guide/anisa01.jpeg',
                'name' => 'Anisa',
                'description' => '',
            ];
        
            $tourGuides[] = [
                'image' => config('site.assets').'/img/guide/dea01.jpeg',
                'name' => 'Dea',
                'description' => '',
            ];
        
            $tourGuides[] = [
                'image' => config('site.assets').'/img/guide/dharma01.jpeg',
                'name' => 'Dharma',
                'description' => '',
            ];

            $services[] = [
                'icon' => '<i class="fa fa-4x fa-bolt mb-2" style="color: #c53c46"></i>',
                'name' => 'Instant Confirmation',
                'description' => 'To secure your spot while keeping your plans flexible. Your booking are confirmed automatically!',
            ];

            $services[] = [
                'icon' => '<i class="fas fa-4x fa-phone-alt mb-2" style="color: #c53c46"></i>',
                'name' => '24/7 Support',
                'description' => 'Stay Connected with us! With 24/7 Support.',
            ];

            $services[] = [
                'icon' => '<i class="fas fa-4x fa-history mb-2" style="color: #c53c46"></i>',
                'name' => 'Full Refund',
                'description' => 'Have your plans changed? No worries! You can cancel the booking anytime!',
            ];

            $services[] = [
                'icon' => '<i class="fas fa-4x fa-thumbs-up mb-2" style="color: #c53c46"></i>',
                'name' => 'Great Local Food',
                'description' => 'Too many options and afraid of tourist traps? We only take you to great places where locals go!',
            ];

            $company = '<strong>VERTIKAL TRIP</strong><br />Perum Guwosari Blok XII No 190<br>Bantul  55751 INDONESIA<br /><i class="fas fa-envelope"></i> Email : guide@vertikaltrip.com<br /><i class="fab fa-whatsapp-square"></i> WhatsApp : +62 895 3000 0030';
            $footerTitle = '<span style="font-size:12px;">© 2018 - 2024 VERTIKAL TRIP. All Rights Reserved</span>';

            $footerPaymentChannels = [
                '<img height="30" class="mt-2" src="'.config('site.assets').'/img/footer/line-1.png" alt="Payment Channels" /><br />',
                '<img height="30" class="mt-2" src="'.config('site.assets').'/img/footer/line-5.png" alt="Payment Channels" /><br />'
            ];

            $usefullLink[] = [
                'title' => 'Meeting Point',
                'link' => 'https://map.vertikaltrip.com',
                'type' => 'outsite'
            ];

            $usefullLink[] = [
                'title' => 'Careers',
                'link' => '/page/careers',
                'type' => 'insite'
            ];
            
        }
        

        

        
        $payment_enable = config('site.payment_enable');
        $payment_array = explode(",",$payment_enable);
        $jscripts = array();

        $jscripts[] = [ config('site.assets') .'/js/intlTelInput.min.js',false];
        if(in_array('xendit',$payment_array)) {
            $jscripts[] = ['https://js.xendit.co/v1/xendit.min.js',false];
            $jscripts[] = [ config('site.assets') .'/js/payform.min.js',true];
        }
        if(in_array('stripe',$payment_array)) $jscripts[] = ['https://js.stripe.com/v3/', true];
        $paypal_sdk = 'https://www.paypal.com/sdk/js?client-id='.env("PAYPAL_CLIENT_ID").'&currency='. env("PAYPAL_CURRENCY").'&disable-funding=credit,card';
        if(in_array('paypal',$payment_array)) $jscripts[] = [$paypal_sdk, true];



        



        $dataPrivacyTerm[] = [
            'title' => 'Terms and Conditions',
            'link' => '/page/terms-and-conditions'
        ];

        $dataPrivacyTerm[] = [
            'title' => 'Privacy Policy',
            'link' => '/page/privacy-policy'
        ];

        

        

        $siteDescription = config('site.description');

        $footerPartners = [
                '<a target="_blank" rel="noreferrer noopener" href="https://www.getyourguide.com/yogyakarta-l349/yogyakarta-night-walking-and-food-tour-t429708/"><img height="30" class="mb-1 mt-2 mr-2 img-thumbnail" src="'.config('site.assets').'/img/footer/getyourguide-logo.png" alt="GetYourGuide" /></a>',
                '<a target="_blank" rel="noreferrer noopener" href="https://www.airbnb.com/experiences/434368"><img height="30" class="mb-1 mt-2 mr-2 img-thumbnail" src="'.config('site.assets').'/img/footer/airbnb-logo.png" alt="Airbnb" /></a>',
                '<a target="_blank" rel="noreferrer noopener" href="https://www.tripadvisor.com/Attraction_Review-g14782503-d17523331-Reviews-VERTIKAL_TRIP-Yogyakarta_Yogyakarta_Region_Java.html"><img height="30" class="mb-1 mt-2 mr-2 img-thumbnail" src="'.config('site.assets').'/img/footer/tripadvisor-logo.png" alt="Tripadvisor" /></a>',
                '<a target="_blank" rel="noreferrer noopener" href="https://www.viator.com/tours/Yogyakarta/Food-Journey-in-Yogyakarta-at-Night/d22560-110844P2?pid=P00167423&mcid=42383&medium=link"><img height="30" class="mb-1 mt-2 mr-2 img-thumbnail" src="'.config('site.assets').'/img/footer/viator-logo-01.png" alt="Viator" /></a>',
            ];

        if(str_contains(GeneralHelper::url(), 'ubudfoodtour'))
        {
            $siteContent = ['header','service','guide'];
        }
        else
        {
            $siteContent = ['header','service','feature','review','guide'];
        }
        

        $response = [
            'mainUrl' => GeneralHelper::url(),

            'siteContent' => $siteContent,

            'jscripts' => $jscripts,
            'analytic' => $analytic,
            'assets' => config('site.assets'),
            'featured' => $featured,
            'services' => $services,
            'siteTitle' => $siteTitle,
            'siteDescription' => $siteDescription,
            'headerBox' => $headerBox,
            'headerBackground' => $headerBackground,

            'tourGuides' => $tourGuides,
            'tourGuide_title' => $tourGuide_title,
            'tourGuide_description' => $tourGuide_description,

            'footerPartnersTitle' => '<b style="font-size:16px">Also available on</b>',
            'footerPaymentChannelsTitle' => '<b style="font-size:16px">Ways you can pay</b>',
            'footerUsefullLinksTitle' => '<b style="font-size:16px">Useful Links</b>',
            'footerPrivacytermsTitle' => '<b style="font-size:16px">Privacy & Terms</b>',
            'footerWhatsappTitle' => '<b style="font-size:16px">Looking for the info?</b>',

            'footerUsefullLinks' => $usefullLink,
            'footerPrivacyterms' => $dataPrivacyTerm,
            'footerWhatsapp' => '6289530000030',
            'footerCompany' => $company,
            'footerTitle' => $footerTitle,
            'footerPartners' => $footerPartners,
            'footerPaymentChannels' => $footerPaymentChannels
        ];

        $trackingCode = $request->input('trackingCode');
        $partner = Partner::where('tracking_code',$trackingCode)->first();
        if($partner)
        {
            $response['trackingCode'] = $trackingCode;
        }

        return response()->json($response, 200);
    }

    public function navbar($sessionId)
    {
        
        if(str_contains(GeneralHelper::url(), 'jogjafoodtour') || str_contains(GeneralHelper::url(), 'vertikaltrip'))
        {
            //$slug = Slug::where('type','category')->where('slug','yogyakarta')->latest('id')->firstOrFail();
            //$categories = Category::where('parent_id',0)->where('id',$slug->link_id)->get();
            $categories = Category::where('parent_id',0)->select(['name','slug'])->get();
            $logo = config('site.assets').'/img/header/'.config('site.logo');
        }
        else if(str_contains(GeneralHelper::url(), 'ubudfoodtour'))
        {
            $slug = Slug::where('type','category')->where('slug','bali')->latest('id')->firstOrFail();
            $categories = Category::where('parent_id',0)->where('id',$slug->link_id)->get();
            $logo = config('site.assets').'/img/header/vertikaltrip.png';
        }
        else
        {
            $categories = Category::where('parent_id',0)->select(['name','slug'])->get();
            $logo = config('site.assets').'/img/header/vertikaltrip.png';
        }


        return response()->json([
            'message' => 'success',
            'logo' => $logo,
            'categories' => $categories,
            'url' => GeneralHelper::url(),
        ], 200);
    }

    

    public function review_count()
    {

        $count = ReviewHelper::review_count();
        $rate = ReviewHelper::review_rate();
        return response()->json([
            'message' => 'success',
            'count' => $count,
            'rate' => $rate['rate'],
            'star' => $rate['star']
        ], 200);
    }

    public function json_ld($product_id)
    {
        $rating = ReviewHelper::review_rate($product_id);
        $count = ReviewHelper::review_count($product_id);
        $product = Product::findOrFail($product_id);
        $content = BokunHelper::get_product($product->bokun_id);

        $image = "";
        $image_item = "";
        foreach($product->images->sortBy('sort') as $image)
        {
            $image_item .= '"'.ImageHelper::urlImageGoogle($image->public_id,600,400).'",';
        }
        $image_item = substr($image_item, 0, -1);
        $image = '['.$image_item.']';


        $aggregateRating = '';
        if($count>0)
        {
            $aggregateRating = '
            "review": {
                "@type": "Review",
                "reviewRating": {
                    "@type": "Rating",
                    "ratingValue": "'.$rating['rate'].'",
                    "bestRating": "5"
                },
                "author": {
                    "@type": "Person",
                    "name": "Travelers"
                }
            },
            "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": "'.$rating['rate'].'",
                "reviewCount": "'.$count.'"
            },';
        }

        $json = '
        {
            "@context": "https://schema.org/",
            "@type": "Product",
            "name": "'.$product->name.'",
            "image": ['.$image_item.'],
            "description": "'.$content->excerpt.'",
            "sku": "'.$product->id.'",
            "mpn": "'.$product->bokun_id.'",
            "brand": {
                "@type": "Brand",
                "name": "'.config("site.brand").'"
            },
            '.$aggregateRating.'
            "offers": {
                "@type": "Offer",
                "url": "'.env("APP_URL").'/tour/'.$product->slug.'",
                "priceCurrency": "'.$content->nextDefaultPriceMoney->currency.'",
                "price": "'.$content->nextDefaultPriceMoney->amount.'",
                "priceValidUntil": "'.date('Y').'-12-31",
                "itemCondition": "https://schema.org/UsedCondition",
                "availability": "https://schema.org/InStock",
                "seller": {
                    "@type": "Organization",
                    "name": "'.config("site.organization").'"
                }
            }
        }';
        return json_encode(json_decode($json), JSON_UNESCAPED_SLASHES);
    }

    public function schedule_jscript()
    {
        $jscript = '
        jQuery(document).ready(function($) {
            $.fn.dataTableExt.sErrMode = \'throw\';
            var table = $("#dataTables-example").DataTable(
            {
                "processing": true,
                "serverSide": true,
                "ajax": 
                {
                    "url": "'.url('/api').'/schedule",
                    "type": "POST",
                },
                "scrollX": true,
                "language": 
                {
                    "paginate": 
                    {
                        "previous": "<i class=\"fa fa-step-backward\"></i>",
                        "next": "<i class=\"fa fa-step-forward\"></i>",
                        "first": "<i class=\"fa fa-fast-backward\"></i>",
                        "last": "<i class=\"fa fa-fast-forward\"></i>"
                    },
                    "aria": 
                    {
                        "paginate": 
                        {
                            "first":    "First",
                            "previous": "Previous",
                            "next":     "Next",
                            "last":     "Last"
                        }
                    }
                },
                "pageLength": 5,
                "order": [[ 0, "desc" ]],
                "columns": [
                    {data: "date", name: "date", orderable: true, searchable: false, visible: false},
                    {data: "name", name: "name", className: "auto", orderable: false},
                    {data: "date_text", name: "date_text", className: "auto", orderable: false},
                    {data: "people", name: "people", className: "auto", orderable: false},
                ],
                "dom": "tp",
                "pagingType": "full_numbers"
            });
            
      });';
      return response($jscript)->header('Content-Type', 'application/javascript');
    }

    public function schedule(Request $request)
    {
        $resources = ShoppingcartProduct::whereHas('shoppingcart', function ($query) {
                return $query->where('booking_status','CONFIRMED');
        })->where('date', '>=', date('Y-m-d'))->whereNotNull('date');
        return Datatables::eloquent($resources)
        ->addColumn('name', function($resources){
                    $shoppingcart_id = $resources->shoppingcart->id;
                    $question = BookingHelper::get_answer_contact($resources->shoppingcart);
                    $name = $question->firstName;
                    return $name;
                })
        ->addColumn('date_text', function($id){
                    $date_text = GeneralHelper::dateFormat($resources->date,10);
                    return $date_text;
                })
        ->addColumn('people', function($id){
                    $people = 0;
                    foreach($resources->shoppingcart_product_details as $shoppingcart_product_detail)
                    {
                        $people += $shoppingcart_product_detail->people;
                    }
                    return $people;
                })
        ->toJson();
    }

    

    public function downloadQrcode($sessionId,$id)
    {
        $shoppingcart = Shoppingcart::where('confirmation_code',$id)->where('session_id',$sessionId)->firstOrFail();
        $qrcode = BookingHelper::generate_qrcode($shoppingcart);
        list($type, $qrcode) = explode(';', $qrcode);
        list(, $qrcode)      = explode(',', $qrcode);
        $qrcode = base64_decode($qrcode);
        $path = Storage::disk('local')->put($shoppingcart->confirmation_code .'.png', $qrcode);
        return response()->download(storage_path('app').'/'.$shoppingcart->confirmation_code .'.png')->deleteFileAfterSend(true);
    }

    public function instruction($sessionId,$id)
    {
        $shoppingcart = Shoppingcart::where('confirmation_code',$id)->where('session_id',$sessionId)->firstOrFail();
        $pdf = BookingHelper::create_instruction_pdf($shoppingcart);
        return $pdf->download('Instruction-'. $shoppingcart->confirmation_code .'.pdf');
    }

    public function manual($sessionId,$id)
    {
        $shoppingcart = Shoppingcart::where('confirmation_code',$id)->where('session_id',$sessionId)->firstOrFail();
        $pdf = BookingHelper::create_manual_pdf($shoppingcart);
        return $pdf->download('Manual-'. $shoppingcart->confirmation_code .'.pdf');
    }

    public function invoice($sessionId,$id)
    {
        $shoppingcart = Shoppingcart::where('confirmation_code',$id)->where('session_id',$sessionId)->firstOrFail();
        $pdf = BookingHelper::create_invoice_pdf($shoppingcart);
        return $pdf->download('Invoice-'. $shoppingcart->confirmation_code .'.pdf');
    }
    
    public function ticket($sessionId,$id)
    {
        $shoppingcart_product = ShoppingcartProduct::where('product_confirmation_code',$id)->whereHas('shoppingcart', function($query) use ($sessionId){
            return $query->where('session_id', $sessionId)->where('booking_status','CONFIRMED');
        })->firstOrFail();
        $pdf = BookingHelper::create_ticket_pdf($shoppingcart_product);
        return $pdf->download('Ticket-'. $shoppingcart_product->product_confirmation_code .'.pdf');
    }

    public function categories()
    {
        
        if(str_contains(GeneralHelper::url(), 'jogjafoodtour') || str_contains(GeneralHelper::url(), 'vertikaltrip'))
        {
            $slug = Slug::where('type','category')->where('slug','yogyakarta')->latest('id')->firstOrFail();
            $category = Category::where('id',$slug->link_id)->firstOrFail();
            $dataObj = ContentHelper::view_category($category);
        }
        else if(str_contains(GeneralHelper::url(), 'ubudfoodtour'))
        {
            $slug = Slug::where('type','category')->where('slug','bali')->latest('id')->firstOrFail();
            $category = Category::where('id',$slug->link_id)->firstOrFail();
            $dataObj = ContentHelper::view_category($category);
        }
        else
        {
            $dataObj = ContentHelper::view_categories();
        }
        

        

        return response()->json([
            'message' => 'success',
            'categories' => $dataObj
        ], 200);
        
    }

    public function category($slug)
    {
        $category = Category::where('slug',$slug)->first();
        if($category)
        {
            $dataObj = ContentHelper::view_category($category);
        }
        else
        {
            $slug = Slug::where('type','category')->where('slug',$slug)->latest('id')->firstOrFail();
            $category = Category::where('id',$slug->link_id)->first();
            $dataObj = ContentHelper::view_category($category);
        }


        
        return response()->json([
            'message' => 'success',
            'category' => $dataObj,
        ], 200);
    }

    public function page($slug)
    {
        $page = Page::where('slug',$slug)->first();
        if($page)
        {
            $dataObj[] = array(
                'title' => $page->title,
                'content' => $page->content,
            );
        }
        else
        {
            $slug = Slug::where('type','page')->where('slug',$slug)->latest('id')->firstOrFail();
            $page = Page::where('id',$slug->link_id)->first();
            $dataObj[] = array(
                'title' => $page->title,
                'content' => $page->content,
            );
        }


        

        return response()->json([
                'page' => $dataObj
            ], 200);
    }

    public function product($slug)
    {
        $product = Product::where('slug',$slug)->first();
        if($product)
        {
            $dataObj = ContentHelper::view_product($product);
        }
        else
        {
            $slug = Slug::where('type','product')->where('slug',$slug)->latest('id')->firstOrFail();
            $product = Product::where('id',$slug->link_id)->first();
            $dataObj = ContentHelper::view_product($product);
        }

        $json_ld = self::json_ld($product->id);

        $dataObj1[] = $dataObj;

        return response()->json([
            'message' => 'success',
            'product' => $dataObj1,
            'json_ld' => $json_ld
        ], 200);

    }

    public function review_jscript()
    {
        $jscript = '
        jQuery(document).ready(function($) {
            $.fn.dataTable.ext.errMode = \'none\';
            var table = $("#dataTables-example").DataTable(
            {
                "processing": true,
                "serverSide": true,
                "ajax": 
                {
                    "url": "'.url('/api').'/review",
                    "type": "POST",
                },
                "scrollX": true,
                "language": 
                {
                    "paginate": 
                    {
                        "previous": "<i class=\"fa fa-step-backward\"></i>",
                        "next": "<i class=\"fa fa-step-forward\"></i>",
                        "first": "<i class=\"fa fa-fast-backward\"></i>",
                        "last": "<i class=\"fa fa-fast-forward\"></i>"
                    },
                    "aria": 
                    {
                        "paginate": 
                        {
                            "first":    "First",
                            "previous": "Previous",
                            "next":     "Next",
                            "last":     "Last"
                        }
                    }
                },
                "pageLength": 5,
                "order": [[ 0, "desc" ]],
                "columns": [
                    {data: "date", name: "date", orderable: true, searchable: false, visible: false},
                    {data: "style", name: "style", className: "auto", orderable: false},
                ],
                "dom": "tp",
                "pagingType": "full_numbers",
                "fnDrawCallback": function () {
                    
                    try {
                        document.getElementById("loadingReviews").style.display = "none";
                        document.getElementById("dataTables-example").style.display = "block";
                    }
                    catch(err) {
  
                    }
                    
                }
            });
            
            
            
      
      });';
      return response($jscript)->header('Content-Type', 'application/javascript');
    }

    

    public function review(Request $request)
    {
            $resources = Review::query();
            return Datatables::eloquent($resources)
                ->addColumn('style', function ($resource) {
                    $rating = $resource->rating;
                    switch($rating)
                    {
                        case '1':
                            $star ='<i class="fa fa-star"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i>';
                            $star_text = '1 out of 5';    
                        break;
                        case '2':
                            $star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i>';
                            $star_text = '2 out of 5';  
                        break;
                        case '3':
                            $star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star text-muted"></i><i class="fa fa-star text-muted"></i>';
                            $star_text = '3 out of 5';    
                        break;
                        case '4':
                            $star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star text-muted"></i>';
                            $star_text = '4 out of 5';  
                        break;
                        case '5':
                            $star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>';
                            $star_text = '5 out of 5';    
                        break;
                        default:
                            $star ='<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>';
                            $star_text = '5 out of 5';       
                    }
                    
                    if($resource->title!="")
                    {
                        $title = '<b>'.$resource->title.'</b><br />';
                    }
                    else
                    {
                        $title = '';
                    }
                    
                    $channel_name = Channel::find($resource->channel_id)->name;
                    if($resource->link!="")
                    {
                        $from = '<a href="'. $resource->link .'"  rel="noreferrer noopener" target="_blank" class="text-theme"><b>'.$channel_name.'</b></a><br>';
                        $from_text = '<br><small>Collected by <a href="'. $resource->link .'"  rel="noreferrer noopener" target="_blank" class="text-theme"><b>'.$channel_name.'</b></a></small>';
                    }
                    else
                    {
                        $from = '<b>'.$channel_name.'</b><br />';
                        $from_text = '<br /><small>Collected by <b>'.$channel_name.'</b></small>';
                    }

                    $date = Carbon::parse($resource->date)->format('M, Y');
                    $user = '<b>'. $resource->user .'</b> <small><span class="text-muted">Reviewed on '.$date.'</span></small><br />';
                    $rating = '<span class="text-warning">'. $star .'</span>';
                    $text =  nl2br($resource->text) .'<br />';
                    $product = Product::findOrFail($resource->product_id);
                    $post_title = 'Review of '. $product->name.'<br />';
                    

                    $output = $user.$rating.' '.$star_text.'<br /><small>'.$post_title.'</small><br />'.$title.$text.$from_text;
                    //$output = $user.$post_title.$rating.$title.$text;
                    //$output = $user.$post_title.$title.$text;
                    
                    return '<div class="bd-callout bd-callout-theme shadow-sm rounded" style="margin-top:5px;margin-bottom:5px;" >'. $output .'</div>';
                })
                ->only(['style'])
                ->rawColumns(['style'])
                ->toJson();
    }

    public function addshoppingcart($id,Request $request)
    {
            $contents = new \stdClass();
            $sessionId = $id;
        
            $value = BookingHelper::read_shoppingcart($sessionId);
            if($value==null)
            {
                $contents = BokunHelper::get_addshoppingcart($sessionId,json_decode($request->getContent(), true));
                BookingHelper::get_shoppingcart($sessionId,"insert",$contents);
            }
            else
            {
                if(BookingHelper::shoppingcart_checker(json_decode($request->getContent()),$sessionId))
                {
                    $contents = BokunHelper::get_addshoppingcart($sessionId,json_decode($request->getContent(), true));
                    BookingHelper::get_shoppingcart($sessionId,"update",$contents);
                }
                
            }
        
        

        FirebaseHelper::shoppingcart($sessionId);
        
        return response()->json($contents);
    }

    public function shoppingcart(Request $request)
    {

        $data = json_decode($request->getContent(), true);
        $sessionId = $data['sessionId'];

        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        
        FirebaseHelper::shoppingcart($sessionId);

        return response()->json([
            'message' => 'success',
            //'shoppingcarts' => $dataShoppingcart,
        ], 200);
    }
    
    

    public function removebookingid(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        $validator = Validator::make($data, [
            'bookingId' => ['required', 'integer'],
            'sessionId' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json($errors);
        }

        $sessionId = $data['sessionId'];
        $bookingId = $data['bookingId'];
         
        BookingHelper::remove_activity($sessionId,$bookingId);
        
        FirebaseHelper::shoppingcart($sessionId);

        return response()->json([
            "message" => "success"
        ]);
    }

    public function applypromocode(Request $request)
    {
        $validator = Validator::make(json_decode($request->getContent(), true), [
            'promocode' => ['required', 'string', 'max:255'],
            'sessionId' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json($errors);
        }

        $data = json_decode($request->getContent(), true);
        
        $promocode = $data['promocode'];
        $sessionId = $data['sessionId'];

        $status = BookingHelper::apply_promocode($sessionId,trim($promocode));

        FirebaseHelper::shoppingcart($sessionId);

        if($status)
        {
            return response()->json([
                'message' => 'success'
            ], 200);
        }
        else
        {
            return response()->json([
                'message' => 'failed'
            ], 200);
        }
    }

    public function removepromocode(Request $request)
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

        BookingHelper::remove_promocode($sessionId);
        
        FirebaseHelper::shoppingcart($sessionId);

        return response()->json([
                'message' => 'success'
            ], 200);
    }

    public function snippetsinvoice(Request $request)
    {
        $contents = BokunHelper::get_invoice(json_decode($request->getContent(), true));
        return response()->json($contents);
    }

    public function snippetscalendar($activityId,$year,$month)
    {
        $contents = BookingHelper::get_calendar($activityId,$year,$month);
        return response()->json($contents);
    }

    public function product_jscript($slug,$sessionId,Request $request)
    {
        $embedded = $request->input('embedded');
        $product = Product::where('slug',$slug)->first();
        if(!$product)
        {
            $slug = Slug::where('type','product')->where('slug',$slug)->latest('id')->firstOrFail();
            $product = Product::where('id',$slug->link_id)->first();
        }

        //bookingCard
        $content = BokunHelper::get_product($product->bokun_id);
        $calendar = BokunHelper::get_calendar_new($content->id);
        $availability = BookingHelper::get_firstAvailability($content->id,$calendar->year,$calendar->month);
           
        

        if($product)
        {
           
            $content = BokunHelper::get_product($product->bokun_id);
            $calendar = BokunHelper::get_calendar_new($content->id);

            $availability = BookingHelper::get_firstAvailability($content->id,$calendar->year,$calendar->month);
            
            $microtime = $availability[0]['date'];
            $month = date("n",$microtime/1000);
            $year = date("Y",$microtime/1000);

            if($embedded=="") $embedded = "true";

            $jscript = ' 
            
        var WidgetUtils = this.WidgetUtils = {};

        WidgetUtils.PriceFormatter = function(attributes) {
            $.extend(this, {
                currency: \''. config('site.currency') .'\',
                language: \''. env("BOKUN_LANG") .'\',
                decimalSeparator: \'.\',
                groupingSeparator: \',\',
                symbol: \''. config('site.currency') .' \'
            }, attributes);

            var instance = this;

            this.setCurrency = function(currency, symbol) {
                this.currency = currency;
                this.symbol = symbol;
            };

            this.format = function(amt) {
                if ( amt != null ) {
                    return amt.toString().replace(/\B(?=(\d{3})+(?!\d))/g, instance.groupingSeparator);
                } else {
                    return \'-\';
                }
            };

            this.symbolAndFormat = function(amt) {
                return (instance.symbol.length > 1 ? instance.symbol + " " : instance.symbol) + instance.format(amt);
            };

            this.formatHtml = function(amt) {
                return \'<span class="price"><span class="symbol">\' + (instance.symbol.length > 1 ? instance.symbol + " " : instance.symbol) + \'</span><span class="amount">\' + instance.format(amt) + \'</span></span>\';
            };

            this.formatHtmlSimple = function(amt) {
                return \'<span class="symbol">\' + (instance.symbol.length > 1 ? instance.symbol + " " : instance.symbol) + \'</span><span class="amount">\' + instance.format(amt) + \'</span>\';
            };

            this.formatHtmlStrikeThrough = function(amt) {
                return \'<span style="font-size: 14px">\' + (instance.symbol.length > 1 ? instance.symbol + " " : instance.symbol) + \'</span><span style="font-size: 14px">\' + instance.format(amt) + \'</span>\';
            }

        };

        window.priceFormatter = new WidgetUtils.PriceFormatter({
                currency: \''. config('site.currency') .'\',
                language: \''. env("BOKUN_LANG") .'\',
                decimalSeparator: \'.\',
                groupingSeparator: \',\',
                symbol: \''. config('site.currency') .' \'
        });

        window.i18nLang = \''. env("BOKUN_LANG") .'\';

        try { 
                $("#titleProduct").append(\''. $product->name .'\');
        } catch(err) {  
        }

        try { 
                $("#titleBooking").html(\'Booking '. $product->name .'\');
        } catch(err) {  
        }

        window.ActivityBookingWidgetConfig = {
                currency: \''. config('site.currency') .'\',
                language: \''. env("BOKUN_LANG") .'\',
                embedded: '.$embedded.',
                priceFormatter: window.priceFormatter,
                invoicePreviewUrl: \''.url('/api').'/activity/invoice-preview\',
                addToCartUrl: \''.url('/api').'/widget/cart/session/'.$sessionId.'/activity\',
                calendarUrl: \''.url('/api').'/activity/{id}/calendar/json/{year}/{month}\',
                activities: [],
                pickupPlaces: [],
                dropoffPlaces: [],
                showOnRequestMessage: false,
                showCalendar: true,
                showUpcoming: false,
                displayOrder: \'Calendar\',
                selectedTab: \'all\',
                hideExtras: false,
                showActivityList: false,
                showFewLeftWarning: false,
                warningThreshold: 10,
                displayStartTimeSelectBox: false,
                displayMessageAfterAddingToCart: false,
                defaultCategoryMandatory: true,
                defaultCategorySelected: true,
                affiliateCodeFromQueryString: true,
                affiliateParamName: \'trackingCode\',
                affiliateCode: \'\',
                onAfterRender: function(selectedDate) {
                    
                    $(".PICK_UP").hide();
                    $("#proses").remove();
                },
                onAvailabilitySelected: function(selectedRate, selectedDate, selectedAvailability) {
                },
                onAddedToCart: function(cart) {
                    
                    window.openAppRoute(\'/booking/checkout\');
                },
        
                calendarMonth: '.$month.',
                calendarYear: '.$year.',
                loadingCalendar: true,
        
                activity: '.json_encode($content).',
        
                upcomingAvailabilities: [],
        
                firstDayAvailabilities: '.json_encode($availability).'
        };
            
        if($("#ActivityBookingWidget").parent().length==0)
        {
            window.reloadJscript();
        }
            
            
            ';   
        }
        else
        {
            $jscript = 'window.openAppRoute(\'/page/not/found\')'; 
        }

        return response($jscript)->header('Content-Type', 'application/javascript');
    }

    public function last_order($sessionId)
    {
        $shoppingcarts = Shoppingcart::with('shoppingcart_products')->WhereHas('shoppingcart_products', function($query) {
                 // $query->where('date','>=',date('Y-m-d 00:00:00'));
            })->where('session_id', $sessionId)->orderBy('id','desc')->get();
        
        if($shoppingcarts->isEmpty())
        {
            return response()->json([
                'message' => 'success',
                //'info' => $sessionId,
                'booking' => array()
            ], 200);
        }
        
        $booking = ContentHelper::view_last_order($shoppingcarts);
        
        return response()->json([
                'message' => 'success',
                //'info' => $sessionId,
                'booking' => $booking
            ], 200);
        
    }

    public function receipt($sessionId,$confirmationCode)
    {
        $shoppingcart = Shoppingcart::where('confirmation_code',$confirmationCode)->where('session_id', $sessionId)->where(function($query){
            return $query->where('booking_status', 'CONFIRMED')
                         ->orWhere('booking_status', 'CANCELED')
                         ->orWhere('booking_status', 'PENDING');
        })->firstOrFail();

        if(!isset($shoppingcart->shoppingcart_payment))
        {
            abort(404);
        }
        
        BookingHelper::booking_expired($shoppingcart);

        $dataObj = ContentHelper::view_receipt($shoppingcart);

        FirebaseHelper::receipt($shoppingcart);
        
        return response()->json([
                'message' => "success"
            ], 200);
    }
    
    public function checkout_jscript()
    {
        $payment_array = explode(",",config('site.payment_enable'));
        $payment_enable = '';
        foreach($payment_array as $x)
        {
            $payment_enable .= '$("#payment_'.$x.'").attr("disabled",false);';
        }

        $jscript = '
        
        
   

        var submit_text;

        function loadInputFormat()
        {
            
            try
            {
                
                const inputPhoneNumber = document.querySelector("#phoneNumber");
                const iti = window.intlTelInput(inputPhoneNumber, {
                    utilsScript: "'. config('site.assets') .'/js/utils.js",
                    separateDialCode: true,
                    initialCountry: "id",
                    hiddenInput: function(telInputName) {
                        return {
                            phone: "phone_full",
                            country: "country_code"
                        };
                    }
                });
            }
            catch
            {

            }
            
        }


        function submitDisabled()
        {
            submit_text = $("#submitCheckout").text();
            $("#submitCheckout").attr("disabled", true);
            $(\'#submitCheckout\').html(\'<i class="fa fa-spinner fa-spin"></i>&nbsp;&nbsp;processing...\');
        }

        function submitEnabled()
        {
            $("#submitCheckout").attr("disabled", false);
            $(\'#submitCheckout\').html(\'<i class="fas fa-lock"></i> <strong id="submitText">\'+ submit_text +\'</strong>\');
        }

        function changePaymentMethod()
        {
            $("#alert-payment").slideUp("slow");
            $("#submitCheckout").slideDown("slow");
            $("#paymentContainer").html(\'\');
            '.$payment_enable.'
            submitEnabled();
        }

        function afterCheckout(url)
        {
            //window.clearTrackingCode();
            window.openAppRoute(url); 
        }

        function clearFormAlert(data)
        {
            $.each(data, function( index, value ) {
                $(\'#\'+ value).removeClass(\'is-invalid\');
                $(\'#span-\'+ value).remove();
            });
        }

        function formAlert(data)
        {
            $.each( data, function( index, value ) {
            $(\'#\'+ index).addClass(\'is-invalid\');
                if(value!="")
                {
                    $(\'#\'+ index).after(\'<span id="span-\'+ index  +\'" class="invalid-feedback" role="alert"><strong>\'+ value +\'</strong></span>\');
                }
            });
            
        }

        function showAlert(string,status)
        {
            if(status=="show")
            {
                $(\'#info-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> \'+ string +\'</h2></div>\');
                $(\'#info-payment\').fadeIn("slow");
            }
            else
            {
                $("#info-payment").slideUp("slow");
            }
        }

        function failedpaymentEwallet(ewallet)
            {
                if(ewallet=="ovo")
                {
                    $("#text-alert").hide();
                    $("#text-alert").html( "" );

                    $(\'#alert-payment\').html(\'<div id="alert-failed" class="alert alert-danger text-center mt-2" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Transaction failed</h2></div>\');
                    $(\'#alert-payment\').fadeIn("slow");
                    $("#ovoPhoneNumber").attr("disabled", false);
                    $("#submit").attr("disabled", false);
                    $("#submit").html(\' <strong>Click to pay with <img class="ml-2 mr-2" src="'.config('site.assets').'/img/payment/ovo-light.png" height="30" /></strong> \');
                }
            }

        

        function redirect(url)
        {
            $(\'#submitCheckout\').html(\'<i class="fa fa-spinner fa-spin"></i>&nbsp;&nbsp;redirecting...\');
            setTimeout(function (){
                window.location.href = url;
            }, 1000);
        }

        function showButton(deeplink,name)
        {
            $("#submitCheckout").slideUp("slow");
            $("#paymentContainer").html(\'<a class="btn btn-lg btn-block btn-theme" href="\'+ deeplink +\'"><strong>Click to pay with \'+ name +\'</strong></a>\');
        }

        
        ';
        
        return response($jscript)->header('Content-Type', 'application/javascript');
    }

    public function receipt_jscript()
    {
        $jscript = '
            
            function copyToClipboard(element) {
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val($(element).val()).select();
                document.execCommand("copy");
                $temp.remove();
  
                $(element +"_button").tooltip("hide");
                $(element +"_button").tooltip("show");
                hideTooltip(element +"_button");
            }

            function hideTooltip(element) {
                setTimeout(function() {
                    $(element).tooltip(\'dispose\');
                }, 1000);
            }

            function clear_timer()
            {
                clearInterval(document.getElementById("timer_id").value);
            }

            function payment_timer(due_date,session_id,confirmation_code)
            {
                 clearInterval(document.getElementById("timer_id").value);

                 var x = {};
                 var countDownDate = new Date(due_date).getTime();
                 x[due_date] = setInterval(function() {

                    
                    var now = new Date().getTime();
                    var distance = countDownDate - now;

                    var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    if(days>0)
                    {
                        try
                        {
                            document.getElementById("payment_timer").innerHTML = days + " day " + hours + " hrs "
      + minutes + " min " + seconds + " sec ";
                        }
                        catch(e)
                        {

                        }
                        
                    }
                    else if(hours>0)
                    {
                        try
                        {
                            document.getElementById("payment_timer").innerHTML = hours + " hrs "
      + minutes + " min " + seconds + " sec ";
                        }
                        catch(e)
                        {

                        }
                        
                    }
                    else
                    {
                        try
                        {
                            document.getElementById("payment_timer").innerHTML = minutes + " min " + seconds + " sec ";
                        }
                        catch(e)
                        {

                        }
                        
                    }
                    

                    document.getElementById("timer_id").value = x[due_date];
                    if (distance < 0) {
                        clearInterval(x[due_date]);
                        document.getElementById("payment_timer").innerHTML = "Payment expired";
                        $.get("'.url('/api').'/receipt/"+session_id+"/"+confirmation_code);
                    }

                }, 1000);

                
            }';

        return response($jscript)->header('Content-Type', 'application/javascript');
    }

}
