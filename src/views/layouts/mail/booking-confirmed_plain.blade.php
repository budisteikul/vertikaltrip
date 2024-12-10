@inject('BookingHelper', 'budisteikul\vertikaltrip\Helpers\BookingHelper')
@inject('Content', 'budisteikul\vertikaltrip\Helpers\ContentHelper')
@php
  $main_contact = $BookingHelper->get_answer_contact($shoppingcart);
@endphp
Hi {{$main_contact->firstName}},

Have a good day,
Thank you for your booking with {{env('APP_NAME')}}.

Your booking number is : {{$shoppingcart->confirmation_code}}

{!! $Content->view_product_detail($shoppingcart,true) !!}

Follow link below to to download your invoice.
{!!env("APP_URL")!!}/booking/receipt/{{$shoppingcart->session_id}}/{{$shoppingcart->confirmation_code}}
Follow link below to know way to the meeting point.
https://map.vertikaltrip.com

Our guide will contact you in the day of the tour via Whatsapp or Email. If you have any question, feel free to contact us.
See you there :) 

Regards,
The {{env('APP_NAME')}} team


{{env('APP_NAME')}}
Whatsapp : +62 895 3000 0030
Email : guide@vertikaltrip.com

VERTIKAL TRIP INDONESIA
www.jogjafoodtour.com