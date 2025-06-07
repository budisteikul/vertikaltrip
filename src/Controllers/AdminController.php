<?php
namespace budisteikul\vertikaltrip\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use budisteikul\vertikaltrip\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;
use Illuminate\Support\Facades\Cache;
use budisteikul\vertikaltrip\Helpers\BokunHelper;
use budisteikul\vertikaltrip\Helpers\OpenAIHelper;

class AdminController extends Controller
{
    
	
    public function __construct()
    {
        
    }
    
    public function createToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        $user = User::where('email', $request->email)->first();
        
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response([
                    'success'   => false,
                    'message' => ['These credentials do not match our records.']
                ], 404);
            }
            
            $token = $user->createToken('VertikalTripToken')->plainTextToken;
        
            $response = [
                'success'   => true,
                'token'     => $token
            ];
        
        return response($response, 201);
    }

    public function openai(Request $request)
    {
        $json = json_decode($request->getContent());
        print_r($json->text);
        exit;

        $request->validate([
            'text' => 'required'
        ]);
        $text = $request->text;

        $openai = New OpenAIHelper;
        $data = $openai->openai($text);
        return response()->json([
                'text' => $data
            ], 200);
    }

    public function product_sync(Request $request)
    {
        $request->validate([
            'activityId' => 'required'
        ]);
        $activityId = $request->activityId;
        Cache::forget('_bokunProductById_'. config('site.currency') .'_'. env("BOKUN_LANG") .'_'.$activityId);
        BokunHelper::get_product($activityId);
        return response()->json([
                'message' => 'success'
            ], 200);
    }

    

}
