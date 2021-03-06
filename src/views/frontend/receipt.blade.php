@inject('BookingHelper', 'budisteikul\toursdk\Helpers\BookingHelper')
@extends('vertikaltrip::layouts.app')
@section('title','Receipt')
@section('content')
@push('scripts')
@if(env('MIDTRANS_ENV')=="sandbox")
<script type="text/javascript"
            src="https://app.sandbox.midtrans.com/snap/snap.js"
            data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>
@else
<script type="text/javascript"
            src="https://app.midtrans.com/snap/snap.js"
            data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>
@endif
@endpush
<section id="booking" style="background-color:#ffffff">
<div class="container">
	<div class="row">
		<div class="col-lg-12 col-md-12 mx-auto">
			<div class="row" style="padding-bottom:0px;">
				<div class="col-lg-12 text-left">
				<div style="height:70px;"></div>
			
			<div class="card mb-8 shadow p-2 mt-2">
 				 <div class="card-body" style="padding-left:10px;padding-right:10px;padding-top:10px;padding-bottom:15px;">
				 <div class="col-md-12  mx-auto text-left">
				 		<p>
                        <h4>Your booking references is {{ $shoppingcart->confirmation_code }}</h4>
						
						Thank you for your booking with <b>{{env('APP_NAME')}}</b>, a confirmation will be sent to your email address.
						</p>

						

				</div>
				 </div>
			</div>

			
			<div class="row mb-2">
			<!-- ################################################################### --> 
			<div class="col-lg-6 col-lg-auto mb-6">

            	<div class="card shadow mt-4">
					<div class="card-header bg-dark text-white pb-1">
						<h4><i class="fas fa-file-invoice"></i> Payment Status</h4>
					</div>
                
					<div class="card-body">
                        <p>
                        <h3>Total</h3>
                        {{ $shoppingcart->shoppingcart_payment->currency }} {{ $shoppingcart->shoppingcart_payment->amount }}
                        <h3>Status</h3>
                        {!! $BookingHelper->payment_status_public($shoppingcart->shoppingcart_payment->payment_status) !!}

                  		@if($shoppingcart->shoppingcart_payment->payment_status==4)
					
						<button id="pay-button" type="submit" style="height:47px;" class="btn btn-lg btn-block btn-theme mt-4"><i class="fas fa-lock"></i> <strong>Pay now</strong></button>
						<script>
							var payButton = document.getElementById('pay-button');
							payButton.addEventListener('click', function () {
                        		snap.pay('{{$shoppingcart->shoppingcart_payment->snaptoken}}');
                    		});
						</script>
					
						@endif	
                        </p>
					
					</div>
				</div>

                <div class="card shadow mt-4">
					<div class="card-header bg-dark text-white pb-1">
						<h4><i class="fas fa-user-tie"></i> Customer Info</h4>
					</div>
                
					<div class="card-body">
                        <p>
						<h3>Name</h3>
						{{ $shoppingcart->shoppingcart_questions()->select('answer')->where('type','mainContactDetails')->where('question_id','firstName')->first()->answer }}
                        {{ $shoppingcart->shoppingcart_questions()->select('answer')->where('type','mainContactDetails')->where('question_id','lastName')->first()->answer }} 
                        <h3>Phone</h3>
						{{ $shoppingcart->shoppingcart_questions()->select('answer')->where('type','mainContactDetails')->where('question_id','phoneNumber')->first()->answer }} 
                        <h3>Email</h3>
						{{ $shoppingcart->shoppingcart_questions()->select('answer')->where('type','mainContactDetails')->where('question_id','email')->first()->answer }} 
                        </p>
						
					</div>
				</div>

				

				

			</div>
			<!-- ################################################################### -->
			<div class="col-lg-6 col-lg-auto mb-6 mt-4">
            	  
                <div class="card shadow">
					<div class="card-header bg-dark text-white pb-1">
						<h4><i class="fas fa-file"></i> Travel Documents</h4>
					</div>
                
					<div class="card-body">
                	 
                        <p>
                        @if($shoppingcart->shoppingcart_payment->payment_status>0)
						<h3>Receipt</h3>
						<a target="_blank" class="text-theme" href="/snippets/pdf/invoice/{{ $shoppingcart->session_id }}/Invoice-{{ $shoppingcart->confirmation_code }}.pdf"><i class="fas fa-file-invoice"></i> Invoice-{{ $shoppingcart->confirmation_code }}.pdf</a>
						@else
						No Document
						@endif 

						@if($shoppingcart->shoppingcart_payment->payment_status==2 || $shoppingcart->shoppingcart_payment->payment_status==1)
							<h3>Experience tickets</h3>
                       		@foreach($shoppingcart->shoppingcart_products()->get() as $shoppingcart_product)
                        		<a target="_blank" class="text-theme" href="/snippets/pdf/ticket/{{$shoppingcart->session_id}}/Ticket-{{$shoppingcart_product->product_confirmation_code}}.pdf"><i class="fas fa-ticket-alt"></i> Ticket-{{ $shoppingcart_product->product_confirmation_code }}.pdf</a>
                        		<br>
                        	@endforeach
                        @endif

                        </p>
							
					</div>
					
				</div>
			</div>
			<!-- ################################################################### -->			
			</div>
				<div style="height:70px;"></div>		
				</div>
			</div>
        </div>
	</div>
</div>
</section>
@endsection