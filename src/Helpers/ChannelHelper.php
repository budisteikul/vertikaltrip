<?php
namespace budisteikul\vertikaltrip\Helpers;
use budisteikul\vertikaltrip\Models\Channel;

class ChannelHelper {

    public static function getDescription($shoppingcart)
    {
        $description = $shoppingcart->booking_channel;
        $channel = Channel::where('name',$shoppingcart->booking_channel)->first();
        if($channel) $description = $channel->description;
        if($description=="") $description = $channel->name;
        return $description;
    }

    public static function getTypeOfInvoice($shoppingcart)
    {
        $val = 1;
        $channel = Channel::where('name',$shoppingcart->booking_channel)->first();
        if($channel) $val = $channel->invoice;
        return $val;
    }
}
?>