<?php

namespace budisteikul\vertikaltrip\Mail;

use budisteikul\vertikaltrip\Helpers\BookingHelper;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;

use budisteikul\vertikaltrip\Models\ShoppingcartProduct;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JogjaFoodTourQuestionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data = $this->data;
        $mail = $this->text('vertikaltrip::layouts.mail.jogja-food-tour-question_plain')
                    ->subject('Jogja Food Tour')
                    ->with('data',$data);
        

    }
}
