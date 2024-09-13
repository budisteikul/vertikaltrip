<?php

	Route::get('/', function () {
    return view('welcome');
	})->middleware(['auth:sanctum']);

	//Auth
	Route::post('/api/auth', 'budisteikul\vertikaltrip\Controllers\AdminController@auth');

	
    //Product
    Route::post('/api/product/add', 'budisteikul\vertikaltrip\Controllers\AdminController@product_add')->middleware(['SettingMiddleware','auth:sanctum']);
    Route::post('/api/product/remove', 'budisteikul\vertikaltrip\Controllers\AdminController@product_remove')->middleware(['SettingMiddleware','auth:sanctum']);

    

	
