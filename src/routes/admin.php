<?php

	

    //Product
    Route::post('/api/product/add', 'budisteikul\vertikaltrip\Controllers\APIController@product_add')->middleware(['SettingMiddleware']);
    Route::post('/api/product/remove', 'budisteikul\vertikaltrip\Controllers\APIController@product_remove')->middleware(['SettingMiddleware']);

    

	
