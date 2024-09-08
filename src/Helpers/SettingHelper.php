<?php
namespace budisteikul\vertikaltrip\Helpers;
use budisteikul\vertikaltrip\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingHelper {

    public static function getSetting($name)
    {
        $value = '';
        $setting = Setting::where('name',$name)->first();
        if($setting)
        {
            return $setting->value;
        }
        return $value;
    }

    

}
?>