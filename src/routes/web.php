<?php

Route::post('/reviews','budisteikul\vertikaltrip\Controllers\FrontendController@reviews')->middleware(['web']);
Route::get('/tours/{slug}','budisteikul\vertikaltrip\Controllers\FrontendController@category')->middleware(['web']);
Route::get('/tour/{slug}','budisteikul\vertikaltrip\Controllers\FrontendController@product')->middleware(['web']);

Route::get('/booking/billing','budisteikul\vertikaltrip\Controllers\FrontendController@billing')->middleware(['web']);

Route::get('/booking/checkout','budisteikul\vertikaltrip\Controllers\FrontendController@checkout')->middleware(['web']);
Route::get('/booking/receipt/{id}/{sessionId}','budisteikul\vertikaltrip\Controllers\FrontendController@receipt')->middleware(['web']);
Route::get('/booking/shoppingcart/empty',function(){
	return view('vertikaltrip::frontend.empty-shoppingcart');
})->middleware(['web']);
Route::get('/booking/{slug}','budisteikul\vertikaltrip\Controllers\FrontendController@booking')->middleware(['web']);
Route::get('/page/{slug}','budisteikul\vertikaltrip\Controllers\FrontendController@page')->middleware(['web']);
