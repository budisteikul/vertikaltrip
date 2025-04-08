<?php

namespace budisteikul\vertikaltrip;

use Illuminate\Support\ServiceProvider;

class VertikaltripServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        
        
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        
        $this->app['router']->aliasMiddleware('SettingMiddleware', \budisteikul\vertikaltrip\Middleware\SettingMiddleware::class);

        $this->registerConfig();
        $this->loadViewsFrom(__DIR__.'/views', 'vertikaltrip');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_17_133006_create_categories_table.php');
		$this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_17_222702_create_products_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_18_151603_create_images_table.php');
		$this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_19_041300_create_channels_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_22_160052_create_reviews_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_25_125733_create_pages_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_19_141154_create_shoppingcarts_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_19_141233_create_shoppingcart_products_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_19_141242_create_shoppingcart_product_details_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_19_141252_create_shoppingcart_questions_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_19_141259_create_shoppingcart_question_options_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_19_141311_create_shoppingcart_payments_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2022_04_12_195049_create_vouchers_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2022_04_13_194552_create_vouchers_products_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2022_12_04_011639_create_close_outs_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2022_12_23_220624_create_settings_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2023_12_07_135250_create_shoppingcart_cancellations_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2024_02_08_003236_create_partners_table.php');

        $this->loadMigrationsFrom(__DIR__.'/migrations/2024_04_02_180446_create_contacts_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2024_04_02_180959_create_messages_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2024_05_19_011530_create_slugs_table.php');


        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_17_000058_create_change_emails_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2020_11_17_014602_create_file_temps_table.php');

        $this->loadMigrationsFrom(__DIR__.'/migrations/2019_09_30_125253_create_fin_categories_table.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations/2019_09_30_132534_create_fin_transactions_table.php');

        $this->loadMigrationsFrom(__DIR__.'/migrations/2025_04_08_172809_create_orders_table.php');
        
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/routes/admin.php');
    }

    protected function registerConfig()
    {
        app()->config["filesystems.disks.gcs"] = [
            'driver' => 'gcs',
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE', null), 
            'key_file' => [], 
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', 'your-project-id'), 
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET', 'your-bucket'),
            'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', ''), 
            'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI', null), 
            'apiEndpoint' => env('GOOGLE_CLOUD_STORAGE_API_ENDPOINT', null), 
            'visibility' => 'public', 
            'metadata' => ['cacheControl'=> 'public,max-age=86400'], 
        ];

        app()->config["services.mailgun"] = [
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
            'scheme' => 'https',
        ];

        app()->config["mail.mailers.mailgun"] = [
            'transport' => 'mailgun',
        ];
    }
}
