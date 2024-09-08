<?php
namespace budisteikul\vertikaltrip\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use budisteikul\vertikaltrip\Helpers\LogHelper;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;
use Illuminate\Support\Facades\Cache;

class ToolController extends Controller
{
    
	
    public function __construct()
    {
        
    }

    public function bin(Request $request)
    {
        $bin = $request->input("bin");
        if(!is_numeric($bin))
        {
            return "";
        }
        if(strlen($bin)!=8)
        {
            return "";
        }

        $country_code = "";

        $bin_tool = config('site.bin');
        if($bin_tool=="xendit")
        {
            $response = Cache::rememberForever('_bin_xendit_'. $bin, function ()  use ($bin){
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
                curl_setopt($ch, CURLOPT_URL, env("XENDIT_URL")."?business_id=".env("XENDIT_BUSSINES_ID")."&amount=50000&currency=IDR&bin=".$bin);

                $headerArray[] = "Invoice-id: ". env("XENDIT_INVOICE_ID");
                $headerArray[] = "Origin: https://checkout.xendit.co";

                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

                $response = curl_exec($ch);
            
                curl_close ($ch);
                return $response;
            });

            $response = json_decode($response);
            if(isset($response->bin_data->country_code)) $country_code = strtoupper($response->bin_data->country_code);
        }
        else if($bin_tool=="midtrans")
        {
            $response = Cache::rememberForever('_bin_midtrans_'. $bin, function ()  use ($bin){

                $headerArray[] = "Accept: application/json";
                $headerArray[] = "Authorization: Basic ". base64_encode(env("MIDTRANS_SERVER_KEY")."");

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
                curl_setopt($ch, CURLOPT_URL, env("MIDTRANS_URL")."/v1/bins/".$bin);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
                $response = curl_exec($ch);
                curl_close ($ch);

                return $response;
            });
        
            $response = json_decode($response);
            if(isset($response->data->country_code)) $country_code = strtoupper($response->data->country_code);
        }
        else
        {
            
            $response = Cache::rememberForever('_bin_apilayer_'. $bin, function ()  use ($bin){
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
                curl_setopt($ch, CURLOPT_URL, "https://api.apilayer.com/bincheck/".$bin);

                $headerArray[] = "Content-Type: text/plain";
                $headerArray[] = "apikey: ". env('APILAYER_KEY');

                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

                $response = curl_exec($ch);
            
                curl_close ($ch);
                return $response;
            });

            $response = json_decode($response);
        
            $value='';
            if(isset($response->country)) $value = $response->country;

            switch($value)
            {
                case "United States Of America":
                    $country_code = "US";  
                break;
                case "United Kingdom":
                    $country_code = "GB";  
                break;
                case "Great Britain":
                    $country_code = "GB";  
                break;
                case "United States":
                    $country_code = "US";
                break;
                case "Canada":
                    $country_code = "CA";  
                break;
                default:
                    $country_code = "";  
            }
        }
        return response()->json(['country_code'=>$country_code], 200);
    }

    

    public function billing($sessionId,Request $request)
    {
        $given_name = $request->input('givenName');
        $surname = $request->input('surname');
        $street_line1 = $request->input('streetLine1');
        $postal_code = $request->input('postalCode');
        $token_id = $request->input('tokenId');
        $country = $request->input('country');
        
        $data = [
            'given_name' => $given_name,
            'surname' => $surname,
            'street_line1' => $street_line1,
            'postal_code' => $postal_code,
            'country' => $country,
            'token_id' => $token_id,
        ];
        
        FirebaseHelper::write('billing/'.$sessionId,$data);
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

}
