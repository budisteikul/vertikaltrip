<?php
namespace budisteikul\vertikaltrip\Helpers;
use Illuminate\Http\Request;

use budisteikul\vertikaltrip\Helpers\BokunHelper;
use budisteikul\vertikaltrip\Helpers\ImageHelper;
use budisteikul\vertikaltrip\Helpers\ProductHelper;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;
use budisteikul\vertikaltrip\Helpers\VoucherHelper;
use budisteikul\vertikaltrip\Helpers\TaskHelper;
use budisteikul\vertikaltrip\Helpers\PaymentHelper;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;


use budisteikul\vertikaltrip\Models\Product;
use budisteikul\vertikaltrip\Models\Shoppingcart;
use budisteikul\vertikaltrip\Models\ShoppingcartProduct;
use budisteikul\vertikaltrip\Models\ShoppingcartProductDetail;
use budisteikul\vertikaltrip\Models\ShoppingcartQuestion;
use budisteikul\vertikaltrip\Models\ShoppingcartQuestionOption;
use budisteikul\vertikaltrip\Models\ShoppingcartPayment;
use budisteikul\vertikaltrip\Models\CloseOut;

use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use \PDF;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use View;
use Illuminate\Support\Facades\Storage;

class BookingHelper {

	public static function webhook_bokun($data)
	{
			$shoppingcart = new Shoppingcart();
			$shoppingcart->booking_status = 'CONFIRMED';
			
			if(isset($data['promoCode'])) $shoppingcart->promo_code = $data['promoCode']['code'];
			$bookingChannel = '';
			if(isset($data['affiliate']['title']))
			{
				$bookingChannel = $data['affiliate']['title'];
			}
			else
			{
				$bookingChannel = $data['seller']['title'];
			}
			
			$bookingChannel = str_replace(".com", "", $bookingChannel);
			$shoppingcart->booking_channel = $bookingChannel;

			$confirmation_code = '';
            if(isset($data['externalBookingReference']))
            {
                $confirmation_code = $data['externalBookingReference'];
            }
            else
            {
                $confirmation_code = $data['confirmationCode'];
            }
			if($bookingChannel=="Viator") $confirmation_code = 'BR-'. $data['externalBookingReference'];
			if($confirmation_code=="")
			{
				$confirmation_code = self::get_ticket();
			}

			$shoppingcart->confirmation_code = $confirmation_code;
			$shoppingcart->session_id = Uuid::uuid4()->toString();
			$shoppingcart->save();
			
			// main contact questions
			if(isset($data['customer']['firstName']))
			{
				$shoppingcart_question = new ShoppingcartQuestion();
				$shoppingcart_question->shoppingcart_id = $shoppingcart->id;
				$shoppingcart_question->type = 'mainContactDetails';
				$shoppingcart_question->question_id = 'firstName';
				$shoppingcart_question->label = 'First name';
				$shoppingcart_question->order = 1;
				$shoppingcart_question->answer = $data['customer']['firstName'];
				$shoppingcart_question->save();
			}
			
			
			if(isset($data['customer']['lastName']))
			{
				$shoppingcart_question = new ShoppingcartQuestion();
				$shoppingcart_question->shoppingcart_id = $shoppingcart->id;
				$shoppingcart_question->type = 'mainContactDetails';
				$shoppingcart_question->question_id = 'lastName';
				$shoppingcart_question->label = 'Last name';
				$shoppingcart_question->order = 2;
				$shoppingcart_question->answer = $data['customer']['lastName'];
				$shoppingcart_question->save();
			}
			
			
			if(isset($data['customer']['email']))
			{
				$shoppingcart_question = new ShoppingcartQuestion();
				$shoppingcart_question->shoppingcart_id = $shoppingcart->id;
				$shoppingcart_question->type = 'mainContactDetails';
				$shoppingcart_question->question_id = 'email';
				$shoppingcart_question->label = 'Your email address';
				$shoppingcart_question->order = 3;
				$shoppingcart_question->answer = $data['customer']['email'];
				$shoppingcart_question->save();
			}

			if(isset($data['customer']['phoneNumber']))
			{
				$shoppingcart_question = new ShoppingcartQuestion();
				$shoppingcart_question->shoppingcart_id = $shoppingcart->id;
				$shoppingcart_question->type = 'mainContactDetails';
				$shoppingcart_question->question_id = 'phoneNumber';
				$shoppingcart_question->label = 'Phone number';
				$shoppingcart_question->order = 4;
				$shoppingcart_question->answer = GeneralHelper::phoneNumber($data['customer']['phoneNumber'],"+");
				$shoppingcart_question->save();
			}
			
			
			// product
			$grand_total = 0;
			$grand_subtotal = 0;
			$grand_discount = 0;

			$currency = config('site.currency');

			for($i=0;$i<count($data['activityBookings']);$i++)
			{
				$shoppingcart_product = new ShoppingcartProduct();
				$shoppingcart_product->shoppingcart_id = $shoppingcart->id;
				$shoppingcart_product->booking_id = $data['activityBookings'][$i]['bookingId'];
				$shoppingcart_product->product_confirmation_code = $data['activityBookings'][$i]['productConfirmationCode'];

				//$shoppingcart_product->product_id = $data['activityBookings'][$i]['productId'];
				$shoppingcart_product->product_id = $data['activityBookings'][$i]['product']['externalId'];
				
				if(isset($data['activityBookings'][$i]['activity']['photos'][0]['derived'][0]['url']))
				{
					$shoppingcart_product->image = $data['activityBookings'][$i]['activity']['photos'][0]['derived'][0]['url'];
				}
				

				$shoppingcart_product->title = $data['activityBookings'][$i]['product']['title'];
				$shoppingcart_product->rate = $data['activityBookings'][$i]['rateTitle'];
				$shoppingcart_product->date = ProductHelper::texttodate($data['activityBookings'][$i]['invoice']['dates']);
				$shoppingcart_product->cancellation = 'Referring to '.$bookingChannel.' policy';
				$shoppingcart_product->save();
				
				$subtotal_product = 0;
				$total_discount = 0;
				$total_product = 0;

				//==============================================================================
				if($bookingChannel=="Viator")
				{
					$lineitems = $data['activityBookings'][$i]['sellerInvoice']['customLineItems'];
					$currency = $data['activityBookings'][$i]['sellerInvoice']['currency'];
				}
				else
				{
					$lineitems = $data['activityBookings'][$i]['invoice']['lineItems'];
					$currency = $data['activityBookings'][$i]['invoice']['currency'];
					
				}
				
				

				for($j=0;$j<count($lineitems);$j++)
				{

						$s_quantity = $lineitems[$j]['quantity'];
						$s_price = $lineitems[$j]['unitPrice'];
						$s_discount = $lineitems[$j]['discount'];


						$shoppingcart_product_detail = new ShoppingcartProductDetail();
						$shoppingcart_product_detail->shoppingcart_product_id = $shoppingcart_product->id;
						$shoppingcart_product_detail->type = 'product';
						$shoppingcart_product_detail->title = $lineitems[$j]['title'];
						$shoppingcart_product_detail->people = $data['activityBookings'][$i]['totalParticipants'];
						$shoppingcart_product_detail->qty = $s_quantity;
						$shoppingcart_product_detail->price = $s_price;

						if($bookingChannel=="Viator")
						{
							$shoppingcart_product_detail->unit_price = 'Price per booking';
						}
						else
						{
							$shoppingcart_product_detail->unit_price = $lineitems[$j]['title'];
						}
						

						$subtotal = $s_price * $s_quantity;
						$discount = $s_discount * $s_quantity;
						$total = $subtotal - $discount;

						$shoppingcart_product_detail->currency = $currency;
						$shoppingcart_product_detail->discount = $discount;
						$shoppingcart_product_detail->subtotal = $subtotal;
						$shoppingcart_product_detail->total = $total;
						$shoppingcart_product_detail->save();
						
						$subtotal_product += $subtotal;
						$total_discount += $discount;
						$total_product += $total;
				}
				//==============================================================================


				//print_r($data['activityBookings'][$i]['notes'][0]['body']);
				//exit();
				ShoppingcartProduct::where('id',$shoppingcart_product->id)->update([
					'currency'=>$currency,
					'subtotal'=>$subtotal_product,
					'discount'=>$total_discount,
					'total'=>$total_product,
					'due_now'=>$total_product
				]);
				

				// activity question
				if(isset($data['activityBookings'][$i]['answers']))
				{
					$order = 1;
					for($k=0;$k<count($data['activityBookings'][$i]['answers']);$k++)
					{
						$shoppingcart_question = new ShoppingcartQuestion();
						$shoppingcart_question->shoppingcart_id = $shoppingcart->id;
						$shoppingcart_question->type = 'activityBookings';
						$shoppingcart_question->booking_id = $data['activityBookings'][$i]['bookingId'];
						$shoppingcart_question->question_id = $data['activityBookings'][$i]['answers'][$k]['id'];
						$shoppingcart_question->label = $data['activityBookings'][$i]['answers'][$k]['question'];
						$shoppingcart_question->order = $order;
						$shoppingcart_question->answer = $data['activityBookings'][$i]['answers'][$k]['answer'];
						$shoppingcart_question->save();
						$order++;
					}
				}

				if(isset($data['activityBookings'][$i]['notes']))
				{
					$order = 1;
					for($k=0;$k<count($data['activityBookings'][$i]['notes']);$k++)
					{
						if($data['activityBookings'][$i]['notes'][$k]['type']=="GENERAL")
						{
							$shoppingcart_question = new ShoppingcartQuestion();
							$shoppingcart_question->shoppingcart_id = $shoppingcart->id;
							$shoppingcart_question->type = 'activityBookings';
							$shoppingcart_question->booking_id = $data['activityBookings'][$i]['bookingId'];
							$shoppingcart_question->question_id = $data['activityBookings'][$i]['notes'][$k]['type'];
							$shoppingcart_question->label = "Note from ". $bookingChannel;
							$shoppingcart_question->order = $order;
							$shoppingcart_question->answer = $data['activityBookings'][$i]['notes'][$k]['body'];
							$shoppingcart_question->save();
							$order++;
						}
						
					}
				}
			}
			
			$grand_discount += $total_discount;
			$grand_subtotal += $subtotal_product;
			$grand_total += $total_product;
			
			//$shoppingcart->currency = $data['currency'];
			$shoppingcart->currency = $currency;
			$shoppingcart->subtotal = $grand_subtotal;
			$shoppingcart->discount = $grand_discount;
			$shoppingcart->total = $grand_total;
			$shoppingcart->due_now = $grand_total;
			$shoppingcart->save();

			$new_currency = config('site.currency');
			$shoppingcart_payment = new ShoppingcartPayment();
			$shoppingcart_payment->payment_provider = 'none';
			$shoppingcart_payment->amount = $grand_total;
			$shoppingcart_payment->rate = self::convert_currency(1,$currency,$new_currency);
			$shoppingcart_payment->rate_from = $currency;
			$shoppingcart_payment->rate_to = $new_currency;
			$shoppingcart_payment->currency = $currency;
			$shoppingcart_payment->payment_status = 2;
			$shoppingcart_payment->shoppingcart_id = $shoppingcart->id;
			$shoppingcart_payment->save();
			
			return $shoppingcart;
	}

	
	
	public static function insert_shoppingcart($contents,$id)
	{
		
		$activity = $contents->activityBookings;

		$s_confirmation_code = self::get_ticket();
		$s_session_id = $id;
		$s_booking_status = 'PENDING';
		$s_booking_channel = 'WEBSITE';
		$s_currency = $contents->customerInvoice->currency;
		$s_promo_code = NULL;
		
		$grand_total = 0;
		$grand_subtotal = 0;
		$grand_discount = 0;
		$grand_due_now = 0;
		$grand_due_on_arrival = 0;

		$ShoppingcartProducts = array();
		for($i=0;$i<count($activity);$i++)
		{
			$product_invoice = $contents->customerInvoice->productInvoices;
			$lineitems = $product_invoice[$i]->lineItems;
			
			
			$sp_product_confirmation_code = $activity[$i]->productConfirmationCode;
			$sp_booking_id = $activity[$i]->id;
			$sp_product_id = $activity[$i]->activity->id;
			$sp_image = NULL;
			if(isset($product_invoice[$i]->product->keyPhoto->derived[1]->url))
			{
				$sp_image = $product_invoice[$i]->product->keyPhoto->derived[1]->url;
			}
			else
			{

				$product = Product::where('bokun_id',$activity[$i]->activity->id)->first();
				if($product)
				{
					$sp_image = ImageHelper::thumbnail($product);
				}
			}

			$sp_title = $activity[$i]->activity->title;
			$sp_rate = $activity[$i]->rate->title;
			$sp_currency = $contents->customerInvoice->currency;
			$sp_date = ProductHelper::texttodate($product_invoice[$i]->dates);
			$sp_cancellation = self::get_cancellation($sp_date,$activity[$i]->rate->cancellationPolicy->simpleCutoffHours);

			$subtotal_product = 0;
			$total_discount = 0;
			$total_product = 0;

			$ShoppingcartProductDetails = array();
			for($z=0;$z<count($lineitems);$z++)
			{
					$itemBookingId = $lineitems[$z]->itemBookingId;
					$itemBookingId = explode("_",$itemBookingId);
					
					$type_product = 'product';
					if($lineitems[$z]->people==0)
					{
						$type_product = "extra";
					}
					if($itemBookingId[1]=="pickup")
					{
						$type_product = "pickup";
					}

					$unitPrice = 'Price per booking';
					if($lineitems[$z]->title!="Passengers")
					{
						$unitPrice = $lineitems[$z]->title;
					}
					

					if($type_product=="product")
					{
						
						$spd_type = $type_product;
						$spd_title = $activity[$i]->activity->title;
						$spd_people = $lineitems[$z]->people;
						$spd_qty = $lineitems[$z]->quantity;
						$spd_price = $lineitems[$z]->unitPrice;
						$spd_unit_price = $unitPrice;
						$spd_currency = $contents->customerInvoice->currency;
						$subtotal = $lineitems[$z]->unitPrice * $lineitems[$z]->quantity;
						$discount = $subtotal - ($lineitems[$z]->discountedUnitPrice * $lineitems[$z]->quantity);
						$total = $subtotal - $discount;

						//===============================================================
						$ShoppingcartProductDetails[] = (object) array(
							'type' => $spd_type,
							'title' => $spd_title,
							'people' => $spd_people,
							'qty' => $spd_qty,
							'price' => $spd_price,
							'unit_price' => $spd_unit_price,
							'discount' => $discount,
							'subtotal' => $subtotal,
							'currency' => $spd_currency,
							'total' => $total
						);
						//===============================================================
						
						$subtotal_product += $subtotal;
						$total_discount += $discount;
						$total_product += $total;
					}

					if($type_product=="extra")
					{
						
						$spd_type = $type_product;
						$spd_title = $activity[$i]->activity->title;
						$spd_people = $lineitems[$z]->people;
						$spd_qty = $lineitems[$z]->quantity;
						$spd_price = $lineitems[$z]->unitPrice;
						$spd_unit_price = $unitPrice;
						$spd_currency = $contents->customerInvoice->currency;
						$subtotal = $lineitems[$z]->unitPrice * $lineitems[$z]->quantity;
						$discount = $subtotal - ($lineitems[$z]->discountedUnitPrice * $lineitems[$z]->quantity);
						$total = $subtotal - $discount;

						//===============================================================
						
						$ShoppingcartProductDetails[] = (object) array(
							'type' => $spd_type,
							'title' => $spd_title,
							'people' => $spd_people,
							'qty' => $spd_qty,
							'price' => $spd_price,
							'unit_price' => $spd_unit_price,
							'discount' => $discount,
							'subtotal' => $subtotal,
							'currency' => $spd_currency,
							'total' => $total
						);
						//===============================================================
						
						

						$subtotal_product += $subtotal;
						$total_discount += $discount;
						$total_product += $total;
					}
					
					if($type_product=="pickup")
					{

						$spd_type = $type_product;
						$spd_title = 'Pick-up and drop-off services';
						$spd_people = $lineitems[$z]->people;
						$spd_qty = 1;
						$spd_price = $lineitems[$z]->total;
						$spd_unit_price = $unitPrice;
						$spd_currency = $contents->customerInvoice->currency;
						$subtotal = $lineitems[$z]->total;
						$discount = $subtotal - $lineitems[$z]->discountedUnitPrice;
						$total = $subtotal - $discount;

						//===============================================================
						
						$ShoppingcartProductDetails[] = (object) array(
							'type' => $spd_type,
							'title' => $spd_title,
							'people' => $spd_people,
							'qty' => $spd_qty,
							'price' => $spd_price,
							'unit_price' => $spd_unit_price,
							'discount' => $discount,
							'subtotal' => $subtotal,
							'currency' => $spd_currency,
							'total' => $total
						);
						//===============================================================
						
						$subtotal_product += $subtotal;
						$total_discount += $discount;
						$total_product += $total;
					}	
					
			}
			

			$deposit = self::get_deposit($activity[$i]->activity->id,$total_product);
			
			
			//=====================================================
			
			$ShoppingcartProducts[] = (object) array(
				'product_confirmation_code' => $sp_product_confirmation_code,
				'booking_id' => $sp_booking_id,
				'product_id' => $sp_product_id,
				'image' => $sp_image,
				'title' => $sp_title,
				'rate' => $sp_rate,
				'currency' =>  $sp_currency,
				'date' => $sp_date,
				'cancellation' => $sp_cancellation,
				'subtotal' => $subtotal_product,
				'discount' => $total_discount,
				'total' => $total_product,
				'due_now' => $deposit->due_now,
				'due_on_arrival' => $deposit->due_on_arrival,
				'product_details' => $ShoppingcartProductDetails,

			);
			//=====================================================

			$grand_discount += $total_discount;
			$grand_subtotal += $subtotal_product;
			$grand_total += $total_product;
			$grand_due_now += $deposit->due_now;
			$grand_due_on_arrival += $deposit->due_on_arrival;
		}

		//===================================================
		
		// QUESTION ==============================================================================
		// Main Question ====
		$questions = BokunHelper::get_questionshoppingcart($id);

		$mainContactDetails = $questions->mainContactQuestions;
		$order = 1;

		$ShoppingcartQuestions = array();
		foreach($mainContactDetails as $mainContactDetail)
		{
			
			$scq_booking_id = NULL;
			$scq_type = 'mainContactDetails';
			$scq_question_id = $mainContactDetail->questionId;
			$scq_label = $mainContactDetail->label;
			$scq_help = NULL;
			$scq_data_type = $mainContactDetail->dataType;
			$scq_data_format = NULL;
			if(isset($mainContactDetail->dataFormat)) $scq_data_format = $mainContactDetail->dataFormat;
			$scq_required = $mainContactDetail->required;
			$scq_select_option = $mainContactDetail->selectFromOptions;
			$scq_select_multiple = $mainContactDetail->selectMultiple;
			$scq_order = $order;
			$order += 1;

			$ShoppingcartQuestionOptions = array();
			if($mainContactDetail->selectFromOptions=="true")
			{
				$order_option = 1;
				foreach($mainContactDetail->answerOptions as $answerOption)
				{
					$scqd_label = $answerOption->label;
					$scqd_value = $answerOption->value;
					$scqd_order = $order_option;
					$order_option += 1;

					$ShoppingcartQuestionOptions[] = (object) array(
						'label' => $scqd_label,
						'value' => $scqd_value,
						'order' => $scqd_order,
					);

					
				}
			}

			if($scq_data_format=="EMAIL_ADDRESS") $scq_label = "Email";
			if($scq_data_format=="PHONE_NUMBER") $scq_label = "Phone / WhatsApp";

			$ShoppingcartQuestions[] = (object) array(
				'type' => $scq_type,
				'when_to_ask' => 'booking',
				'question_id' => $scq_question_id,
				'booking_id' => $scq_booking_id,
				'label' => $scq_label,
				'help' => $scq_help,
				'data_type' => $scq_data_type,
				'data_format' => $scq_data_format,
				'required' => $scq_required,
				'select_option' => $scq_select_option,
				'select_multiple' => $scq_select_multiple,
				'order' => $scq_order,
				'answer' => '',
				'question_options' => $ShoppingcartQuestionOptions
			);
		}
		
		
		//===========================================================================
		$order = 1;
		for($ii = 0; $ii < count($questions->checkoutOptions); $ii++){
			// Pickup Question
			if(isset($questions->checkoutOptions[$ii]->pickup->questions)){
					$activityBookingId = $questions->checkoutOptions[$ii]->activityBookingDetail->activityBookingId;
					$pickupQuestion = $questions->checkoutOptions[$ii]->pickup->questions[0];

					$scq_type = 'pickupQuestions';
					$scq_booking_id = $activityBookingId;
					$scq_question_id = $pickupQuestion->questionId;
					$scq_label = $pickupQuestion->label;
					$scq_help = NULL;
					$scq_data_type = $pickupQuestion->dataType;
					$scq_data_format = NULL;
					$scq_required = $pickupQuestion->required;
					$scq_select_option = $pickupQuestion->selectFromOptions;
					$scq_select_multiple = $pickupQuestion->selectMultiple;
					$scq_order = $order;
					$order += 1;

					$ShoppingcartQuestions[] = (object) array(
						'type' => $scq_type,
						'when_to_ask' => 'booking',
						'question_id' => $scq_question_id,
						'booking_id' => $scq_booking_id,
						'label' => $scq_label,
						'help' => $scq_help,
						'data_type' => $scq_data_type,
						'data_format' => $scq_data_format,
						'required' => $scq_required,
						'select_option' => $scq_select_option,
						'select_multiple' => $scq_select_multiple,
						'order' => $scq_order,
						'answer' => '',
						'question_options' => array()
					);

			}

			// ActivityBookings question per booking
			if(isset($questions->checkoutOptions[$ii]->perBookingQuestions)){
				$activityBookingId = $questions->checkoutOptions[$ii]->activityBookingDetail->activityBookingId;
				for($jj = 0; $jj < count($questions->checkoutOptions[$ii]->perBookingQuestions); $jj++)
				{
					$activityBookingQuestion = $questions->checkoutOptions[$ii]->perBookingQuestions[$jj];

					$scq_type = 'activityBookings';
					$scq_booking_id = $activityBookingId;
					$scq_question_id =  $activityBookingQuestion->questionId;
					$scq_label = $activityBookingQuestion->label;
					$scq_help = NULL;
					if(isset($activityBookingQuestion->help)) $scq_help = $activityBookingQuestion->help;
					$scq_data_type = $activityBookingQuestion->dataType;
					$scq_data_format = NULL;
					if(isset($activityBookingQuestion->dataFormat)) $scq_data_format = $activityBookingQuestion->dataFormat;
					$scq_required = $activityBookingQuestion->required;
					$scq_select_option = $activityBookingQuestion->selectFromOptions;
					$scq_select_multiple = $activityBookingQuestion->selectMultiple;
					$scq_order = $order;
					$order += 1;

					

					$ShoppingcartQuestionOptions = array();
					if($activityBookingQuestion->selectFromOptions=="true")
					{
						$order_option = 1;
						foreach($activityBookingQuestion->answerOptions as $answerOption)
						{
							
							

							$scqd_label = $answerOption->label;
							$scqd_value = $answerOption->value;
							$scqd_order = $order_option;

							$ShoppingcartQuestionOptions[] = (object) array(
								'label' => $scqd_label,
								'value' => $scqd_value,
								'order' => $scqd_order,
							);

							$order_option += 1;
						}
					}

					$ShoppingcartQuestions[] = (object) array(
						'type' => $scq_type,
						'when_to_ask' => 'booking',
						'question_id' => $scq_question_id,
						'booking_id' => $scq_booking_id,
						'label' => $scq_label,
						'help' => $scq_help,
						'data_type' => $scq_data_type,
						'data_format' => $scq_data_format,
						'required' => $scq_required,
						'select_option' => $scq_select_option,
						'select_multiple' => $scq_select_multiple,
						'order' => $scq_order,
						'answer' => '',
						'question_options' => $ShoppingcartQuestionOptions
					);

				}
			}

			// ActivityBookings question per participant
			if(isset($questions->checkoutOptions[$ii]->participants))
			{
				$activityBookingId = $questions->checkoutOptions[$ii]->activityBookingDetail->activityBookingId;
				$participant_number = 1;
				for($jj = 0; $jj < count($questions->checkoutOptions[$ii]->participants); $jj++)
				{
					$participantQuestions = $questions->checkoutOptions[$ii]->participants[$jj]->participantQuestions;
					$scq_type = 'activityBookings';
					$scq_participant_number = $participant_number;
					//$scq_booking_id = $participantQuestions->bookingId;
					$order = 1;

					foreach($participantQuestions->questions as $question)
					{
						$scq_question_id =  $question->questionId.'_'.$participant_number;
						$scq_label = $question->label;
						$scq_data_format = NULL;
						if(isset($question->dataFormat)) $scq_data_format = $question->dataFormat;
						$scq_data_type = $question->dataType;
						$scq_help = NULL;
						if(isset($question->help)) $scq_help = $question->help;
						$scq_required = $question->required;
						$scq_select_option = $question->selectFromOptions;
						$scq_select_multiple = $question->selectMultiple;

						$ShoppingcartQuestionOptions = array();
						if($question->selectFromOptions)
						{
							$order_option = 1;
							foreach($question->answerOptions as $answerOption)
							{
								$scqd_label = $answerOption->label;
								$scqd_value = $answerOption->value;
								$scqd_order = $order_option;

								$ShoppingcartQuestionOptions[] = (object) array(
									'label' => $scqd_label,
									'value' => $scqd_value,
									'order' => $scqd_order,
								);

								$order_option += 1;
							}
						}
						
						$ShoppingcartQuestions[] = (object) array(
							'type' => $scq_type,
							'when_to_ask' => 'participant',
							'participant_number' => $scq_participant_number,
							'question_id' => $scq_question_id,
							'booking_id' => $activityBookingId,
							'label' => $scq_label,
							'help' => $scq_help,
							'data_type' => $scq_data_type,
							'data_format' => $scq_data_format,
							'required' => $scq_required,
							'select_option' => $scq_select_option,
							'select_multiple' => $scq_select_multiple,
							'order' => $order,
							'answer' => '',
							'question_options' => $ShoppingcartQuestionOptions
						);
						$order += 1;
					}

					$participant_number += 1;
					
				}
			}

		}

		
		$ShoppingCart = (object)[
			'session_id' => $s_session_id,
			'booking_status' => $s_booking_status,
			'booking_channel' => $s_booking_channel,
			'confirmation_code' => $s_confirmation_code,
			'currency' => $s_currency,
			'promo_code' => $s_promo_code,
			'subtotal' => $grand_subtotal,
			'discount' => $grand_discount,
			'total' => $grand_total,
			'due_now' => $grand_due_now,
			'due_on_arrival' => $grand_due_on_arrival,
			'products' => $ShoppingcartProducts,
			'questions' => $ShoppingcartQuestions,
			'url' => GeneralHelper::url()
		];
		
		BookingHelper::save_shoppingcart($id, $ShoppingCart);
		return $ShoppingCart;
	}
	


	public static function update_shoppingcart($contents,$id)
	{

		$activity = $contents->activityBookings;
		$shoppingcart = BookingHelper::read_shoppingcart($id);

		$shoppingcart->session_id = $id;

		$shoppingcart->currency = $contents->customerInvoice->currency;


		unset($shoppingcart->products);
		
		$grand_total = 0;
		$grand_subtotal = 0;
		$grand_discount = 0;
		$grand_due_now = 0;
		$grand_due_on_arrival = 0;

		$ShoppingcartProducts = array();
		for($i=0;$i<count($activity);$i++)
		{
			$product_invoice = $contents->customerInvoice->productInvoices;
			$lineitems = $product_invoice[$i]->lineItems;
			
			$sp_product_confirmation_code = $activity[$i]->productConfirmationCode;
			$sp_booking_id = $activity[$i]->id;
			$sp_product_id = $activity[$i]->activity->id;
			$sp_image = NULL;
			if(isset($product_invoice[$i]->product->keyPhoto->derived[1]->url))
			{
				$sp_image = $product_invoice[$i]->product->keyPhoto->derived[1]->url;
			}
			else
			{
				$product = Product::where('bokun_id',$activity[$i]->activity->id)->first();
				if($product)
				{
					$sp_image = ImageHelper::thumbnail($product);
				}
			}
			$sp_title = $activity[$i]->activity->title;
			$sp_rate = $activity[$i]->rate->title;
			$sp_currency = $contents->customerInvoice->currency;
			
			
			$sp_date = ProductHelper::texttodate($product_invoice[$i]->dates);
			$sp_cancellation = self::get_cancellation($sp_date,$activity[$i]->rate->cancellationPolicy->simpleCutoffHours);
			
			$subtotal_product = 0;
			$total_discount = 0;
			$total_product = 0;


			$ShoppingcartProductDetails = array();
			for($z=0;$z<count($lineitems);$z++)
			{
					$itemBookingId = $lineitems[$z]->itemBookingId;
					$itemBookingId = explode("_",$itemBookingId);
					
					$type_product = 'product';
					if($lineitems[$z]->people==0)
					{
						$type_product = 'extra';
					}
					if($itemBookingId[1]=="pickup"){
						$type_product = "pickup";
					}

					$unitPrice = 'Price per booking';
					if($lineitems[$z]->title!="Passengers")
					{
						$unitPrice = $lineitems[$z]->title;
					}

					if($type_product=="product")
					{
						
						$spd_type = $type_product;
						$spd_title = $activity[$i]->activity->title;
						$spd_people = $lineitems[$z]->people;
						$spd_qty = $lineitems[$z]->quantity;
						$spd_price = $lineitems[$z]->unitPrice;
						$spd_unit_price = $unitPrice;
						$spd_currency = $contents->customerInvoice->currency;
						$subtotal = $lineitems[$z]->unitPrice * $lineitems[$z]->quantity;
						$discount = $subtotal - ($lineitems[$z]->discountedUnitPrice * $lineitems[$z]->quantity);
						$total = $subtotal - $discount;

						$ShoppingcartProductDetails[] = (object) array(
							'type' => $spd_type,
							'title' => $spd_title,
							'people' => $spd_people,
							'qty' => $spd_qty,
							'price' => $spd_price,
							'unit_price' => $spd_unit_price,
							'discount' => $discount,
							'subtotal' => $subtotal,
							'currency' => $spd_currency,
							'total' => $total
						);
						
						

						$subtotal_product += $subtotal;
						$total_discount += $discount;
						$total_product += $total;
					}

					if($type_product=="extra")
					{
						
						$spd_type = $type_product;
						$spd_title = $activity[$i]->activity->title;
						$spd_people = $lineitems[$z]->people;
						$spd_qty = $lineitems[$z]->quantity;
						$spd_price = $lineitems[$z]->unitPrice;
						$spd_unit_price = $unitPrice;
						$spd_currency = $contents->customerInvoice->currency;
						$subtotal = $lineitems[$z]->unitPrice * $lineitems[$z]->quantity;
						$discount = $subtotal - ($lineitems[$z]->discountedUnitPrice * $lineitems[$z]->quantity);
						$total = $subtotal - $discount;

						$ShoppingcartProductDetails[] = (object) array(
							'type' => $spd_type,
							'title' => $spd_title,
							'people' => $spd_people,
							'qty' => $spd_qty,
							'price' => $spd_price,
							'unit_price' => $spd_unit_price,
							'discount' => $discount,
							'subtotal' => $subtotal,
							'currency' => $spd_currency,
							'total' => $total
						);
						
						

						$subtotal_product += $subtotal;
						$total_discount += $discount;
						$total_product += $total;
					}
					
					if($type_product=="pickup")
					{
						$spd_type = $type_product;
						$spd_title = 'Pick-up and drop-off services';
						$spd_people = $lineitems[$z]->people;
						$spd_qty = 1;
						$spd_price = $lineitems[$z]->total;
						$spd_unit_price = $unitPrice;
						$spd_currency = $contents->customerInvoice->currency;
						$subtotal = $lineitems[$z]->total;
						$discount = $subtotal - $lineitems[$z]->discountedUnitPrice;
						$total = $subtotal - $discount;
						
						$ShoppingcartProductDetails[] = (object) array(
							'type' => $spd_type,
							'title' => $spd_title,
							'people' => $spd_people,
							'qty' => $spd_qty,
							'price' => $spd_price,
							'unit_price' => $spd_unit_price,
							'discount' => $discount,
							'subtotal' => $subtotal,
							'currency' => $spd_currency,
							'total' => $total
						);

						$subtotal_product += $subtotal;
						$total_discount += $discount;
						$total_product += $total;
					}	
					
			}
			
			
			$deposit = self::get_deposit($activity[$i]->activity->id,$total_product);
			

			
			$ShoppingcartProducts[] = (object) array(
				'product_confirmation_code' => $sp_product_confirmation_code,
				'booking_id' => $sp_booking_id,
				'product_id' => $sp_product_id,
				'image' => $sp_image,
				'title' => $sp_title,
				'rate' => $sp_rate,
				'currency' =>  $sp_currency,
				'date' => $sp_date,
				'cancellation' => $sp_cancellation,
				'subtotal' => $subtotal_product,
				'discount' => $total_discount,
				'total' => $total_product,
				'due_now' => $deposit->due_now,
				'due_on_arrival' => $deposit->due_on_arrival,
				'product_details' => $ShoppingcartProductDetails,

			);

			$grand_discount += $total_discount;
			$grand_subtotal += $subtotal_product;
			$grand_total += $total_product;
			$grand_due_now += $deposit->due_now;
			$grand_due_on_arrival += $deposit->due_on_arrival;
		}
		

		$shoppingcart->products = $ShoppingcartProducts;
		$shoppingcart->subtotal = $grand_subtotal;
		$shoppingcart->discount = $grand_discount;
		$shoppingcart->total = $grand_total;
		$shoppingcart->due_now = $grand_due_now;
		$shoppingcart->due_on_arrival = $grand_due_on_arrival;
		
		//===============================================

		$questions = BokunHelper::get_questionshoppingcart($id);


		foreach($shoppingcart->questions as $key => $question)
		{
			if($question->type=='activityBookings')
			{
				
				unset($shoppingcart->questions[$key]);
			}
			if($question->type=='pickupQuestions')
			{
				unset($shoppingcart->questions[$key]);
			}
		}
		
		$ShoppingcartQuestions = $shoppingcart->questions;

		//===========================================================================
		$order = 1;
		for($ii = 0; $ii < count($questions->checkoutOptions); $ii++){
			// Pickup Question
			if(isset($questions->checkoutOptions[$ii]->pickup->questions)){
					$activityBookingId = $questions->checkoutOptions[$ii]->activityBookingDetail->activityBookingId;
					$pickupQuestion = $questions->checkoutOptions[$ii]->pickup->questions[0];

					$scq_type = 'pickupQuestions';
					$scq_booking_id = $activityBookingId;
					$scq_question_id = $pickupQuestion->questionId;
					$scq_label = $pickupQuestion->label;
					$scq_help = NULL;
					$scq_data_type = $pickupQuestion->dataType;
					$scq_data_format = NULL;
					$scq_required = $pickupQuestion->required;
					$scq_select_option = $pickupQuestion->selectFromOptions;
					$scq_select_multiple = $pickupQuestion->selectMultiple;
					$scq_order = $order;
					$order += 1;

					$ShoppingcartQuestions[] = (object) array(
						'type' => $scq_type,
						'when_to_ask' => 'booking',
						'question_id' => $scq_question_id,
						'booking_id' => $scq_booking_id,
						'label' => $scq_label,
						'help' => $scq_help,
						'data_type' => $scq_data_type,
						'data_format' => $scq_data_format,
						'required' => $scq_required,
						'select_option' => $scq_select_option,
						'select_multiple' => $scq_select_multiple,
						'order' => $scq_order,
						'answer' => '',
						'question_options' => array()
					);

			}

			// ActivityBookings question per booking
			if(isset($questions->checkoutOptions[$ii]->perBookingQuestions)){
				$activityBookingId = $questions->checkoutOptions[$ii]->activityBookingDetail->activityBookingId;
				for($jj = 0; $jj < count($questions->checkoutOptions[$ii]->perBookingQuestions); $jj++)
				{
					$activityBookingQuestion = $questions->checkoutOptions[$ii]->perBookingQuestions[$jj];
					
					$scq_type = 'activityBookings';
					$scq_booking_id = $activityBookingId;
					$scq_question_id =  $activityBookingQuestion->questionId;
					$scq_label = $activityBookingQuestion->label;
					$scq_help = NULL;
					if(isset($activityBookingQuestion->help)) $scq_help = $activityBookingQuestion->help;
					$scq_data_type = $activityBookingQuestion->dataType;
					$scq_data_format = NULL;
					if(isset($activityBookingQuestion->dataFormat)) $scq_data_format = $activityBookingQuestion->dataFormat;
					$scq_required = $activityBookingQuestion->required;
					$scq_select_option = $activityBookingQuestion->selectFromOptions;
					$scq_select_multiple = $activityBookingQuestion->selectMultiple;
					$scq_order = $order;
					$order += 1;

					$ShoppingcartQuestionOptions = array();
					if($activityBookingQuestion->selectFromOptions=="true")
					{
						$order_option = 1;
						foreach($activityBookingQuestion->answerOptions as $answerOption)
						{
							
							$scqd_label = $answerOption->label;
							$scqd_value = $answerOption->value;
							$scqd_order = $order_option;

							$ShoppingcartQuestionOptions[] = (object) array(
								'label' => $scqd_label,
								'value' => $scqd_value,
								'order' => $scqd_order,
							);

							$order_option += 1;
						}
					}

					$ShoppingcartQuestions[] = (object) array(
						'type' => $scq_type,
						'when_to_ask' => 'booking',
						'question_id' => $scq_question_id,
						'booking_id' => $scq_booking_id,
						'label' => $scq_label,
						'help' => $scq_help,
						'data_type' => $scq_data_type,
						'data_format' => $scq_data_format,
						'required' => $scq_required,
						'select_option' => $scq_select_option,
						'select_multiple' => $scq_select_multiple,
						'order' => $scq_order,
						'answer' => '',
						'question_options' => $ShoppingcartQuestionOptions
					);

				}
			}

			// ActivityBookings question per participant
			if(isset($questions->checkoutOptions[$ii]->participants))
			{
				$activityBookingId = $questions->checkoutOptions[$ii]->activityBookingDetail->activityBookingId;
				$participant_number = 1;
				for($jj = 0; $jj < count($questions->checkoutOptions[$ii]->participants); $jj++)
				{
					$participantQuestions = $questions->checkoutOptions[$ii]->participants[$jj]->participantQuestions;
					$scq_type = 'activityBookings';
					$scq_participant_number = $participant_number;
					//$scq_booking_id = $participantQuestions->bookingId;
					$order = 1;

					foreach($participantQuestions->questions as $question)
					{
						$scq_question_id =  $question->questionId.'_'.$participant_number;
						$scq_label = $question->label;
						$scq_data_format = NULL;
						if(isset($question->dataFormat)) $scq_data_format = $question->dataFormat;
						$scq_data_type = $question->dataType;
						$scq_help = NULL;
						if(isset($question->help)) $scq_help = $question->help;
						$scq_required = $question->required;
						$scq_select_option = $question->selectFromOptions;
						$scq_select_multiple = $question->selectMultiple;

						$ShoppingcartQuestionOptions = array();
						if($question->selectFromOptions)
						{
							$order_option = 1;
							foreach($question->answerOptions as $answerOption)
							{
								$scqd_label = $answerOption->label;
								$scqd_value = $answerOption->value;
								$scqd_order = $order_option;

								$ShoppingcartQuestionOptions[] = (object) array(
									'label' => $scqd_label,
									'value' => $scqd_value,
									'order' => $scqd_order,
								);

								$order_option += 1;
							}
						}
						
						$ShoppingcartQuestions[] = (object) array(
							'type' => $scq_type,
							'when_to_ask' => 'participant',
							'participant_number' => $scq_participant_number,
							'question_id' => $scq_question_id,
							'booking_id' => $activityBookingId,
							'label' => $scq_label,
							'help' => $scq_help,
							'data_type' => $scq_data_type,
							'data_format' => $scq_data_format,
							'required' => $scq_required,
							'select_option' => $scq_select_option,
							'select_multiple' => $scq_select_multiple,
							'order' => $order,
							'answer' => '',
							'question_options' => $ShoppingcartQuestionOptions
						);
						$order += 1;
					}

					$participant_number += 1;
					
				}
			}

		}

		$shoppingcart->questions = $ShoppingcartQuestions;
		$shoppingcart->url = GeneralHelper::url();
		//===========================================
		BookingHelper::save_shoppingcart($id, $shoppingcart);
		//===========================================
		return $shoppingcart;
	}
	

	public static function get_cancellation($date,$hour=null)
	{
		$value = "";
		if($date==null)
		{
			$value = "Non-refundable";
		}
		else if($hour==null)
		{
			$value = "Fully refundable until ". ProductHelper::datetotext($date);
		}
		else
		{
			$date = Carbon::createFromFormat('Y-m-d H:i:s', $date)->addHours($hour*-1);
			$now = date('Y-m-d H:i:s');
			if($now>=$date)
			{
				$value = "Non-refundable";
			}
			else
			{
				$value = "Fully refundable until ". ProductHelper::datetotext($date);
			}
			
		}
		return $value;
	}

	public static function get_deposit($bokunId,$amount)
	{
		$due_now = 0;
		$due_on_arrival = 0;
		$dataObj = new \stdClass();
		$product = Product::where('bokun_id',$bokunId)->first();

		if($product->deposit_amount==0)
		{
			$dataObj->due_now = $amount;
			$dataObj->due_on_arrival = 0;
		}
		else
		{
			if($product->deposit_percentage)
			{
				
				$dataObj->due_now = $amount * $product->deposit_amount / 100;
				$dataObj->due_on_arrival = $amount - $dataObj->due_now;
			}
			else
			{
				$dataObj->due_now = $product->deposit_amount;
				$dataObj->due_on_arrival = $amount - $dataObj->due_now;
			}
		}
			
		return $dataObj;
	}

	public static function get_shoppingcart($id,$action="insert",$contents)
	{
		if($action=="insert")
			{
				$shoppingcart = self::insert_shoppingcart($contents,$id);
			}
		if($action=="update")
			{
				$shoppingcart = self::update_shoppingcart($contents,$id);
			}

		if($shoppingcart->promo_code!=null)
		{
			VoucherHelper::apply_voucher($shoppingcart->session_id,$shoppingcart->promo_code);
		}
	}
	
	public static function shoppingcart_mail($shoppingcart)
	{
		$payload = new \stdClass();
		$payload->app = 'mail';
		$payload->session_id = $shoppingcart->session_id;
		$payload->confirmation_code = $shoppingcart->confirmation_code;

		if($shoppingcart->booking_status=="CONFIRMED")
		{
			TaskHelper::create($payload);
		}
	}

	public static function shoppingcart_whatsapp($shoppingcart)
	{
		$payload = new \stdClass();
		$payload->app = 'whatsapp';
		$payload->session_id = $shoppingcart->session_id;
		$payload->confirmation_code = $shoppingcart->confirmation_code;

		if($shoppingcart->booking_status=="CONFIRMED")
		{
			TaskHelper::create($payload);
		}
	}

	public static function shoppingcart_notif($shoppingcart)
	{
		$payload = new \stdClass();
		$payload->app = 'pushover';
		$payload->session_id = $shoppingcart->session_id;
		$payload->confirmation_code = $shoppingcart->confirmation_code;

		TaskHelper::create($payload);
		
	}

	public static function shoppingcart_clear($sessionId)
	{
		BokunHelper::get_removepromocode($sessionId);
		$shoppingcart = BookingHelper::read_shoppingcart($sessionId);
		foreach($shoppingcart->products as $product)
		{
			BokunHelper::get_removeactivity($sessionId,$product->booking_id);
		}
		Cache::forget('_'.$sessionId);
        return $shoppingcart;
	}

	public static function check_question_json($sessionId,$data)
	{
		
		$status = true;
		$array = array();

		$shoppingcart = BookingHelper::read_shoppingcart($sessionId);

		
		foreach($shoppingcart->questions as $question)
		{
				if($question->required)
            	{
            		$rules = array('data' => 'required');
            		$inputs = array(
    						'data' => $data['questions'][$question->question_id]
							);
            		$validator = Validator::make($inputs, $rules);
            		if($validator->fails()) {
						$status = false;
						$array[$question->question_id] = array($question->label .' field is required.');
					}
            	}

            	
					if($question->question_id=="firstName")
						{
							$rules = array('firstName' => 'regex:/^[\pL\s]+$/u');

							$inputs = array(
    							'firstName' => $data['questions'][$question->question_id]
							);
							$validator = Validator::make($inputs, $rules);
							if($validator->fails()) {
    							$status = false;
								$array[$question->question_id] = array('Please use alphabetic characters only');
							}
						}

					if($question->question_id=="lastName")
						{
							$rules = array('lastName' => 'regex:/^[\pL\s]+$/u');

							$inputs = array(
    							'lastName' => $data['questions'][$question->question_id]
							);
							$validator = Validator::make($inputs, $rules);
							if($validator->fails()) {
    							$status = false;
								$array[$question->question_id] = array('Please use alphabetic characters only');
							}
						}

					if($question->question_id=="email")
						{
							$rules = array('email' => 'email');
							$inputs = array(
    							'email' => $data['questions'][$question->question_id]
							);
							$validator = Validator::make($inputs, $rules);
							if($validator->fails()) {
    							$status = false;
								$array[$question->question_id] = array('Email format not valid.');
							}
						}

					if($question->question_id=="phoneNumber")
						{
							/*
							$rules = array('phoneNumber' => 'phone:AUTO');
							$inputs = array(
    							'phoneNumber' => $data['questions'][$question->question_id]
							);
							$validator = Validator::make($inputs, $rules);
							if($validator->fails()) {
    							$status = false;
								$array[$question->question_id] = array('Phone number format not valid.');
							}
							*/
						}

				
		}

		
        return $array;
	}

	public static function save_question_json($sessionId,$data)
	{
		
		$shoppingcart = BookingHelper::read_shoppingcart($sessionId);
		
		foreach($shoppingcart->questions as $question)
            {
            	
            	foreach ($data['questions'] as $key => $value) {
    				if($question->question_id==$key)
    				{
    					$question->answer = $value;
    				}
				}
                
                if($question->select_option)
                {

                	foreach($question->question_options as $question_option)
                	{
                		if(isset($data['questions'][$question->question_id]))
                		{
                			if($question_option->value==$data['questions'][$question->question_id])
                			{
                				$question_option->answer = 1;
                			}
                			else
                			{
                				$question_option->answer = 0;
                			}
                		}
                		else
                		{
                			$question_option->answer = 0;
                		}
                		
                	}
                    
                }
                
            }

        $shoppingcart = BookingHelper::save_shoppingcart($sessionId, $shoppingcart);
        return $shoppingcart;
	}

	public static function get_firstAvailability($activityId,$year,$month)
	{
		$dateObj = Carbon::now()->timestamp * 1000;
		$localizedDate = null;
		$value = [];

		$availability = self::get_calendar($activityId,$year,$month);
		
		if($availability->firstAvailableDay!=null)
		{
			$count_availability = count($availability->firstAvailableDay->availabilities);
			$dateObj = $availability->firstAvailableDay->dateObj;

			for($i=0;$i<$count_availability;$i++)
			{
				$value[] = $availability->firstAvailableDay->availabilities[$i]->activityAvailability;
			}
		}
		
		$month = date("n",$dateObj/1000);
		$year = date("Y",$dateObj/1000);
		$day = date("d",$dateObj/1000);
		$localizedDate = GeneralHelper::dateFormat($year.'-'.$month.'-'.$day,11);
		
		
		$dataObj[] = [
			'date' => $dateObj,
			'localizedDate' => $localizedDate,
			'availabilities' => $value
		];
		return $dataObj;
	}

	public static function get_calendar($activityId,$year,$month)
	{
		
        $contents = BokunHelper::get_calendar($activityId,$year,$month);
        
        //print_r($contents);
        //exit();
        $value[] = $contents->firstAvailableDay;
        

        //$contents->todayInMonth = true;
        //print_r($contents->todayInMonth);


        //=========================================================
        /*
        foreach($value as $firstDay)
        	{
				if($firstDay->soldOut==true)
				{
					$firstDay->availabilities = [];
					$firstDay->soldOut = false;
					$firstDay->available = false;
					$firstDay->empty = true;
				}
        	}

        foreach($contents->weeks as $week)
        {
            foreach($week->days as $day)
            {
            	if($day->soldOut==true)
				{
            		$day->availabilities = [];
					$day->soldOut = false;
					$day->available = false;
					$day->empty = true;
				}
            }
        }
		*/
        //=========================================================
        $min_participant = Product::where('bokun_id',$activityId)->first()->min_participant;
        //=========================================================
        foreach($value as $firstDay)
        	{
        		
        		//$firstDay->month = (int)date("n",$firstDay->dateObj/1000);
        		//if($firstDay->month<date("n"))
        		//{
        			//$contents->todayInMonth = true;
        		//}
        		if(isset($firstDay->availabilities))
        		{
        			foreach($firstDay->availabilities as $availability)
					{
						$availability->activityAvailability->minParticipants = $min_participant;
						$availability->activityAvailability->minParticipantsToBookNow = $min_participant;

						$availability->data->minParticipants = $min_participant;
						$availability->data->minParticipantsToBookNow = $min_participant;
					}
        		}
				
        	}

        
        foreach($contents->weeks as $week)
        {
            foreach($week->days as $day)
            {
            	foreach($day->availabilities as $availability)
				{
					$availability->activityAvailability->minParticipants = $min_participant;
					$availability->activityAvailability->minParticipantsToBookNow = $min_participant;

					$availability->data->minParticipants = $min_participant;
					$availability->data->minParticipantsToBookNow = $min_participant;
				}

				
            }
        }
        //=========================================================
        $closeouts = CloseOut::where('bokun_id', $activityId)->where('date','>=',date('Y-m-d'))->get();

        foreach($value as $firstDay)
        	{
        		if(isset($firstDay->availabilities))
        		{
        			foreach($closeouts as $closeout)
        			{
        				if($closeout->date == $firstDay->fullDate)
						{
						
        					foreach($firstDay->availabilities as $availability)
                    		{
                    			$firstDay->empty = true;
                    			$firstDay->availabilities = [];
							}
        				
						
						}
        			}
				}
        	}

        
        foreach($contents->weeks as $week)
        {
            foreach($week->days as $day)
            {
            	foreach($closeouts as $closeout)
        		{
                                    if($closeout->date == $day->fullDate)
                                    {
                                            $day->empty = true;
                                            $day->available = false;
                                            $day->availabilities = [];
                                    }
                }
            }
        }
        //=========================================================
        
        
        // Check booking full or not
        $bookings = array();
		
		$group_shoppingcart_products = ShoppingcartProduct::with('shoppingcart')
		->whereHas('shoppingcart', function($query) {
                $query->where('booking_status','CONFIRMED');
			    //$query->where('booking_channel','WEBSITE')->orWhere('booking_channel','AIRBNB');
            })
		->where('product_id',$activityId)->whereYear('date','=',$year)->whereMonth('date','=',$month)->whereDate('date', '>=', Carbon::now())->groupBy(['date'])->select('date')->get();

		foreach($group_shoppingcart_products as $group_shoppingcart_product)
        {
        	$date = Carbon::parse($group_shoppingcart_product->date)->format('Y-m-d');
        	$time = Carbon::parse($group_shoppingcart_product->date)->format('H:i');
        	$people = ShoppingcartProductDetail::with('shoppingcart_product')
            	->WhereHas('shoppingcart_product', function($query) use ($date,$activityId) {
            	$query->whereDate('date','=',$date)->where(['product_id'=>$activityId])->WhereHas('shoppingcart', function($query) {
              		return $query->where('booking_status','CONFIRMED');
              		//return $query->where('booking_channel','WEBSITE')->orWhere('booking_channel','AIRBNB');
            	});
            	})->get()->sum('people');

            $min_participant = Product::where('bokun_id',$activityId)->first()->min_participant;

            $bookings[] = (object)[
            	"date" => $date,
            	"time" => $time,
            	"people" => $people,
            	"min_participant" => $min_participant,
        	];
        }


        if(count($bookings)>0)
        {
        	foreach($value as $firstDay)
        	{
        		foreach($firstDay->availabilities as $availability)
				{
					foreach($bookings as $booking)
					{
						if($booking->date == $firstDay->fullDate)
						{
							if($availability->activityAvailability->startTime==$booking->time)
							{
                        		$availability->data->bookedParticipants +=  $booking->people;
								$availability->data->availabilityCount -= $booking->people;

								$availability->activityAvailability->bookedParticipants +=  $booking->people;
								$availability->activityAvailability->availabilityCount -= $booking->people;

								// cek cek aja
								if($booking->people>=$booking->min_participant)
								{
									$availability->activityAvailability->minParticipantsToBookNow = 1;
								}
								if($booking->people>=$booking->min_participant)
								{
									$availability->data->minParticipantsToBookNow = 1;
								}
                                        
								$availability->availabilityCount -= $booking->people;

								if($availability->availabilityCount<=0) $firstDay->soldOut = true;
							}

						}
					}

                }
					
				
        	}
        }

        

        if(count($bookings)>0)
        {
        foreach($contents->weeks as $week)
        {
            foreach($week->days as $day)
            {
                if(!$day->notInCurrentMonth)
                {
                    if(!$day->past)
                    {
                        if(!$day->pastCutoff)
                        {
                            if(!$day->soldOut)
                            {

								foreach($day->availabilities as $availability)
								{
									foreach($bookings as $booking)
									{
										if($booking->date == $day->fullDate)
										{

											if($availability->activityAvailability->startTime==$booking->time)
											{
												$availability->data->bookedParticipants +=  $booking->people;
                                            	$availability->data->availabilityCount -= $booking->people;

                                            	$availability->activityAvailability->bookedParticipants +=  $booking->people;
                                            	$availability->activityAvailability->availabilityCount -= $booking->people;
                                        	
                                        		// cek cek aja
                                        		if($booking->people>=$booking->min_participant)
												{
													$availability->activityAvailability->minParticipantsToBookNow = 1;
												}
												if($booking->people>=$booking->min_participant)
												{
													$availability->data->minParticipantsToBookNow = 1;
												}


                                            	$availability->availabilityCount -= $booking->people;

                                            	if($availability->availabilityCount<=0) $day->soldOut = true;
											}

                                            	
                                        }
                                    }
                                }
                                   
                                
                            }
                        }
                    }
                }
                
            }
        }
    	}
		
        //=========================================================
        return $contents;
	}

	public static function shoppingcart_checker($contents,$sessionId)
	{
		$status = false;

		$fullDate = $contents->date;
        $year = date("Y",strtotime($fullDate));
        $month = date("m",strtotime($fullDate));
        $day = date("d",strtotime($fullDate));
        $activityId = $contents->activityId;

        $sc_quantity = 0;
        $shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        foreach($shoppingcart->products as $product)
        {

        	foreach($product->product_details as $product_detail)
        	{
        		if(substr($product->date,0,10)==$fullDate)
        		{
        			$sc_quantity += $product_detail->qty;
        		}
        		
        	}
        }

        $quantity = 0;
        $pricingCategoryBookings = $contents->pricingCategoryBookings;
        foreach($pricingCategoryBookings as $pricingCategoryBooking)
        {
            $quantity += $pricingCategoryBooking->quantity;
        }

        $people = 0;
        $shoppingcart_products = ShoppingcartProduct::with('shoppingcart')
        ->whereHas('shoppingcart', function($query) {
                $query->where('booking_status','CONFIRMED');
        })->where('product_id',$activityId)->whereYear('date','=',$year)->whereMonth('date','=',$month)->whereDay('date','=',$day)->get();
        
        foreach($shoppingcart_products as $shoppingcart_product)
        {
            
            foreach($shoppingcart_product->shoppingcart_product_details as $shoppingcart_product_detail)
            {
                $people += $shoppingcart_product_detail->people;
            }
        }

        $availabilityCount = 0;
        $contents = BokunHelper::get_calendar($activityId,$year,$month);
        foreach($contents->weeks as $week)
        {
            foreach($week->days as $day)
            {
                if(!$day->notInCurrentMonth)
                {
                    if(!$day->past)
                    {
                        if(!$day->pastCutoff)
                        {
                            if(!$day->soldOut)
                            {

                                foreach($day->availabilities as $availability)
                                {
                                    if($day->fullDate==$fullDate)
                                    {
                                        $availabilityCount = $availability->data->availabilityCount;
                                    }
                                }
                                   
                                
                            }
                        }
                    }
                }
                
            }
        }

        //print_r($quantity."<br />");
        //print_r($sc_quantity."<br />");
        //print_r($availabilityCount."<br />");
        //print_r($people."<br />");


        if($quantity+$sc_quantity<=$availabilityCount-$people)
        {
        	$status = true;
        }

        return $status;
	}

	public static function shoppingcart_dbtojson($id)
	{
		$shoppingcart = Shoppingcart::find($id);

		$shoppingcart_json = new \stdClass();
        
        $ShoppingcartProducts = [];
        foreach($shoppingcart->shoppingcart_products as $product)
        {
            $ShoppingcartProductDetails = [];   
            foreach($product->shoppingcart_product_details as $product_detail)
            {
                $ShoppingcartProductDetails[] = (object) array(
                    'type' => $product_detail->type,
                    'title' => $product_detail->title,
                    'people' => $product_detail->people,
                    'qty' => $product_detail->qty,
                    'price' => $product_detail->price,
                    'unit_price' => $product_detail->unit_price,
                    'currency' => $product_detail->currency,
                    'subtotal' => $product_detail->subtotal,
                    'discount' => $product_detail->discount,
                    'tax' => $product_detail->tax,
                    'fee' => $product_detail->fee,
                    'admin' => $product_detail->admin,
                    'total' => $product_detail->total
                );
            }
            

            $ShoppingcartProducts[] = (object) array(
                'booking_id' => $product->booking_id,
                'product_confirmation_code' => $product->product_confirmation_code,
                'product_id' => $product->product_id,
                'image' => $product->image,
                'title' => $product->title,
                'rate' => $product->rate,
                'date' => $product->date,
                'cancellation' => $product->cancellation,
                'currency' =>  $product->currency,
                'subtotal' => $product->subtotal,
                'discount' => $product->discount,
                'tax' => $product->tax,
                'fee' => $product->fee,
                'admin' => $product->admin,
                'total' => $product->total,
                'due_now' => $product->due_now,
                'due_on_arrival' => $product->due_on_arrival,
                'product_details' => $ShoppingcartProductDetails
            );
            
        }
        $shoppingcart_json->products = $ShoppingcartProducts;


        $ShoppingcartQuestions = [];
        foreach($shoppingcart->shoppingcart_questions as $question)
        {

            $ShoppingcartQuestionOptions = [];
            foreach($question->shoppingcart_question_options as $question_option)
            {
                $ShoppingcartQuestionOptions[] = (object) array(
                    'label' => $question_option->label,
                    'value' => $question_option->value,
                    'order' => $question_option->order
                );
            }

            $ShoppingcartQuestions[] = (object) array(
                'type' => $question->type,
                'when_to_ask' => $question->when_to_ask,
                'booking_id' => $question->booking_id,
                'participant_number' => $question->participant_number,
                'question_id' => $question->question_id,
                'label' => $question->label,
                'data_type' => $question->data_type,
                'data_format' => $question->data_format,
                'required' => $question->required,
                'select_option' => $question->select_option,
                'select_multiple' => $question->select_multiple,
                'help' => $question->help,
                'answer' => $question->answer,
                'order' => $question->order,
                'question_options' => $ShoppingcartQuestionOptions
            );
        }
        $shoppingcart_json->questions = $ShoppingcartQuestions;

        
        $ShoppingcartPayment = (object) array(
                'order_id' => $shoppingcart->shoppingcart_payment->order_id,
                'authorization_id' => $shoppingcart->shoppingcart_payment->authorization_id,
                'payment_provider' => $shoppingcart->shoppingcart_payment->payment_provider,
                'payment_type' => $shoppingcart->shoppingcart_payment->payment_type,
                'payment_description' => $shoppingcart->shoppingcart_payment->payment_description,
                'bank_name' => $shoppingcart->shoppingcart_payment->bank_name,
                'bank_code' => $shoppingcart->shoppingcart_payment->bank_code,
                'va_number' => $shoppingcart->shoppingcart_payment->va_number,
                'qrcode' => $shoppingcart->shoppingcart_payment->qrcode,
                'amount' => $shoppingcart->shoppingcart_payment->amount,
                'net' => $shoppingcart->shoppingcart_payment->net,
                'currency' => $shoppingcart->shoppingcart_payment->currency,
                'rate' => $shoppingcart->shoppingcart_payment->rate,
                'rate_from' => $shoppingcart->shoppingcart_payment->rate_from,
                'rate_to' => $shoppingcart->shoppingcart_payment->rate_to,
                'link' => $shoppingcart->shoppingcart_payment->link,
                'redirect' => $shoppingcart->shoppingcart_payment->redirect,
                'expiration_date' => $shoppingcart->shoppingcart_payment->expiration_date,
                'payment_status' => $shoppingcart->shoppingcart_payment->payment_status
        );
        
        $shoppingcart_json->payment = $ShoppingcartPayment;
        

        $shoppingcart_json->booking_status = $shoppingcart->booking_status;
        $shoppingcart_json->session_id = $shoppingcart->session_id;
        $shoppingcart_json->booking_channel = $shoppingcart->booking_channel;
        $shoppingcart_json->confirmation_code = $shoppingcart->confirmation_code;
        $shoppingcart_json->promo_code = $shoppingcart->promo_code;
        $shoppingcart_json->currency = $shoppingcart->currency;
        $shoppingcart_json->subtotal = $shoppingcart->subtotal;
        $shoppingcart_json->discount = $shoppingcart->discount;

        $shoppingcart_json->tax = $shoppingcart->tax;
        $shoppingcart_json->fee = $shoppingcart->fee;
        $shoppingcart_json->admin = $shoppingcart->admin;

        $shoppingcart_json->total = $shoppingcart->total;
        $shoppingcart_json->due_now = $shoppingcart->due_now;
        $shoppingcart_json->due_on_arrival = $shoppingcart->due_on_arrival;
        $shoppingcart_json->url = $shoppingcart->url;
        $shoppingcart_json->referer = $shoppingcart->referer;
        
		return $shoppingcart_json;
	}

	public static function send_webhook($curl_url,$curl_data)
	{
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_URL, "$curl_url");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
		$payload = json_encode($curl_data);
		$headerArray[] = "Content-Type: application/json";
		$headerArray[] = 'Content-Length: ' . strlen($payload);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        curl_exec($ch);
        curl_close($ch);
	}

	public static function confirm_transaction($sessionId=null,$shoppingcart_json=null)
	{
		if($shoppingcart_json==null)
		{
			$shoppingcart_json = BookingHelper::read_shoppingcart($sessionId);
		}

		if($sessionId==null)
		{
			$sessionId = Uuid::uuid4()->toString();
		}
		
		$shoppingcart = new Shoppingcart();
		if(isset($shoppingcart_json->booking_status)) $shoppingcart->booking_status = $shoppingcart_json->booking_status;
		if(isset($shoppingcart_json->session_id)) $shoppingcart->session_id = $shoppingcart_json->session_id;
		if(isset($shoppingcart_json->booking_channel)) $shoppingcart->booking_channel = $shoppingcart_json->booking_channel;
		if(isset($shoppingcart_json->confirmation_code)) $shoppingcart->confirmation_code = $shoppingcart_json->confirmation_code;
		if(isset($shoppingcart_json->promo_code)) $shoppingcart->promo_code = $shoppingcart_json->promo_code;
		if(isset($shoppingcart_json->currency)) $shoppingcart->currency = $shoppingcart_json->currency;
		if(isset($shoppingcart_json->subtotal)) $shoppingcart->subtotal = $shoppingcart_json->subtotal;
		if(isset($shoppingcart_json->discount)) $shoppingcart->discount = $shoppingcart_json->discount;
		if(isset($shoppingcart_json->total)) $shoppingcart->total = $shoppingcart_json->total;
		if(isset($shoppingcart_json->due_now)) $shoppingcart->due_now = $shoppingcart_json->due_now;
		if(isset($shoppingcart_json->due_on_arrival)) $shoppingcart->due_on_arrival = $shoppingcart_json->due_on_arrival;
		if(isset($shoppingcart_json->url)) $shoppingcart->url = $shoppingcart_json->url;
		if(isset($shoppingcart_json->referer)) $shoppingcart->referer = $shoppingcart_json->referer;
		
		$shoppingcart->save();

		foreach($shoppingcart_json->products as $product)
		{
			$shoppingcart_product = new ShoppingcartProduct();
			$shoppingcart_product->shoppingcart_id = $shoppingcart->id;

			if(isset($product->booking_id)) $shoppingcart_product->booking_id = $product->booking_id;
			if(isset($product->product_confirmation_code)) $shoppingcart_product->product_confirmation_code = $product->product_confirmation_code;
			if(isset($product->product_id)) $shoppingcart_product->product_id = $product->product_id;
			if(isset($product->image)) $shoppingcart_product->image = $product->image;
			if(isset($product->title)) $shoppingcart_product->title = $product->title;
			if(isset($product->rate)) $shoppingcart_product->rate = $product->rate;
			if(isset($product->date)) $shoppingcart_product->date = $product->date;
			if(isset($product->cancellation)) $shoppingcart_product->cancellation = $product->cancellation;
			if(isset($product->currency)) $shoppingcart_product->currency = $product->currency;
			if(isset($product->subtotal)) $shoppingcart_product->subtotal = $product->subtotal;
			if(isset($product->discount)) $shoppingcart_product->discount = $product->discount;
			if(isset($product->total)) $shoppingcart_product->total = $product->total;
			if(isset($product->due_now)) $shoppingcart_product->due_now = $product->due_now;
			if(isset($product->due_on_arrival)) $shoppingcart_product->due_on_arrival = $product->due_on_arrival;

			$shoppingcart_product->save();
			
			foreach($product->product_details as $product_detail)
			{
				$shoppingcart_product_detail = new ShoppingcartProductDetail();
				$shoppingcart_product_detail->shoppingcart_product_id = $shoppingcart_product->id;

				if(isset($product_detail->type)) $shoppingcart_product_detail->type = $product_detail->type;
				if(isset($product_detail->title)) $shoppingcart_product_detail->title = $product_detail->title;
				if(isset($product_detail->people)) $shoppingcart_product_detail->people = $product_detail->people;
				if(isset($product_detail->qty)) $shoppingcart_product_detail->qty = $product_detail->qty;
				if(isset($product_detail->price)) $shoppingcart_product_detail->price = $product_detail->price;
				if(isset($product_detail->unit_price)) $shoppingcart_product_detail->unit_price = $product_detail->unit_price;
				if(isset($product_detail->currency)) $shoppingcart_product_detail->currency = $product_detail->currency;
				if(isset($product_detail->subtotal)) $shoppingcart_product_detail->subtotal = $product_detail->subtotal;
				if(isset($product_detail->discount)) $shoppingcart_product_detail->discount = $product_detail->discount;
				if(isset($product_detail->total)) $shoppingcart_product_detail->total = $product_detail->total;

				$shoppingcart_product_detail->save();
			}
		}

		foreach($shoppingcart_json->questions as $question)
		{
			$shoppingcart_question = new ShoppingcartQuestion();
			$shoppingcart_question->shoppingcart_id = $shoppingcart->id;
			$shoppingcart_question->type = $question->type;
			if(isset($question->when_to_ask)) $shoppingcart_question->when_to_ask = $question->when_to_ask;
			if(isset($question->participant_number)) $shoppingcart_question->participant_number = $question->participant_number;

			if(isset($question->booking_id)) $shoppingcart_question->booking_id = $question->booking_id;
			if(isset($question->question_id)) $shoppingcart_question->question_id = $question->question_id;
			if(isset($question->label)) $shoppingcart_question->label = $question->label;
			if(isset($question->data_type)) $shoppingcart_question->data_type = $question->data_type;
			if(isset($question->data_format)) $shoppingcart_question->data_format = $question->data_format;
			if(isset($question->required)) $shoppingcart_question->required = $question->required;
			if(isset($question->select_option)) $shoppingcart_question->select_option = $question->select_option;
			if(isset($question->select_multiple)) $shoppingcart_question->select_multiple = $question->select_multiple;
			if(isset($question->help)) $shoppingcart_question->help = $question->help;
			if(isset($question->order)) $shoppingcart_question->order = $question->order;
			if(isset($question->answer)) $shoppingcart_question->answer = $question->answer;

			$shoppingcart_question->save();

			foreach($question->question_options as $question_option)
			{
				$shoppingcart_question_option = new ShoppingcartQuestionOption();
				$shoppingcart_question_option->shoppingcart_question_id = $shoppingcart_question->id;

				if(isset($question_option->label)) $shoppingcart_question_option->label = $question_option->label;
				if(isset($question_option->value)) $shoppingcart_question_option->value = $question_option->value;
				if(isset($question_option->order)) $shoppingcart_question_option->order = $question_option->order;
				if(isset($question_option->answer)) $shoppingcart_question_option->answer = $question_option->answer;

				$shoppingcart_question_option->save();
				
			}
		}

		$shoppingcart_payment = new ShoppingcartPayment();
		$shoppingcart_payment->shoppingcart_id = $shoppingcart->id;

		if(isset($shoppingcart_json->payment->order_id)) $shoppingcart_payment->order_id = $shoppingcart_json->payment->order_id;
		if(isset($shoppingcart_json->payment->authorization_id)) $shoppingcart_payment->authorization_id = $shoppingcart_json->payment->authorization_id;
		if(isset($shoppingcart_json->payment->payment_provider)) $shoppingcart_payment->payment_provider = $shoppingcart_json->payment->payment_provider;
		if(isset($shoppingcart_json->payment->payment_type)) $shoppingcart_payment->payment_type = $shoppingcart_json->payment->payment_type;

		if(isset($shoppingcart_json->payment->payment_description)) $shoppingcart_payment->payment_description = $shoppingcart_json->payment->payment_description;

		if(isset($shoppingcart_json->payment->bank_name)) $shoppingcart_payment->bank_name = $shoppingcart_json->payment->bank_name;
		if(isset($shoppingcart_json->payment->bank_code)) $shoppingcart_payment->bank_code = $shoppingcart_json->payment->bank_code;
		if(isset($shoppingcart_json->payment->va_number)) $shoppingcart_payment->va_number = $shoppingcart_json->payment->va_number;
		if(isset($shoppingcart_json->payment->qrcode)) $shoppingcart_payment->qrcode = $shoppingcart_json->payment->qrcode;
		if(isset($shoppingcart_json->payment->link)) $shoppingcart_payment->link = $shoppingcart_json->payment->link;
		if(isset($shoppingcart_json->payment->redirect)) $shoppingcart_payment->redirect = $shoppingcart_json->payment->redirect;
		if(isset($shoppingcart_json->payment->amount)) $shoppingcart_payment->amount = $shoppingcart_json->payment->amount;
		if(isset($shoppingcart_json->payment->currency)) $shoppingcart_payment->currency = $shoppingcart_json->payment->currency;
		if(isset($shoppingcart_json->payment->rate)) $shoppingcart_payment->rate = $shoppingcart_json->payment->rate;
		if(isset($shoppingcart_json->payment->rate_from)) $shoppingcart_payment->rate_from = $shoppingcart_json->payment->rate_from;
		if(isset($shoppingcart_json->payment->rate_to)) $shoppingcart_payment->rate_to = $shoppingcart_json->payment->rate_to;
		if(isset($shoppingcart_json->payment->expiration_date)) $shoppingcart_payment->expiration_date = $shoppingcart_json->payment->expiration_date;
		if(isset($shoppingcart_json->payment->payment_status)) $shoppingcart_payment->payment_status = $shoppingcart_json->payment->payment_status;

		$shoppingcart_payment->save();

		
		if(config('site.webhook')=="yes")
		{
			if(!isset($shoppingcart_json->is_webhook))
			{
				$shoppingcart_json = BookingHelper::shoppingcart_dbtojson($shoppingcart->id);
				$shoppingcart_json->is_webhook = "yes";
				$shoppingcart_json->webhook_key = env('APP_KEY');
				BookingHelper::send_webhook(config('site.webhook_url'),$shoppingcart_json);
			}
		}
		
		return $shoppingcart;
		
	}


	

	public static function save_shoppingcart($sessionId,$shoppingcart)
	{
		Cache::forget('_'. $sessionId);
        Cache::add('_'. $sessionId, $shoppingcart, 172800);
        return $shoppingcart;
	}

	public static function read_shoppingcart($sessionId)
	{
		$shoppingcart = Cache::get('_'. $sessionId);
		return $shoppingcart;
	}

	public static function set_confirmationCode($sessionId)
	{
		$shoppingcart = BookingHelper::read_shoppingcart($sessionId);
		$confirmation_code = self::get_ticket();
		$shoppingcart->confirmation_code = $confirmation_code;
        BookingHelper::save_shoppingcart($sessionId,$shoppingcart);
        return $shoppingcart;
	}

	public static function confirm_booking($sessionId,$notif_customer=true)
	{
		$shoppingcart = BookingHelper::read_shoppingcart($sessionId);
        $shoppingcart = self::confirm_transaction($sessionId);

        self::shoppingcart_clear($sessionId);

        if($notif_customer)
        {
        	self::shoppingcart_whatsapp($shoppingcart);
        	self::shoppingcart_mail($shoppingcart);
        }

        self::shoppingcart_notif($shoppingcart);
        return $shoppingcart;
	}

	

	

	public static function due_date($shoppingcart, $data_type = "json")
	{
		$due_date = null;
		$date = null;

		if($data_type=="json")
		{
			if(isset($shoppingcart->payment->expiration_date)) $date = $shoppingcart->payment->expiration_date;
		}
		else
		{
			$date = $shoppingcart->shoppingcart_payment->expiration_date;
		}


		if($date!==null)
		{
			$due_date = $date;
		}
		else
		{
			$date_arr = array();

			if($data_type=="json")
			{
        		foreach($shoppingcart->products as $product)
        		{
            		$date_arr[] = $product->date;
        		}
			}
			else
			{
				foreach($shoppingcart->shoppingcart_products()->get() as $shoppingcart_product)
				{
				$date_arr[] = $shoppingcart_product->date;
            	}
			}

			usort($date_arr, function($a, $b) {
            	$dateTimestamp1 = strtotime($a);
            	$dateTimestamp2 = strtotime($b);
            	return $dateTimestamp1 < $dateTimestamp2 ? -1: 1;
        	});

        	$due_date = $date_arr[0];
		}
		

		return $due_date;
	}

	

	

	

	public static function remove_activity($sessionId,$bookingId)
	{

		$contents = BokunHelper::get_removeactivity($sessionId,$bookingId);
		self::get_shoppingcart($sessionId,"update",$contents);
		return $sessionId;
	}

	public static function remove_promocode($sessionId)
	{
		VoucherHelper::remove_voucher($sessionId);
		return '';
	}

	public static function apply_promocode($sessionId,$promocode)
	{
		$status = VoucherHelper::apply_voucher($sessionId,$promocode);
		return $status;
	}

	public static function product_extend($product_id1=null,$product_id2=null,$shoppingcart)
	{
		$products = $shoppingcart->products;
		foreach($products as $product)
		{
			if($product->product_id==$product_id1)
			{
				if(!self::product_extend_check($product_id2,$shoppingcart->session_id))
				{
					$contents = BokunHelper::get_removeactivity($shoppingcart->session_id,$product->booking_id);
					$shoppingcart = self::get_shoppingcart($shoppingcart->session_id,"update",$contents);
				}
			}
		}
		return $shoppingcart;
	}

	public static function product_extend_check($product_id=null,$session_id)
	{
		$status = false;
		$shoppingcart = BookingHelper::read_shoppingcart($session_id);
        if($shoppingcart!=null)
        {
        	$products = $shoppingcart->products;
			foreach($products as $product)
			{
				if($product->product_id==$product_id)
				{
					$status = true;
				}
			}
        }
		return $status;
	}
	
	public static function text_rate($shoppingcart,$currency)
	{
			$value = '';
			$check = self::convert_currency($shoppingcart->due_now,$shoppingcart->currency,$currency);
			if($check>0)
			{
				$value = $shoppingcart->due_now / $check;
				if($currency!="IDR")
				{
					$value = number_format((float)$value, 2);
				}
				

				$value = '1 '. $currency .' = '. $value .' '. $shoppingcart->currency;
			}

			$amount = GeneralHelper::numberFormat(self::convert_currency($shoppingcart->due_now,$shoppingcart->currency,$currency),$currency);

			$text = $value .'<div class="mt-2"><span class="badge badge-success" style="font-size:12px;">Total : '. $amount .' '. $currency .'</span></div>';
		
			return $text;
	}
	

	public static function get_rate($shoppingcart)
	{
		$amount = $shoppingcart->due_now / $shoppingcart->shoppingcart_payment->amount;

		if($shoppingcart->shoppingcart_payment->rate_from=="IDR")
		{
			$amount = number_format((float)$amount, 2, '.', '');
		}
		
		$value = '1 '. $shoppingcart->shoppingcart_payment->rate_to .' = '. $amount .' '. $shoppingcart->shoppingcart_payment->rate_from;

		return $value;
	}

	public static function convert_currency($amount,$from,$to)
	{
		$rate_usd = BokunHelper::get_currency($from);
		$rate_usd_reserve = BokunHelper::get_currency($to);
		
		$rate = $rate_usd / $rate_usd_reserve;
		 
		$value = ($amount * $rate);
		
		//======================
		if($to=="USD")
		{
			if($value>0)
			{
				$rate_value = $amount / number_format((float)$value, 2, '.', '');
			
				//if($rate_value<=0) $rate_value = 1;
				$value = number_format((float)$amount / $rate_value, 2, '.', '');
				while( ShoppingcartPayment::where('amount',$value)->where('payment_status','4')->first() ){
					$rate_value += 0.01;
					$value = number_format((float)$amount / $rate_value, 2, '.', '');
        		}
			}
		}
		//======================
		
		$value = number_format((float)$value, 2, '.', '');

		//agar IDR tidak receh
		if($to=="IDR" && $from!="IDR")
		{
			$value=ceil($value);
			if (substr($value,-3)>499){
				$value=round($value,-3);
			} else {
				$value=round($value,-3)+1000;
			} 
            $value = number_format((float)$value, 2, '.', '');
		}

		return $value;
	}

	
	
	public static function get_count($table="shoppingcart")
	{
		$count = 0;
		if($table=="shoppingcart")
		{
			$count = Shoppingcart::whereYear('created_at',date('Y'))->whereMonth('created_at',date('m'))->count();
		}
		$count++;
		return $count;
	}

	public static function get_ticket(){
		$count = GeneralHelper::digitFormat(self::get_count('shoppingcart'),3);
		$uuid = config("site.ticket") . date('ymd') . $count;
		//$uuid = config("site.ticket") . date('ym') . GeneralHelper::digitFormat(rand(000,999),2) . $count;
        //while( Shoppingcart::where('confirmation_code','=',$uuid)->first() ){
            //$uuid = config("site.ticket") . date('ym') . GeneralHelper::digitFormat(rand(000,999),2) . $count;
        //}
        return $uuid;
	}
	
	public static function get_bookingStatus($shoppingcart)
	{
		$value = '';
		if($shoppingcart->booking_status=="CONFIRMED")
		{
			$value = '<span class="badge badge-success" style="font-size: 20px;">CONFIRMED</span>';
		}
		else if($shoppingcart->booking_status=="PENDING")
		{
			$value = '<span class="badge badge-info" style="font-size: 20px;">PENDING</span>';
		}
		else
		{
			$value = '<span class="badge badge-danger" style="font-size: 20px;">CANCELED</span>';
		}
		return $value;
	}

	public static function get_answer($shoppingcart,$question_id)
	{
		$value = '';
		foreach($shoppingcart->questions as $question)
        {
            if($question->question_id==$question_id)
            {
            	$value = $question->answer;
            }
        }
        return $value;
	}

	public static function get_answer_contact($shoppingcart)
	{
		$object = new \stdClass();
   		$object->firstName = '';
   		$object->lastName = '';
   		$object->email = '';
   		$object->phoneNumber = '';

   		$questions = $shoppingcart->shoppingcart_questions()->where('type','mainContactDetails')->get();
   		foreach($questions as $question)
   		{
   			if($question->question_id=="firstName") $object->firstName = $question->answer;
   			if($question->question_id=="lastName") $object->lastName = $question->answer;
   			if($question->question_id=="email") $object->email = $question->answer;
   			if($question->question_id=="phoneNumber") $object->phoneNumber = $question->answer;
   		}

   		return $object;
	}

	public static function get_answer_product($shoppingcart,$booking_id)
	{
		$value = '';

		foreach($shoppingcart->shoppingcart_questions()->where('when_to_ask','booking')->where('booking_id',$booking_id)->whereNotNull('label')->get() as $shoppingcart_question)
		{
			if($shoppingcart_question->answer!="")
			{
				$value .= '<b>'. $shoppingcart_question->label .'</b><br />'. $shoppingcart_question->answer .'<br />';
			}
			
		}

		$participants = $shoppingcart->shoppingcart_questions()->where('when_to_ask','participant')->where('booking_id',$booking_id)->select('participant_number')->groupBy('participant_number')->get();
		
		foreach($participants as $participant)
		{
			$value .= '<b>Participant '. $participant->participant_number .'</b><br />';
			foreach($shoppingcart->shoppingcart_questions()->where('when_to_ask','participant')->where('booking_id',$booking_id)->where('participant_number',$participant->participant_number)->get() as $participant_detail)
			{
				if($shoppingcart_question->answer!="")
				{
					$value .= '<b>'.$participant_detail->label .'</b><br /><span class="text-muted">'. $participant_detail->answer .'</span><br />';
				}
				
			}
		}
		
		if($value!="")
		{
			$value = '<div class="card mb-2 mt-2"><div class="card-body bg-light"><i>Additional Information</i><br />'. nl2br($value) .'</div></div>';
		}
		
        return $value;
	}

	public static function access_ticket($shoppingcart)
	{
		$access = false;
		if($shoppingcart->booking_status=="CONFIRMED")
		{
			$access = true;
			if(isset($shoppingcart->shoppingcart_payment->payment_status))
			{
				$payment_status = $shoppingcart->shoppingcart_payment->payment_status;
				if($payment_status == 3 || $payment_status == 4)
				{
					$access = false;
				}
			}
		}
		return $access;
	}

	public static function generate_qrcode($shoppingcart)
	{
			$qrcode = QrCode::errorCorrection('H')->format('png')->margin(0)->size(630)->generate($shoppingcart->shoppingcart_payment->qrcode);
			return 'data:image/png;base64, '. base64_encode($qrcode);
	}

	public static function create_manual_pdf($shoppingcart)
	{
        $pdf = PDF::setOptions(['tempDir' =>  storage_path(),'fontDir' => storage_path(),'fontCache' => storage_path(),'isRemoteEnabled' => true])->loadView('vertikaltrip::layouts.manual.manual', compact('shoppingcart'))->setPaper('a4', 'portrait');
        return $pdf;
	}

	public static function create_invoice_pdf($shoppingcart)
	{
		$path = config('site.assets') .'/img/pdf/qrcode-logo.png';
		$qrcode = base64_encode(QrCode::errorCorrection('H')->format('png')->merge($path,1,false)->size(1024)->margin(0)->generate(env('APP_API_URL') .'/pdf/invoice/'.$shoppingcart->session_id.'/Invoice-'.$shoppingcart->confirmation_code.'.pdf'  ));
		$main_contact = self::get_answer_contact($shoppingcart);
        $pdf = PDF::setOptions(['tempDir' =>  storage_path(),'fontDir' => storage_path(),'fontCache' => storage_path(),'isRemoteEnabled' => true])->loadView('vertikaltrip::layouts.pdf.invoice', compact('shoppingcart','qrcode'))->setPaper('a4', 'portrait');
        if($shoppingcart->booking_channel=="WEBSITE")
        {
        	if($main_contact->email!="")
        	{
        		$pdf->setEncryption($main_contact->email);
        	}
        }
        
        return $pdf;
	}

	public static function create_instruction_pdf($shoppingcart)
	{
		$customPaper = array(0,0,430,2032);
		$pdf = PDF::setOptions(['tempDir' => storage_path(),'fontDir' => storage_path(),'fontCache' => storage_path(),'isRemoteEnabled' => true])->loadView('vertikaltrip::layouts.manual.bank_transfer', compact('shoppingcart'))->setPaper($customPaper,'portrait');
		return $pdf;
	}

	public static function create_ticket_pdf($shoppingcart_product)
	{
		$customPaper = array(0,0,300,540);
		$path = config('site.assets') .'/img/pdf/qrcode-logo.png';
        $qrcode = base64_encode(QrCode::errorCorrection('H')->format('png')->merge($path,1,false)->size(1024)->margin(0)->generate($shoppingcart_product->shoppingcart->url .'/booking/receipt/'.$shoppingcart_product->shoppingcart->session_id.'/'.$shoppingcart_product->shoppingcart->confirmation_code  ));
        $pdf = PDF::setOptions(['tempDir' => storage_path(),'fontDir' => storage_path(),'fontCache' => storage_path(),'isRemoteEnabled' => true])->loadView('vertikaltrip::layouts.pdf.ticket', compact('shoppingcart_product','qrcode'))->setPaper($customPaper);
        return $pdf;
	}

	public static function save_trackingCode($sessionId,$trackingCode)
	{
		if($trackingCode==null) $trackingCode = null;
		if($trackingCode=="") $trackingCode = null;
		if($trackingCode=="null") $trackingCode = null;
		$shoppingcart = BookingHelper::read_shoppingcart($sessionId);
		$shoppingcart->referer = $trackingCode;
		BookingHelper::save_shoppingcart($sessionId, $shoppingcart);
	}

	public static function get_booking_expired()
	{
		$shoppingcarts = Shoppingcart::where('booking_status','PENDING')->get();
        foreach($shoppingcarts as $shoppingcart)
        {
        	BookingHelper::booking_expired($shoppingcart);
        }
	}

	public static function booking_expired($shoppingcart)
	{
		if($shoppingcart->booking_status=="PENDING")
        {
            $due_date = self::due_date($shoppingcart,"database");
            if(Carbon::parse($due_date)->isPast())
            {
                $shoppingcart->booking_status = "CANCELED";
                $shoppingcart->save();
                $shoppingcart->shoppingcart_payment->payment_status = 3;
                $shoppingcart->shoppingcart_payment->save();
            }
        }
	}

	public static function schedule_bydate($date)
	{
		$text = "Today's Participant \n\n";

		$products = ShoppingcartProduct::whereHas('shoppingcart', function ($query) {
                    return $query->where('booking_status','CONFIRMED');
                 })->whereDate('date', '=', $date)->whereNotNull('date')->groupBy('product_id')->select(['product_id'])->get();
		$total = 0;
        foreach($products as $product)
        {
            $product_name = ProductHelper::product_name_by_bokun_id($product->product_id);
            $text .= "*". $product_name ."* \n";
            //print_r($product_name ."<br />");
            $schedule = ShoppingcartProduct::with(['shoppingcart' => function ($query) {
                    return $query->with(['shoppingcart_questions' => function ($query) {
                        return $query->where('question_id','firstName')->orWhere('question_id','lastName');
                    }]);
                }])
                 ->whereHas('shoppingcart', function ($query) {
                    return $query->where('booking_status','CONFIRMED');
                 })->whereDate('date', '=', $date)->where('product_id',$product->product_id)->whereNotNull('date')->get();
            foreach($schedule as $id)
            {
                $question = BookingHelper::get_answer_contact($id->shoppingcart);
                $people = 0;
                foreach($id->shoppingcart_product_details as $shoppingcart_product_detail)
                {
                    $people += $shoppingcart_product_detail->people;
                    $total += $people;
                }
                $text .= "- ". GeneralHelper::mask_name($question->firstName) ." _(".$people." pax)_ \n";
                //print_r("-". $question->firstName ." (".$people." people) <br />");
            }
            $text .= "\n";
            //print_r("<br />");
        }

        if($total==0)
        {
        	$text = "There is no participant for today";
        }
        return $text;
	}

}
?>