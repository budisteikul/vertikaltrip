<?php

	// API
	Route::get('/api/index_jscript', 'budisteikul\vertikaltrip\Controllers\APIController@config')->middleware(['SettingMiddleware']);
	Route::get('/api/config', 'budisteikul\vertikaltrip\Controllers\APIController@config')->middleware(['SettingMiddleware']);
	Route::get('/api/{sessionId}/navbar', 'budisteikul\vertikaltrip\Controllers\APIController@navbar')->middleware(['SettingMiddleware']);
	Route::get('/api/tawkto/{id}', 'budisteikul\vertikaltrip\Controllers\APIController@tawkto')->middleware(['SettingMiddleware']);

	//Review
	Route::post('/api/review', 'budisteikul\vertikaltrip\Controllers\APIController@review')->middleware(['SettingMiddleware']);
	Route::get('/api/review/count', 'budisteikul\vertikaltrip\Controllers\APIController@review_count')->middleware(['SettingMiddleware']);
	Route::get('/api/review/jscript', 'budisteikul\vertikaltrip\Controllers\APIController@review_jscript')->middleware(['SettingMiddleware']);

	//Schedule
	Route::post('/api/schedule', 'budisteikul\vertikaltrip\Controllers\APIController@schedule')->middleware(['SettingMiddleware']);
	Route::get('/api/schedule/jscript', 'budisteikul\vertikaltrip\Controllers\APIController@schedule_jscript')->middleware(['SettingMiddleware']);

	//Page
	Route::get('/api/page/{slug}', 'budisteikul\vertikaltrip\Controllers\APIController@page')->middleware(['SettingMiddleware']);

	//Category
    Route::get('/api/categories', 'budisteikul\vertikaltrip\Controllers\APIController@categories')->middleware(['SettingMiddleware']);
    Route::get('/api/category/{slug}', 'budisteikul\vertikaltrip\Controllers\APIController@category')->middleware(['SettingMiddleware']);

    //Product
    Route::get('/api/product/{slug}', 'budisteikul\vertikaltrip\Controllers\APIController@product')->middleware(['SettingMiddleware']);
	Route::get('/api/product/{slug}/{sessionId}/product_jscript', 'budisteikul\vertikaltrip\Controllers\APIController@product_jscript')->middleware(['SettingMiddleware']);


	//Create Payment
	Route::post('/api/payment/checkout', 'budisteikul\vertikaltrip\Controllers\PaymentController@checkout')->middleware(['SettingMiddleware']);
	//Stripe
	Route::get('/api/payment/stripe/jscript/{sessionId}', 'budisteikul\vertikaltrip\Controllers\PaymentController@stripe_jscript')->middleware(['SettingMiddleware']);
	Route::post('/api/payment/stripe', 'budisteikul\vertikaltrip\Controllers\PaymentController@createpaymentstripe')->middleware(['SettingMiddleware']);
	//Xendit
	Route::get('/api/payment/xendit/jscript/{sessionId}', 'budisteikul\vertikaltrip\Controllers\PaymentController@xendit_jscript')->middleware(['SettingMiddleware']);
	Route::post('/api/payment/xendit', 'budisteikul\vertikaltrip\Controllers\PaymentController@createpaymentxendit')->middleware(['SettingMiddleware']);
	//Paypal
	Route::get('/api/payment/paypal/jscript/{sessionId}', 'budisteikul\vertikaltrip\Controllers\PaymentController@paypal_jscript')->middleware(['SettingMiddleware']);
	Route::post('/api/payment/paypal', 'budisteikul\vertikaltrip\Controllers\PaymentController@createpaymentpaypal')->middleware(['SettingMiddleware']);
	//QRIS
	Route::get('/api/payment/qris/jscript/{sessionId}', 'budisteikul\vertikaltrip\Controllers\PaymentController@qris_jscript')->middleware(['SettingMiddleware']);
	//Wise
	Route::get('/api/payment/wise/jscript/{sessionId}', 'budisteikul\vertikaltrip\Controllers\PaymentController@wise_jscript')->middleware(['SettingMiddleware']);


	//Shoppingcart
	Route::get('/api/activity/{activityId}/calendar/json/{year}/{month}', 'budisteikul\vertikaltrip\Controllers\APIController@snippetscalendar')->middleware(['SettingMiddleware']);
	Route::post('/api/activity/invoice-preview', 'budisteikul\vertikaltrip\Controllers\APIController@snippetsinvoice')->middleware(['SettingMiddleware']);
	Route::post('/api/activity/remove', 'budisteikul\vertikaltrip\Controllers\APIController@removebookingid')->middleware(['SettingMiddleware']);
	Route::post('/api/widget/cart/session/{id}/activity', 'budisteikul\vertikaltrip\Controllers\APIController@addshoppingcart')->middleware(['SettingMiddleware']);
	Route::post('/api/shoppingcart', 'budisteikul\vertikaltrip\Controllers\APIController@shoppingcart')->middleware(['SettingMiddleware']);
	Route::post('/api/promocode', 'budisteikul\vertikaltrip\Controllers\APIController@applypromocode')->middleware(['SettingMiddleware']);
	Route::post('/api/promocode/remove', 'budisteikul\vertikaltrip\Controllers\APIController@removepromocode')->middleware(['SettingMiddleware']);

	//Checkout
	Route::get('/api/checkout/jscript', 'budisteikul\vertikaltrip\Controllers\APIController@checkout_jscript')->middleware(['SettingMiddleware']);

	//Receipt
	Route::get('/api/receipt/jscript', 'budisteikul\vertikaltrip\Controllers\APIController@receipt_jscript')->middleware(['SettingMiddleware']);
	Route::get('/api/receipt/{sessionId}/{confirmationCode}', 'budisteikul\vertikaltrip\Controllers\APIController@receipt')->middleware(['SettingMiddleware']);

	//Cancellation
	Route::post('/api/cancel/{sessionId}/{confirmationCode}', 'budisteikul\vertikaltrip\Controllers\APIController@cancellation')->middleware(['SettingMiddleware']);

	//Callback Payment
	Route::post('/api/payment/stripe/confirm', 'budisteikul\vertikaltrip\Controllers\CallbackController@confirmpaymentstripe')->middleware(['SettingMiddleware']);
	Route::post('/api/payment/paypal/confirm', 'budisteikul\vertikaltrip\Controllers\CallbackController@confirmpaymentpaypal')->middleware(['SettingMiddleware']);
	Route::post('/api/payment/xendit/confirm', 'budisteikul\vertikaltrip\Controllers\CallbackController@confirmpaymentxendit')->middleware(['SettingMiddleware']);
	
	//Billing Tools
	Route::post('/api/tool/billing/{sessionId}', 'budisteikul\vertikaltrip\Controllers\ToolController@billing')->middleware(['SettingMiddleware']);
	Route::post('/api/tool/bin', 'budisteikul\vertikaltrip\Controllers\ToolController@bin')->middleware(['SettingMiddleware']);

	//PDF
	Route::get('/api/pdf/manual/{sessionId}/Manual-{id}.pdf', 'budisteikul\vertikaltrip\Controllers\APIController@manual')->middleware(['SettingMiddleware']);
	Route::get('/api/pdf/invoice/{sessionId}/Invoice-{id}.pdf', 'budisteikul\vertikaltrip\Controllers\APIController@invoice')->middleware(['SettingMiddleware']);
	Route::get('/api/pdf/ticket/{sessionId}/Ticket-{id}.pdf', 'budisteikul\vertikaltrip\Controllers\APIController@ticket')->middleware(['SettingMiddleware']);
	Route::get('/api/pdf/instruction/{sessionId}/Instruction-{id}.pdf', 'budisteikul\vertikaltrip\Controllers\APIController@instruction')->middleware(['SettingMiddleware']);

	//Download
	Route::get('/api/qrcode/{sessionId}/{id}', 'budisteikul\vertikaltrip\Controllers\APIController@downloadQrcode')->middleware(['SettingMiddleware']);

	//Last Order
	Route::get('/api/ticket/{sessionId}/last-order', 'budisteikul\vertikaltrip\Controllers\APIController@last_order')->middleware(['SettingMiddleware']);

	// Webhook
	Route::post('/webhook/{webhook_app}', 'budisteikul\vertikaltrip\Controllers\WebhookController@webhook')->middleware(['SettingMiddleware']);
	Route::get('/webhook/{webhook_app}', 'budisteikul\vertikaltrip\Controllers\WebhookController@webhook')->middleware(['SettingMiddleware']);

	//TASK
	Route::post('/task', 'budisteikul\vertikaltrip\Controllers\TaskController@task')->middleware(['SettingMiddleware']);

	//LOG
	Route::post('/logger/{identifier}', 'budisteikul\vertikaltrip\Controllers\LogController@log')->middleware(['SettingMiddleware']);

	

	
