<?php

	
	//Auth
	Route::post('/api/create-token', 'budisteikul\vertikaltrip\Controllers\AdminController@createToken');

	
    //Product
    Route::post('/api/product/add', 'budisteikul\vertikaltrip\Controllers\AdminController@product_add')->middleware(['SettingMiddleware','auth:sanctum']);
    Route::post('/api/product/remove', 'budisteikul\vertikaltrip\Controllers\AdminController@product_remove')->middleware(['SettingMiddleware','auth:sanctum']);

    

	
