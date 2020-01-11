<?php
namespace Dvb\Eboekhouden;

use Dvb\Accounting\AccountingProvider;
use Illuminate\Support\ServiceProvider as SP;

class ServiceProvider extends SP {
    public function boot() {
        $this->publishes([
            __DIR__ . '/../config/eboekhouden.php' => config_path('eboekhouden.php')
        ]);
    }

    public function register() {
        $this->mergeConfigFrom(__DIR__ . '/../config/eboekhouden.php', 'eboekhouden');

        $this->app->singleton(AccountingProvider::class, function($app) {
            return new EboekhoudenProvider([
                'username' => config('eboekhouden.username'),
                'sec_code_1' => config('eboekhouden.security_code1'),
                'sec_code_2' => config('eboekhouden.security_code2'),
                'wsdl' => config('eboekhouden.wsdl'),
                'payment_term' => config('eboekhouden.payment_term'),
                'invoice_template' => config('eboekhouden.invoice_template'),
                'email_from_address' => config('eboekhouden.email_from_address'),
                'email_from_name' => config('eboekhouden.email_from_name'),
            ]);
        });
    }
}
