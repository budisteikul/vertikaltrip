@inject('ProductHelper', 'budisteikul\toursdk\Helpers\ProductHelper')
@inject('BookingHelper', 'budisteikul\toursdk\Helpers\BookingHelper')
@inject('GeneralHelper', 'budisteikul\coresdk\Helpers\GeneralHelper')
@extends('vertikaltrip::layouts.app')
@section('title','Checkout')
@push('scripts')
 <script
    src="https://www.paypal.com/sdk/js?client-id={{ env('PAYPAL_CLIENT_ID') }}&intent=authorize&currency={{ env('PAYPAL_CURRENCY') }}"  data-csp-nonce="xyz-123">
</script>
<script>
$(document).ready(function() {
    
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
<script language="javascript">
function REMOVE(id)
{
    $('#remove-'+id).attr("disabled", true);
    $('#remove-'+id).html('<i class="fa fa-spinner fa-spin"></i>');
    
    $.ajax({
        data: {
            "_token": $("meta[name=csrf-token]").attr("content"),
            "bookingId": id,
            "sessionId": '{{$shoppingcart->session_id}}',
        },
        type: 'POST',
        url: '/snippets/activity/remove'
        }).done(function( data ) {
            if(data.id=="1")
            {
                window.location.href = '/booking/checkout';
            }
            else
            {
                $('#remove-'+id).attr("disabled", false);
                $('#remove-'+id).html('<i class="fa fa-trash-alt"></i>');
            }
        });
    
    
    return false;
}
</script>
                <div class="card shadow">
  				<div class="card-header bg-dark text-white pb-1">
    				<h4><i class="fas fa-shopping-cart"></i> Order Summary</h4>
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
                                    	<strike class="text-muted">{{ $GeneralHelper->numberFormat($product_subtotal) }}</strike><br><b>{{ $GeneralHelper->numberFormat($product_total) }}</b>
                                    @else
                    					<b>{{ $GeneralHelper->numberFormat($product_total) }}</b>
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
                                    	
                                        	{{ $shoppingcart_rates->qty }} x {{ $shoppingcart_rates->unit_price }} ({{ $GeneralHelper->numberFormat($shoppingcart_rates->price) }})
                                    	
                                        <br>
                                    @endforeach
                                </div>
                			</div>
                            <div class="col text-right">
                            	<button id="remove-{{ $shoppingcart_product->booking_id }}" onClick="REMOVE({{ $shoppingcart_product->booking_id }});" class="btn btn-sm btn-danger"><i class="fa fa-trash-alt fa-sm"></i></button>
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
                                            	<strike class="text-muted">{{ $GeneralHelper->numberFormat($shopppingcart_rates->subtotal) }}</strike><br><b>{{ $GeneralHelper->numberFormat($shopppingcart_rates->total) }}</b>
                                            @else
                                            	<b>{{ $GeneralHelper->numberFormat($shopppingcart_rates->subtotal) }}</b>
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
										&#9642; {{ $shoppingcart_rates->qty }} {{ $shoppingcart_rates->unit_price }}
                    					</div>
                    					<div class="col-4 text-right">
                                        	@if($shoppingcart_rates->discount > 0)
                                            	<strike class="text-muted">{{ $GeneralHelper->numberFormat($shoppingcart_rates->subtotal) }}</strike><br><b>{{ $GeneralHelper->numberFormat($shoppingcart_rates->total) }}</b>
                                            @else
                    							<b>{{ $GeneralHelper->numberFormat($shoppingcart_rates->subtotal) }}</b>
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
                    		<span style="font-size:16px">Items</span>
                    	</div>
                    	<div class="col-4 text-right">
                    		<span style="font-size:16px">{{ $GeneralHelper->numberFormat($grand_subtotal) }}</span>
                    	</div>
                	</div>
                    @if($grand_discount>0)
                    <div class="row mb-2">
                		<div class="col-8">
                    		<span style="font-size:16px">Discount</span>
                    	</div>
                    	<div class="col-4 text-right">
                    		<span style="font-size:16px">{{ $GeneralHelper->numberFormat($grand_discount) }}</span>
                    	</div>
                	</div>
                    @endif
                    <div class="row mb-2">
                        <div class="col-8">
                            <b style="font-size:16px">Total ({{ $shoppingcart->currency }})</b>
                        </div>
                        <div class="col-4 text-right">
                            <b style="font-size:16px">{{ $GeneralHelper->numberFormat($grand_total) }}</b>
                        </div>
                    </div>
				</div>
                
                @if($shoppingcart->due_on_arrival>0)
                <div class="card-body pt-0">
                	<hr class="mt-0"> 
                    <div class="row mb-2 mt-0">
                		<div class="col-8">
                    		<b style="font-size:16px">Biaya booking ({{ $shoppingcart->currency }})</b>
                    	</div>
                    	<div class="col-4 text-right">
                    	   <b style="font-size:16px">{{ $GeneralHelper->numberFormat($shoppingcart->due_now) }}</b>
                    	</div>
                	</div>
                    <div class="row mb-4 mt-0">
                        <div class="col-8">
                            <span style="font-size:16px">Biaya pelunasan  ({{ $shoppingcart->currency }})</span>
                        </div>
                        <div class="col-4 text-right">
                            <span style="font-size:16px">{{ $GeneralHelper->numberFormat($shoppingcart->due_on_arrival) }}</span>
                        </div>
                    </div>
                    <span style="color: red">*</span> biaya pelunasan bisa dilakukan dilokasi acara
                </div>
                @endif

				</div>
<!-- ################################################################### -->
@if(!isset($shoppingcart->promo_code))
<script language="javascript">
function PROMOCODE()
{
    $('#alert-promocode-success').fadeOut("slow");
    $('#alert-promocode-failed').fadeOut("slow");
    $("#apply").attr("disabled", true);
    $("#promocode").attr("disabled", true);
    $('#apply').html('<i class="fa fa-spinner fa-spin"></i>');
    
    $.ajax({
        data: {
            "_token": $("meta[name=csrf-token]").attr("content"),
            "promocode": $('#promocode').val(),
            "sessionId": '{{$shoppingcart->session_id}}',
        },
        type: 'POST',
        url: '/snippets/promocode'
        }).done(function( data ) {
            if(data.id=="1")
            {
                window.location.href = '/booking/checkout';
                $('#alert-promocode').hide();
                $('#alert-promocode').html('<div id="alert-promocode-success" class="alert alert-primary text-center" role="alert"><i class="far fa-smile"></i> Promo code applied</div>');
                $('#alert-promocode').fadeIn("slow");
            }
            else
            {
                $('#promocode').val('');
                $('#alert-promocode').hide();
                $('#alert-promocode').html('<div id="alert-promocode-failed" class="alert alert-danger text-center" role="alert"><i class="far fa-frown"></i> Promo code not valid</div>');
                $('#alert-promocode').fadeIn("slow");
                $("#promocode").attr("disabled", false);
                $("#apply").attr("disabled", false);
                $('#apply').html('Apply');
            }
        });
    return false;
}
</script>
<!-- ################################################################### -->
                <div class="card shadow mt-4">
                	<div class="card-body">
                            <div id="alert-promocode"></div>
                    		
                            
                    	<form onSubmit="PROMOCODE(); return false;" class="form-inline">
  							<div class="form-row align-items-center">
    							<div class="col-auto">
      								<input type="text" class="form-control" id="promocode" placeholder="Promo code" required>
    							</div>
    							<div class="col-auto">
      								<button id="apply" type="submit" class="btn btn-secondary ">Apply</button>
    							</div>
  							</div>
						</form>
                	</div>
                </div>
 <!-- ################################################################### --> 
 @else
 <script>
$( document ).ready(function() {
	$('#alert-promocode-failed').hide();
});
</script>
<script language="javascript">
function DELETE()
{
    $("#apply").attr("disabled", true);
    $('#apply').html('<i class="fa fa-spinner fa-spin"></i>');
    
    $.ajax({
        data: {
            "_token": $("meta[name=csrf-token]").attr("content"),
            "sessionId": '{{$shoppingcart->session_id}}',
        },
        type: 'POST',
        url: '/snippets/promocode/remove'
        }).done(function( data ) {
            if(data.id=="1")
            {
                window.location.href = '/booking/checkout';
                $('#alert-promocode').hide();
                $('#alert-promocode').html('<div id="alert-promocode-failed" class="alert alert-danger text-center" role="alert"><i class="far fa-frown"></i> Promo code removed</div>');
                $('#alert-promocode').fadeIn("slow");
            }
        });
    
    
    return false;
}
</script>
<div class="card shadow mt-4">
	<div class="card-body">
    		<div id="alert-promocode"></div>
    	<div class="row mb-2">
        	<div class="col-8 my-auto">
				<strong>Promo code : {{ $shoppingcart->promo_code }}</strong>
			</div>
			<div class="col-4 my-auto text-right">
				<button id="apply" type="button" onClick="DELETE();" class="btn btn-sm btn-danger"><i class="fa fa-trash-alt"></i></button>
			</div>
		</div>	
	</div>
</div>
@endif         
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
    @if($main_contact->data_format=="EMAIL_ADDRESS")
	<input name="{{ $main_contact->id }}" value="{{ $main_contact->answer }}" type="email" class="form-control" id="{{ $main_contact->id }}" style="height:47px;" {{ $main_contact->required ? "required" : "" }}>
    @elseif($main_contact->data_format=="PHONE_NUMBER")
    <input name="{{ $main_contact->id }}" value="{{ $main_contact->answer }}" type="tel" class="form-control" id="{{ $main_contact->id }}" style="height:47px;" {{ $main_contact->required ? "required" : "" }}>
    @else
    @if($main_contact->select_option)
    <select style="font-size:16px;height:47px;"  class="form-control" id="{{ $main_contact->id }}" name="{{ $main_contact->id }}" {{ $main_contact->required ? "required" : "" }}>
    	<option value=""></option>
    	@foreach($main_contact->shoppingcart_question_options()->orderBy('order')->get() as $shoppingcart_question_option)
    	<option value="{{ $shoppingcart_question_option->value }}" {{ $shoppingcart_question_option->answer==1 ? "selected" : "" }}>{{ $shoppingcart_question_option->label }}</option>
        @endforeach
    </select>
    @else
    <input name="{{ $main_contact->id }}" value="{{ $main_contact->answer }}" type="text" class="form-control" id="{{ $main_contact->id }}" style="height:47px;" {{ $main_contact->required ? "required" : "" }}>
    @endif
    @endif
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
    	@if($activityBooking->select_option)
    	<select style="font-size:16px;height:47px;" class="form-control" id="{{ $activityBooking->id }}" name="{{ $activityBooking->id }}" {{ $activityBooking->required ? "required" : "" }}>
    		<option value=""></option>
    		@foreach($activityBooking->shoppingcart_question_options()->orderBy('order')->get() as $shoppingcart_question_option)
    		<option value="{{ $shoppingcart_question_option->value }}" {{ $shoppingcart_question_option->answer==1 ? "selected" : "" }}>{{ $shoppingcart_question_option->label }}</option>
    	    @endforeach
    	</select>
    	@else
    	<input type="text" id="{{ $activityBooking->id }}" value="{{ $activityBooking->answer }}" style="height:47px;" name="{{ $activityBooking->id }}" class="form-control" {{ $activityBooking->required ? "required" : "" }}>
    	@endif
    @if(isset($activityBooking->help))
    <small class="form-text text-muted">{{$activityBooking->help}}</small>
    @endif
	</div>
    @endforeach
    @endif
    @endforeach
<!-- ########################################### -->

<h2>Billing Information</h2>

<div class="form-group">

<h3>Payment provider</h3>

<div class="card bg-light mb-2">
  <div class="card-body pl-3">
        <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_provider" id="payment_midtrans" value="midtrans" checked>
            <label class="form-check-label ml-2" for="payment_midtrans">
                <h5 class="mb-1">MidTrans</h5>
                <small class="form-text text-muted mb-1">Recommended for local payment</small>
                <img src="/img/midtrans.png" height="50">
            </label>
        </div>
  </div>
</div>

<div class="card bg-light mb-2">
  <div class="card-body pl-3">
        <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_provider" id="payment_paypal" value="paypal">
            <label class="form-check-label ml-2" for="payment_paypal">
                <h5 class="mb-1">PayPal</h5>
                <small class="form-text text-muted mb-1">Recommended for international payment</small>
                <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/cc-badges-ppmcvdam.png" height="30">
                <br />
                <small class="form-text text-muted">Charge in USD, Rate : {{ $BookingHelper->paypal_rate($shoppingcart) }}</small>
            </label>
        </div>
  </div>
</div>




<div class="form-check mt-3">
  <input class="form-check-input" type="checkbox" value="" id="term">
  <label class="form-check-label" for="term">
    I agree with the <a class="text-theme" href="/page/terms-and-conditions" target="_blank">terms and conditions</a>.
  </label>
</div>




</div>  

<!-- ########################################### --> 

<button id="submit" type="submit" style="height:47px;" class="btn btn-lg btn-block btn-theme" disabled="true"><i class="fas fa-lock"></i> <strong>Checkout</strong></button>
</form>

<div id="payment-container">
    
</div>


<div id="alert-payment"></div>

<div id="notice">
    
    

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
$('#term').click(function() {
    if($("#term").is(':checked'))
    {
        $("#submit").attr("disabled", false);
    }
    else
    {
        $("#submit").attr("disabled", true);
    }
});

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
                $("#term").attr("disabled", true);
				$("#apply").attr("disabled", true);
				$("#promocode").attr("disabled", true);
                $("#payment_paypal").attr("disabled", true);
                $("#payment_midtrans").attr("disabled", true);

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
				
				
				
				
                
                if($('input[name="payment_provider"]:checked').val()=="paypal")
                {
                    $('#payment-container').html('<div id="proses"><h2>Pay with</h2><div id="paypal-button-container"></div></div><div id="loader"></div>');
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
                        
                        $('#alert-payment').html('<div id="alert-failed" class="alert alert-danger text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-frown"></i> Payment Error!</h2></div>');
                        $('#alert-payment').fadeIn("slow");
                    
                    },
                    onApprove: function(data, actions) {
                        $("#proses").hide();
                        $("#loader").addClass("loader");
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
                                    $("#loader").hide();
                                    $('#alert-payment').html('<div id="alert-success" class="alert alert-primary text-center" role="alert"><h2 style="margin-bottom:10px; margin-top:10px;"><i class="far fa-smile"></i> Payment Successful!</h2></div>');
                                    $('#alert-payment').fadeIn("slow");
                                }
                                else
                                {
                                    $("#loader").hide();
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
                }
                else
                {
                    $('#payment-container').html('<div id="proses"></div>');
                    $("#proses").addClass("loader");
                    //$('#payment-container').html('<div id="proses"><button id="pay-button" type="submit" style="height:47px;" class="btn btn-lg btn-block btn-theme mt-4"><i class="fas fa-lock"></i> <strong>Pay now</strong></button></div>');
                    //var payButton = document.getElementById('pay-button');
                    //var snapToken;
                    
                    $.ajax({
                        data: {
                            "_token": $("meta[name=csrf-token]").attr("content"),
                            "sessionId": '{{$shoppingcart->session_id}}',
                        },
                        type: 'POST',
                        url: '/snippets/payment/midtrans'
                    }).done(function( data ) {
                        if(data.id=="1")
                        {
                            //snapToken = data.snapToken;
                            window.location.href = data.redirect;
                        }
                    });

                    
                    //payButton.addEventListener('click', function () {
                        //snap.pay(snapToken);
                    //});
                   
                }
				
                $("#submit").slideUp("slow");
                $('#payment-container').fadeIn("slow");
                $("#proses").fadeIn("slow");
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
				$('#submit').html('<i class="fas fa-lock"></i> <strong>Checkout</strong>');
				
			}
		});
	
	
	return false;
}
</script>

@endsection
