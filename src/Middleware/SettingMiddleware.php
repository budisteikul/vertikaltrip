<?php

namespace budisteikul\vertikaltrip\Middleware;

use Closure;
use Illuminate\Http\Request;
use budisteikul\vertikaltrip\Models\Setting;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;

class SettingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $settings = Setting::get();
        foreach($settings as $setting)
        {
            config(['site.'.$setting->name => $setting->value]);
        }
        
        
        if(str_contains(GeneralHelper::url(), 'jogjafoodtour'))
        {
            config(['site.currency'=>'IDR']);
            config(['site.payment_enable'=>'xendit,stripe,paypal']);
            config(['site.payment_default'=>'xendit']);
        }
        else if(str_contains(GeneralHelper::url(), 'localhost'))
        {
            //config(['site.currency'=>'IDR']);
            //config(['site.payment_enable'=>'xendit,stripe,paypal']);
            //config(['site.payment_default'=>'xendit']);
        }
        else
        {
            config(['site.currency'=>'USD']);
            config(['site.payment_enable'=>'stripe,paypal']);
            config(['site.payment_default'=>'stripe']);
        }
        
        return $next($request);
    }
}
