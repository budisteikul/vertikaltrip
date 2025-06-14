<?php

	
	//Auth
	Route::post('/api/create-token', 'budisteikul\vertikaltrip\Controllers\AdminController@createToken');

	
    //Product
    Route::post('/api/product/sync', 'budisteikul\vertikaltrip\Controllers\AdminController@product_sync')->middleware(['SettingMiddleware','auth:sanctum']);

    Route::post('/api/openai', 'budisteikul\vertikaltrip\Controllers\AdminController@openai')->middleware(['SettingMiddleware','auth:sanctum']);

    

	//Schedule
	Route::post('/api/test', 'budisteikul\vertikaltrip\Controllers\AdminController@test')->middleware(['SettingMiddleware']);
