# Laravel eboekhouden

Package to provide [eboekhouden](https://www.e-boekhouden.nl) as service in [laravel](https://www.laravel.com)

## Requirements

- PHP 7.4
- PHP soap extension

## Install

```shell script
$ composer require daanvanberkel/laravel_eboekhouden
```

Laravel will automatically detect the service provider

## Using

This is an example to use the provider in a laravel controller:

```php
<?php
namespace App\Http\Controllers;

use Dvb\Accounting\AccountingProvider;

class ExampleController
{
    /**
     * Return a list with all the relation in eboekhouden 
     * 
     * @param AccountingProvider $accountingProvider
     * @return array|\Dvb\Accounting\AccountingRelation[]
     */
    public function index(AccountingProvider $accountingProvider) {
        return $accountingProvider->getRelations();
    }
}
```

## Config

To edit the configuration, run the following command in a terminal window. The `eboekhouden.php` configuration file will be added to the `config` folder

```shell script
$ php artisan vendor:publish --provider=Dvb\Eboekhouden\ServiceProvider
```

Now you can edit `config/eboekhouden.php` to change the configuration.

## Changelog
### v2.0.2 - 2020-01-14
- Solved bug where mutation lines where not properly parsed
- Added test for getting mutations

### v2.0.1 - 2020-01-13
- Changed namespace of `EboekhoudenLedger` model
- Added test for getting ledgers

### v2.0.0 - 2020-01-11
- MIT license added
- Upgraded to daanvanberkel/laravel_accounting ^v2.1.3
- Use objects instead of arrays
- Added some tests

### v1.0.4 - 2020-01-10
- Handle optional fields better

### v1.0.2 - 2020-01-10
- Changed wsdl_url to wsdl in `config/eboekhouden.php`

### v1.0.1 - 2020-01-07
- Composer.json validated for integration with [packagist.org](https://packagist.org/packages/daanvanberkel/laravel_eboekhouden)

### v1.0.0 - 2020-01-07
- Initial version
