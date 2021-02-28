@inject('ProductHelper', budisteikul\toursdk\Helpers\ProductHelper)
@inject('BookingHelper', budisteikul\toursdk\Helpers\BookingHelper)
@extends('vertikaltrip::layouts.app')
@section('title','Checkout')
@push('scripts')
 <script
    src="https://www.paypal.com/sdk/js?client-id={{ env('PAYPAL_CLIENT_ID') }}&intent=authorize&currency={{ env('PAYPAL_CURRENCY') }}"  data-csp-nonce="xyz-123">
</script>
<script>
$( document ).ready(function() {
    $('#proses').hide();
});
</script>
@endpush
@section('content')
@include('vertikaltrip::layouts.loading')
<section id="booking" style="background-color:#ffffff">
<div class="container">
	<div class="row">
		<div class="col-lg-12 col-md-12 mx-auto">
			<div class="row" style="padding-bottom:0px;">
				<div class="col-lg-12 text-left">
				<div style="height:56px;"></div>
            	<div class="row mb-2">  
				<div class="col-lg-6 col-lg-auto mb-6 mt-4">
                
<!-- ################################################################### -->  

                <div class="card shadow">
  				<div class="card-header bg-dark text-white pb-1">
    				<h4><i class="fas fa-shopping-cart"></i> Order Summary</h4>
  				</div>

                <div class="card-body">
                <p>
                        <h2>Contact Information</h2>
                        @php
                        $main_contacts = $shoppingcart->shoppingcart_questions()->where('type','mainContactDetails')->orderBy('order')->get()
                        @endphp
                        @foreach($main_contacts as $main_contact)        
                            <strong>{{ $main_contact->label }} : </strong>
                            {{ $main_contact->answer }} <br />
                        @endforeach
                    </p>
                    <hr>
                </div>


                <?php
				$grand_subtotal = 0;
				$grand_discount = 0;
				$grand_total = 0;
				?>
                @foreach($shoppingcart->shoppingcart_products()->get() as $shoppingcart_product)
                <!-- Product booking -->
                <div class="card-body">
                            <!-- Product detail booking -->
							<div class="row mb-4">
                				<div class="col-8">
                    				<b>{{ $shoppingcart_product->title }}</b>
                    			</div>
                    			<div class="col-4 text-right">
                                	<?php
									$product_subtotal = 0;
									$product_discount = 0;
									$product_total = 0;
									foreach($shoppingcart_product->shoppingcart_rates()->where('type','product')->get() as $shoppingcart_rates)
									{
										$product_subtotal += $shoppingcart_rates->subtotal;
										$product_discount += $shoppingcart_rates->discount;
										$product_total += $shoppingcart_rates->total;
									}
									?>
                                    @if($product_discount>0)
                                    	<strike class="text-muted">{{ $product_subtotal }}</strike><br><b>{{ $product_total }}</b>
                                    @else
                    					<b>{{ $product_total }}</b>
                    				@endif
                                </div>
                			 </div>
                    
                    		 <div class="row mb-4">
                             <div class="col-10 row">
                				<div class="ml-4 mb-2">
                               		@if(isset($shoppingcart_product->image))
                    				<img class="img-fluid" width="55" src="{{ $shoppingcart_product->image }}">
                                	@endif
                    			</div>
                    			<div class="col-8 product-detail">
                                	{{ $ProductHelper->datetotext($shoppingcart_product->date) }}
                                	<br>
                                    {{ $shoppingcart_product->rate }}
                                    <br>
                                    @foreach($shoppingcart_product->shoppingcart_rates()->where('type','product')->get() as $shoppingcart_rates)
                                    	
                                        	{{ $shoppingcart_rates->qty }} x {{ $shoppingcart_rates->unitPrice }} ({{ $shoppingcart_rates->price }})
                                    	
                                        <br>
                                    @endforeach
                                </div>
                			</div>
                            
                            </div>
                            <!-- Product detail booking -->
                            <!-- Pickup booking $activity -->
                            @php
							$pickups = $shoppingcart_product->shoppingcart_rates()->where('type','pickup')->get();
                            @endphp
                            @if(count($pickups))
                            <div class="card mb-2">
                        		<div class="card-body">
                               		@foreach($pickups as $shopppingcart_rates)
									<div class="row mb-2">
                						<div class="col-8">
                                        <strong style="font-size:12px;">Pick-up and drop-off services</strong>
                                        <br>
                                        <span style="font-size:12px;">{{ $shopppingcart_rates->unitPrice }}</span>
                    					</div>
                    					<div class="col-4 text-right">
                    						@if($shopppingcart_rates->discount > 0)
                                            	<strike class="text-muted">{{ $shopppingcart_rates->subtotal }}</strike><br><b>{{ $shopppingcart_rates->total }}</b>
                                            @else
                                            	<b>{{ $shopppingcart_rates->subtotal }}</b>
                    						@endif
                                        </div>
                					</div>
                               		@endforeach
								</div>
                   			</div>
							@endif
                            <!-- Pickup booking $activity -->
							
                            <!-- Extra booking $activity -->
                            @php
                            $extra = $shoppingcart_product->shoppingcart_rates()->where('type','extra')->get();
                            @endphp
                            @if(count($extra))
							<div class="card mb-2">
                            
                        		<div class="card-body">
                                <div class="row col-12 mb-2">
                            		<strong>Extras</strong>
                            	</div>
                                @foreach($extra as $shoppingcart_rates)
									<div class="row mb-2">
                						<div class="col-8">
										{{ $shoppingcart_rates->title }}
                    					</div>
                    					<div class="col-4 text-right">
                                        	@if($shopppingcart_rates->discount > 0)
                                            	<strike class="text-muted">{{ $shopppingcart_rates->subtotal }}</strike><br><b>{{ $shopppingcart_rates->total }}</b>
                                            @else
                    							<b>{{ $shoppingcart_rates->subtotal }}</b>
                                            @endif
                    					</div>
                					</div>
                               @endforeach
								</div>
                   			</div>
							<!-- Extra booking -->
                            @endif
                            
				</div>
                <!-- Product booking -->
                <?php
				$grand_subtotal += $shoppingcart_product->subtotal;
				$grand_discount += $shoppingcart_product->discount;
				$grand_total += $shoppingcart_product->total;
				?>
                
                @endforeach
                <div class="card-body pt-0 mt-0">
                	<hr>
                	<div class="row mb-2">
                		<div class="col-8">
                    		<span style="font-size:18px">Items</span>
                    	</div>
                    	<div class="col-4 text-right">
                    		<span style="font-size:18px">{{ $grand_subtotal }}</span>
                    	</div>
                	</div>
                    @if($grand_discount>0)
                    <div class="row mb-2">
                		<div class="col-8">
                    		<span style="font-size:18px">Discount</span>
                    	</div>
                    	<div class="col-4 text-right">
                    		<span style="font-size:18px">{{ $grand_discount }}</span>
                    	</div>
                	</div>
                    @endif
				</div>
                
                <div class="card-body pt-0">
                	<hr class="mt-0"> 
                    <div class="row mt-0">
                		<div class="col-8">
                    		<b style="font-size:18px">Total ({{ $shoppingcart->currency }})</b>
                    	</div>
                    	<div class="col-4 text-right">
                    	<b style="font-size:18px">{{ $grand_total }}</b>
                    	</div>
                	</div>
                </div>

                
                    
				</div>
           
<!-- ################################################################### -->
            </div>
            
            <div class="col-lg-6 col-lg-auto mb-6 mt-4">
            <div class="card mb-8 shadow p-2">
 				 <div class="card-body" style="padding-left:10px;padding-right:10px;padding-top:10px;padding-bottom:15px;">
                 
<form onSubmit="STORE(); return false;">             
<!-- ########################################### -->
<h2>Main Contact</h2>   
	@php
    	$main_contacts = $shoppingcart->shoppingcart_questions()->where('type','mainContactDetails')->orderBy('order')->get()
    @endphp
    @foreach($main_contacts as $main_contact)        
<div class="form-group">
	<label for="{{ $main_contact->id }}" class="{{ $main_contact->required ? "required" : "" }}"><strong>{{ $main_contact->label }}</strong></label>
    
    {{ $main_contact->answer }}
   
</div>
	@endforeach
<!-- ########################################### --> 
    @foreach($shoppingcart->shoppingcart_products()->get() as $shoppingcart_products)
    @php
    	$activityBookings = $shoppingcart->shoppingcart_questions()->where('booking_id',$shoppingcart_products->booking_id)->whereNotNull('booking_id')->orderBy('order')->get();
    @endphp
    @if(count($activityBookings))
    <h2>{{ $shoppingcart_products->title }}</h2>
    
    @foreach($activityBookings as $activityBooking)
    	<div class="form-group">
		<label for="{{ $activityBooking->id }}" class="{{ $activityBooking->required ? "required" : "" }}"><strong>{{ $activityBooking->label }}</strong></label>
    	
    	{{ $activityBooking->answer }}
    	
   
	</div>
    @endforeach
    @endif
    @endforeach
<!-- ########################################### -->    


<button id="submit" type="submit" style="height:47px;" class="btn btn-lg btn-block btn-theme"><i class="fas fa-lock"></i> <strong>Pay {{ $shoppingcart->shoppingcart_payment->currency }} {{ $shoppingcart->shoppingcart_payment->amount }}</strong></button>
</form>

<div id="payment-container">
    
</div>


<div id="alert-payment"></div>

<div id="notice">
    
    @if($shoppingcart->currency!=$shoppingcart->shoppingcart_payment->currency)
    <i>
    <br />
    All payment will be made in {{ $shoppingcart->shoppingcart_payment->currency }}
    <br />
    Rate : {{ $BookingHelper->get_rate($shoppingcart) }}
    </i>
    @endif

</div>

			</div>
            </div>
            </div>
        	</div>
				<div style="height:40px;"></div>		
				</div>
			</div>
        </div>
	</div>
</div>
</section>
<script>
@php
$questions = $shoppingcart->shoppingcart_questions()->where('required',1)->get()
@endphp
    @foreach($questions as $question)
	$("#{{ $question->id }}").focusout(function() {
		$('#{{ $question->id }}').removeClass('is-invalid');
  		$('#span-{{ $question->id }}').remove();
    	if($("#{{ $question->id }}").val()=="")
		{
			$('#{{ $question->id }}').addClass('is-invalid');
			$('#{{ $question->id }}').after('<span id="span-{{ $question->id }}" class="invalid-feedback" role="alert"><strong>Please fill out this field.</strong></span>');
		}
		else
		{
			@if($question->data_format=="EMAIL_ADDRESS")
				var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
				if(regex.test($("#{{ $question->id }}").val()))
				{
					$('#{{ $question->id }}').removeClass('is-invalid');
  					$('#span-{{ $question->id }}').remove();
				}
				else
				{
					$('#{{ $question->id }}').addClass('is-invalid');
					$('#{{ $question->id }}').after('<span id="span-{{ $question->id }}" class="invalid-feedback" role="alert"><strong>Email format not valid.</strong></span>');
				}
			@else
				$('#{{ $question->id }}').removeClass('is-invalid');
  				$('#span-{{ $question->id }}').remove();
			@endif
		}
		
  	});
	@endforeach


</script>
<script language="javascript">
function STORE()
{
	var error = false;
	$("#submit").attr("disabled", true);
	$('#submit').html('<i class="fa fa-spinner fa-spin"></i>');
	var input = [
				
				@php
    			$main_contacts = $shoppingcart->shoppingcart_questions()->where('type','mainContactDetails')->orderBy('order')->get()
    			@endphp
    			@foreach($main_contacts as $main_contact)
					"{{ $main_contact->id }}",
				@endforeach
				
				@php
    			$activityBookings = $shoppingcart->shoppingcart_questions()->where('type','activityBookings')->orderBy('order')->get();
    			@endphp
				@if(count($activityBookings))
    				@foreach($activityBookings as $activityBooking)
						"{{ $activityBooking->id }}",
					@endforeach
				@endif
				@php
    			$pickup_questions = $shoppingcart->shoppingcart_questions()->where('type','pickupQuestions')->orderBy('order')->get();
    			@endphp
    			@if(count($pickup_questions))
					@foreach($pickup_questions as $pickup_question)
					"{{ $pickup_question->id }}",
					@endforeach
				@endif
				
	];
	
	$.each(input, function( index, value ) {
  		$('#'+ value).removeClass('is-invalid');
  		$('#span-'+ value).remove();
	});
	
	
	$.ajax({
		data: {
        	"_token": $("meta[name=csrf-token]").attr("content"),
            "sessionId": '{{$shoppingcart->session_id}}',
			
				@php
    			$main_contacts = $shoppingcart->shoppingcart_questions()->where('type','mainContactDetails')->orderBy('order')->get()
    			@endphp
    			@foreach($main_contacts as $main_contact)
					"{{ $main_contact->id }}": $('#{{ $main_contact->id }}').val(),
				@endforeach
				
				@php
    			$activityBookings = $shoppingcart->shoppingcart_questions()->where('type','activityBookings')->orderBy('order')->get();
    			@endphp
				@if(count($activityBookings))
    				@foreach($activityBookings as $activityBooking)
						"{{ $activityBooking->id }}": $('#{{ $activityBooking->id }}').val(),
					@endforeach
				@endif
				@php
    			$pickup_questions = $shoppingcart->shoppingcart_questions()->where('type','pickupQuestions')->orderBy('order')->get();
    			@endphp
    			@if(count($pickup_questions))
					@foreach($pickup_questions as $pickup_question)
					"{{ $pickup_question->id }}": $('#{{ $pickup_question->id }}').val(),
					@endforeach
				@endif
			
        },
		type: 'POST',
		url: '/snippets/shoppingcart/checkout'
		}).done(function( data ) {
			
			if(data.id=="1")
			{
				$("#apply").attr("disabled", true);
				$("#promocode").attr("disabled", true);

				@php
				$bookingId_buttons = $shoppingcart->shoppingcart_products()->get();
				@endphp
				@foreach($bookingId_buttons as $bookingId_button)
					$("#remove-{{ $bookingId_button->booking_id }}").attr("disabled", true);
				@endforeach

				@php
    			$main_contacts = $shoppingcart->shoppingcart_questions()->where('type','mainContactDetails')->orderBy('order')->get()
    			@endphp
    			@foreach($main_contacts as $main_contact)
					$("#{{ $main_contact->id }}").attr("disabled", true);
					$("#{{ $main_contact->id }}").addClass("input-disabled");
				@endforeach
				
				@php
    			$activityBookings = $shoppingcart->shoppingcart_questions()->where('type','activityBookings')->orderBy('order')->get();
    			@endphp
				@if(count($activityBookings))
    				@foreach($activityBookings as $activityBooking)
						$("#{{ $activityBooking->id }}").attr("disabled", true);
						$("#{{ $activityBooking->id }}").addClass("input-disabled");
					@endforeach
				@endif
				@php
    			$pickup_questions = $shoppingcart->shoppingcart_questions()->where('type','pickupQuestions')->orderBy('order')->get();
    			@endphp
    			@if(count($pickup_questions))
					@foreach($pickup_questions as $pickup_question)
					$("#{{ $pickup_question->id }}").attr("disabled", true);
					$("#{{ $pickup_question->id }}").addClass("input-disabled");
					@endforeach
				@endif
				
				
				
				$("#submit").slideUp("slow");
                $('#payment-container').html('<div id="proses"><h2>Pay with</h2><div id="paypal-button-container"></div></div>');


                $('#payment-container').fadeIn("slow");
				$("#proses").fadeIn("slow");
                
				//=========================================================
				paypal.Buttons({
    			createOrder: function() {
					
  					return fetch('/snippets/payment/paypal', {
    				method: 'POST',
					credentials: 'same-origin',
    				headers: {
      					'content-type': 'application/json',
						'X-CSRF-TOKEN': $("meta[name=csrf-token]").attr("content"),
                        'sessionId': '{{ $shoppingcart->session_id }}'
    					}
  					}).then(function(res) {
						//console.log(res);
    					return res.json();
  					}).then(function(data) {
						//console.log(data);
    					return data.result.id;
  					});
					
				},
				onError: function (err) {
    				$("#proses").hide();
					$('#alert-payment').html('<div id="alert-failed" class="alert alert-danger text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Error!</h2></div>');
                                $('#alert-payment').fadeIn("slow");
					
  				},
   				onApprove: function(data, actions) {
					$("#proses").addClass("loader");
      				actions.order.authorize().then(function(authorization) {
        				var authorizationID = authorization.purchase_units[0].payments.authorizations[0].id
						$.ajax({
							data: {
        						"_token": $("meta[name=csrf-token]").attr("content"),
								"orderID": data.orderID,
								"authorizationID": authorizationID,
                                "sessionId": '{{ $shoppingcart->session_id }}',
        						},
							type: 'POST',
							url: '/snippets/payment/paypal/confirm'
						}).done(function(data) {
							if(data.id=="1")
							{
								window.location.href = '/booking/receipt/'+ data.message;
								$("#proses").hide();
                                $('#alert-payment').html('<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>');
                                $('#alert-payment').fadeIn("slow");
								
							}
							else
							{
								$("#proses").hide();
                                $('#alert-payment').html('<div id="alert-failed" class="alert alert-danger text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Failed!</h2></div>');
                                $('#alert-payment').fadeIn("slow");
							}
						}).fail(function(error) {
							console.log(error);
						});
      				});
    			}
			
  				}).render('#paypal-button-container');
				//=========================================================

				//=========================================================
			}
			else
			{
				$.each( data, function( index, value ) {
					$('#'+ index).addClass('is-invalid');
						if(value!="")
						{
							$('#'+ index).after('<span id="span-'+ index  +'" class="invalid-feedback" role="alert"><strong>'+ value +'</strong></span>');
						}
					});
					
				$("#submit").attr("disabled", false);
				$('#submit').html('<i class="fas fa-lock"></i> <strong>Pay {{ $shoppingcart->currency }} {{ $shoppingcart->total }}</strong>');
				
			}
		});
	
	
	return false;
}
</script>

@endsection