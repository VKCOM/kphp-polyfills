# kphp-polyfills

**Polyfills** are PHP implementations of functions supported by KPHP natively.  
Without polyfills, your code can be compiled, but can't be run by plain PHP.


## How to install and use this package

* In a PHP project, create `composer.json` with the following contents (if exists, manually merge):  
```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:VKCOM/kphp-polyfills.git"
    }
  ],
  "require": {
    "vkcom/kphp-polyfills": "^1.0.0"
  }
}
```

* Run `composer install`; ensure that *vendor/* folder contains this lib.

* If it's your first installed package, call composer autoload:
```php
require_once '.../vendor/autoload.php';
``` 

* You are done! All KPHP functions would work on plain PHP.



