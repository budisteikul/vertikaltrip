<?php
namespace budisteikul\vertikaltrip\Helpers;
use Stripe;

class StripeHelper {

	public static function env_stripePublishableKey()
  	{
        return env("STRIPE_PUBLISHABLE_KEY");
  	}

  	public static function env_stripeSecretKey()
  	{
        return env("STRIPE_SECRET_KEY");
  	}

  	public static function createPayment($data)
  	{
  		$amount = number_format((float)$data->transaction->amount, 2, '.', '');
  		//$amount = bcmul($amount, 100);
  		$amount = $amount * 100;

  		Stripe\Stripe::setApiKey(self::env_stripeSecretKey());
  		$intent = Stripe\PaymentIntent::create([
  			'amount' => $amount,
  			'currency' => 'usd',
  			'metadata' => ['integration_check' => 'accept_a_payment'],
  			//'capture_method' => 'manual',
		]);

        $data_json = new \stdClass();
        $status_json = new \stdClass();
        $response_json = new \stdClass();
      
  		$data_json->intent = $intent;
  		$data_json->authorization_id = $intent->id;

        $status_json->id = '1';
        $status_json->message = 'success';
        
        $response_json->status = $status_json;
        $response_json->data = $data_json;

		return $response_json;
  	}

    public function createRefund($shoppingcart)
    {
        $amount = $shoppingcart->shoppingcart_payment->amount * 100;
        $id = $shoppingcart->shoppingcart_payment->authorization_id;

        Stripe\Stripe::setApiKey(self::env_stripeSecretKey());
        $refund = Stripe\Refund::create([
            'amount' => $amount,
            'payment_intent' => $id,
            'reason' => 'requested_by_customer'
        ]);
        return $refund;
    }

    public function getNet($shoppingcart)
    {
        $id = $shoppingcart->shoppingcart_payment->authorization_id;
        Stripe\Stripe::setApiKey(self::env_stripeSecretKey());
        
        $result = Stripe\PaymentIntent::retrieve([
            'id' => $id,
            'expand' => ['latest_charge.balance_transaction'],
        ]);

        $amount = $shoppingcart->shoppingcart_payment->amount;
        $contribution = $amount * 1 / 100;

        $net = $result->latest_charge->balance_transaction->net / 100;
        $net = $net - $contribution;
        return $net;
    }
}