<?php
namespace budisteikul\vertikaltrip\Helpers;


class GeneralHelper {

    public static function sanitize_output($buffer) {

    $search = array(
        '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
        '/[^\S ]+\</s',     // strip whitespaces before tags, except space
        '/(\s)+/s',         // shorten multiple whitespace sequences
        '/<!--(.|\s)*?-->/' // Remove HTML comments
    );

    $replace = array(
        '>',
        '<',
        '\\1',
        ''
    );

    $buffer = preg_replace($search, $replace, $buffer);

    return $buffer;
    
    }

    public static function get_string_between($string, $start, $end){
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public static function digitFormat($number,$digit)
    {
        $number = str_pad($number, $digit, '0', STR_PAD_LEFT);
        return $number;
    }

    public static function dateFormat($date="",$type="")
    {
        if($date=="") $date = \Carbon\Carbon::now()->toDateTimeString();
        
        
        switch($type)
        {
            case 1:
                return \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
            break;
            case 2:
                return \Carbon\Carbon::parse($date)->format('d-m-Y H:i');
            break;
            case 3:
                return \Carbon\Carbon::parse($date)->format('l, d F Y, H:i');
            break;
            case 4:
                return \Carbon\Carbon::parse($date)->format('d F Y');
            break;
            case 5:
                return \Carbon\Carbon::parse($date)->format('d/m/Y');
            break;
            case 6:
                return \Carbon\Carbon::parse($date)->format('l, d F Y');
            break;
            case 7:
                return \Carbon\Carbon::parse($date)->format('Y-m-d 00:00:00');
            break;
            case 8:
                return \Carbon\Carbon::parse($date)->format('Y-m-d 23:59:59');
            break;
            case 9:
                return \Carbon\Carbon::parse($date)->format('d F Y, H:i');
            break;
            case 10:
                return \Carbon\Carbon::parse($date)->format('d F Y, H:i:s');
            break;
            case 11:
                return \Carbon\Carbon::parse($date)->format("D d.M'y");
            break;
            case 12:
                $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date, 'Asia/Jakarta');
                $new_date = $date->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z');
                return $new_date;
            break;
            default:
                return \Carbon\Carbon::now()->toDateTimeString();
        }
    }

    public static function numberFormat($exp,$currency="")
    {
        if($currency=="") $currency = config('site.currency');

        if($currency=="IDR")
        {
            return number_format($exp, 0, ',',',');
        }
        else
        {
            $exp = number_format($exp, 2, '.','');
            $arr_val = explode('.',$exp);
            if(strlen((string)$arr_val[0])>=4)
            {
                $exp = round($exp);
                $exp = number_format((float)$exp, 2, '.', '');
                $exp = number_format($exp, 0, ',',',');
            }
            
            return $exp;
        }
        
    }

    public static function splitSpace($string,$number=4,$first=0)
    {
        $front = substr($string,0,$first);
        $string = substr($string,$first);
        $value = "";
        $max_string = strlen($string);
        $mod = $max_string % $number;
        $j = 0;
        for($i=0;$i<$max_string;$i++)
        {
            $value .= substr($string, $j, $number) .' ';
            $j += $number;
        }
        return trim($front .' '. $value);
    }

    public static function formatRupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka,0,',','.');
        return $hasil_rupiah;
    }

    public static function hourToDay($hour)
    {
        $value = "";
        $hid = 24;
        $day = round($hour/$hid);

        if( $day < 0 )
        {
            if($hour==1)
            {
                $value = $hour ." hour";
            }
            else
            {
                $value = $hour ." hours";
            }
        }
        else
        {
            if($day==1)
            {
                $value = $day ." day";
            }
            else
            {
                $value = $day ." days";
            }
        }
        return $value;   
    }

    public static function roundCurrency($value,$currency="IDR")
    {
        if($currency=="IDR")
        {
            $hundred = substr($value, -3);
            if($hundred<500)
            {
                $value = $value - $hundred;
            }
            else
            {
                $value = $value + (1000-$hundred);
            }
        }
        return $value;
    }

    public static function url()
    {
        return rtrim(request()->headers->get('referer'), "/");
    }

    
    public static function mask($str, $first, $last) {
        $len = strlen($str);
        $toShow = $first + $last;
        return substr($str, 0, $len <= $toShow ? 0 : $first).str_repeat("*", $len - ($len <= $toShow ? 0 : $toShow)).substr($str, $len - $last, $len <= $toShow ? 0 : $last);
    }

    public static function mask_email($email) {
        $mail_parts = explode("@", $email);
        $domain_parts = explode('.', $mail_parts[1]);

        $mail_parts[0] = self::mask($mail_parts[0], 2, 1); // show first 2 letters and last 1 letter
        $domain_parts[0] = self::mask($domain_parts[0], 2, 1); // same here
        $mail_parts[1] = implode('.', $domain_parts);

        return implode("@", $mail_parts);
    }
    
    public static function mask_phoneNumber($phone)
    {
        $phone = self::mask($phone, 3, 1);
        return $phone;
    }

    public static function mask_name($name)
    {
        $name = self::mask($name, 3, 0);
        return $name;
    }

    public static function phoneNumber($phoneNumber,$pre="")
    {
        
        $number = $phoneNumber;
        $number_array = explode(" ",$number);
        if(isset($number_array[1]))
        {
            $number_array[1] = ltrim($number_array[1], '0');
        }
                        
        $nomor = '';
        foreach($number_array as $no)
        {
            $nomor .= preg_replace("/[^0-9]/","",$no);
        }
        return $pre.$nomor;
    }
}
?>