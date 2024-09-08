<?php

namespace budisteikul\vertikaltrip\Mail;

use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($shoppingcart)
    {
        $this->shoppingcart = $shoppingcart;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $shoppingcart = $this->shoppingcart;
        
        $mail = $this->view('vertikaltrip::layouts.mail.booking-confirmed')
                    ->text('vertikaltrip::layouts.mail.booking-confirmed_plain')
                    ->subject('Booking Confirmed')
                    ->with('shoppingcart',$shoppingcart);
        

    }
}
