<?php

	
	//Auth
	Route::post('/api/create-token', 'budisteikul\vertikaltrip\Controllers\AdminController@createToken');

	
    //Product
    Route::post('/api/product/sync', 'budisteikul\vertikaltrip\Controllers\AdminController@product_sync')->middleware(['SettingMiddleware','auth:sanctum']);

    

	
