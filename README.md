# php-bitly (but async)
Version 3 now uses OAuth2 as required by Bitly. [Get your developer access token here](https://bitly.com/a/oauth_apps)

## Installation

Install via [composer](https://getcomposer.org/) - In the terminal:
```bash
composer require leadthread/php-bitly
```

## Usage
```php
use LeadThread\Bitly\Bitly;
$c = new Bitly("access token");
$result = $c->shorten("https://www.google.com/");
var_dump($result);
// string(21) "http://bit.ly/1SvUIo8"
```
