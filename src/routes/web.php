<?php

Route::post('/reviews','budisteikul\tourfront\Controllers\FrontendController@reviews')->middleware(['web']);
Route::get('/tours/{slug}','budisteikul\tourfront\Controllers\FrontendController@category')->middleware(['web']);
Route::get('/tour/{slug}','budisteikul\tourfront\Controllers\FrontendController@product')->middleware(['web']);
Route::get('/booking/checkout','budisteikul\tourfront\Controllers\FrontendController@checkout')->middleware(['web']);
Route::get('/booking/receipt/{id}/{sessionId}','budisteikul\tourfront\Controllers\FrontendController@receipt')->middleware(['web']);
Route::get('/booking/shoppingcart/empty',function(){
	return view('tourfront::frontend.empty-shoppingcart');
})->middleware(['web']);
Route::get('/booking/{slug}','budisteikul\tourfront\Controllers\FrontendController@booking')->middleware(['web']);
Route::get('/page/{slug}','budisteikul\tourfront\Controllers\FrontendController@page')->middleware(['web']);
