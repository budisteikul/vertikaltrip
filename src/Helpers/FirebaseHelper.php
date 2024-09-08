<?php
namespace budisteikul\vertikaltrip\Helpers;
use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\PaymentHelper;
use budisteikul\vertikaltrip\Helpers\ContentHelper;
use budisteikul\vertikaltrip\Models\Shoppingcart;
use Illuminate\Support\Facades\Cache;

class FirebaseHelper {

    public static function env_firebaseDatabaseUrl()
    {
        return env("FIREBASE_DATABASE_URL");
    }

    public static function env_firebaseDatabaseSecret()
    {
        return env("FIREBASE_DATABASE_SECRET");
    }

    public static function connect($path,$data="",$method="PUT")
    {
        $response = null;

        if($method=="PUT")
        {
            $endpoint = "https://". self::env_firebaseDatabaseUrl() ."/". $path .".json?auth=". self::env_firebaseDatabaseSecret();
            $client = new \GuzzleHttp\Client(['http_errors' => false]);
            $response = $client->request('PUT',$endpoint,
                ['body' => json_encode($data)]
            );
            $data = $response->getBody()->getContents();
            $response = json_decode($data);
        }

        if($method=="DELETE")
        {
            $endpoint = "https://". self::env_firebaseDatabaseUrl() ."/".$path .".json?auth=". self::env_firebaseDatabaseSecret();
            $client = new \GuzzleHttp\Client(['http_errors' => false]);
            $response = $client->request('DELETE',$endpoint);

            $data = $response->getBody()->getContents();
            $response = json_decode($data);
        }

        if($method=="GET")
        {
            $endpoint = "https://". self::env_firebaseDatabaseUrl() ."/".$path .".json?auth=". self::env_firebaseDatabaseSecret();
            $client = new \GuzzleHttp\Client(['http_errors' => false]);
            $response = $client->request('GET',$endpoint);

            $data = $response->getBody()->getContents();
            $response = json_decode($data);

        }
            
        return $response;
    }
    
    public static function shoppingcart($sessionId)
    {
            $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
            $dataShoppingcart = ContentHelper::view_shoppingcart($shoppingcart);

            $dataFirebase = array(
                'shoppingcarts' => $dataShoppingcart,
                'api_url' => env('APP_API_URL'),
                'payment_enable' => config('site.payment_enable'),
                'payment_default' => config('site.payment_default'),
                'agreement' => 'I agree with the <a class="text-theme" href="/page/terms-and-conditions" target="_blank">terms and conditions</a> and the cancellation policy for each product.',
                'cancellationPolicy' => 'I agree with the <a class="text-theme" href="/page/terms-and-conditions" target="_blank">terms and conditions</a>.',
                'payment_information' => '',
                'message' => 'success'
            );
            self::connect('shoppingcart/'.$sessionId,$dataFirebase,"PUT");
    }

    public static function receipt($shoppingcart)
    {
        if(!PaymentHelper::have_payment($shoppingcart))
        {
            return "";
        }
        $dataObj = ContentHelper::view_receipt($shoppingcart); 
        $data = array(
                    'receipt' => $dataObj,
                    'api_url' => env('APP_API_URL'),
                    'message' => 'success'
                );
        self::connect('receipt/'.$shoppingcart->session_id ."/". $shoppingcart->confirmation_code,$data,"PUT");
    }

    public static function write($identifier,$data=null)
    {
            self::connect($identifier,$data,"PUT");
    }

    public static function read($identifier)
    {
            return self::connect($identifier,"","GET");
    }

    public static function delete($identifier)
    {
            return self::connect($identifier,"","DELETE");
    }
}
?>