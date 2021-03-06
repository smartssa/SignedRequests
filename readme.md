# Signed Requests

[![Build Status](https://travis-ci.org/SoapBox/SignedRequests.svg?branch=master)](https://travis-ci.org/SoapBox/SignedRequests) [![Coverage Status](https://coveralls.io/repos/github/SoapBox/SignedRequests/badge.svg?branch=master)](https://coveralls.io/github/SoapBox/SignedRequests?branch=master) [![Code Climate](https://codeclimate.com/github/SoapBox/SignedRequests/badges/gpa.svg)](https://codeclimate.com/github/SoapBox/SignedRequests)

A wrapper to add the ability to accept signed requests to a Laravel project.

## Installation

### Composer

```sh
composer require soapbox/signed-requests
```

### Setup the Service Provider

Open `config/app.php` and register the required service provider above your application providers.

```php
'providers' => [
    ...
    SoapBox\SignedRequests\ServiceProvider::class
    ...
]
```

### Publish the Configuration

```php
php artisan vendor:publish --provider 'SoapBox\SignedRequests\ServiceProvider'
```

### Configuring your Environment

You will need to set the following details in your environment:

```sh
SIGNED_REQUEST_ALGORITHM=
SIGNED_REQUEST_CACHE_PREFIX=
SIGNED_REQUEST_SIGNATURE_HEADER=
SIGNED_REQUEST_ALGORITHM_HEADER=
SIGNED_REQUEST_KEY=
SIGNED_REQUEST_ALLOW_REPLAYS=
SIGNED_REQUEST_TOLERANCE_SECONDS=
```

Each of the settings above allows for a different level of configuration.

  - `SIGNED_REQUEST_ALGORITHM` is the algorithm that will be used to generate / verify the signature. This is defaulted to use `sha256` feel free to change this to anything that `hash_hmac` accepts.
  - `SIGNED_REQUEST_CACHE_PREFIX` is the prefix to use for all the cache keys that will be generated. Here you can use the default if you're not planning on sharing a cache between multiple applications.
  - `SIGNED_REQUEST_SIGNATURE_HEADER` should be the request header that the signature will be included on, `X-Signature` will be used by default.
  - `SIGNED_REQUEST_ALGORITHM_HEADER` should be the request header that the includes the algorithm used to sign the request.
  - `SIGNED_REQUEST_KEY` is the shared secret key between the application generating the requests, and the application consuming them. This value should not be publically available.
  - `SIGNED_REQUEST_ALLOW_REPLAYS` allows you to enable or disable replay attacks. By default replays are disabled.
  - `SIGNED_REQUEST_TOLERANCE_SECONDS` is the number of seconds that a request will be considered for. This setting allows for some time drift between servers and is only used when replays are disabled.

### Setup the Middleware

Signed Requests includes a middleware to validate the signature of a request for your automatically. To get started, add the following middleware to the `$routeMiddleware` property of your `app/Http/Kernel.php` file.

```php
'verify-signature' => \SoapBox\SignedRequests\Middlewares\Laravel\VerifySignature::class
```

### Verify the Signature

The `verify-signature` middleware may be assigned to a route to verify the signature of the incoming request to verify its authenticity:

```php
Route::get('/fire', function () {
    return "You'll only see this if the signature of the request is valid!";
})->middleware('verify-signature');
```

### Setting Up Additional Keys

You can also set up additional keys to use if you want different keys for different endpoints.

Add them to your environment:

```sh
CUSTOM_SIGNED_REQUEST_ALGORITHM=
CUSTOM_SIGNED_REQUEST_CACHE_PREFIX=
CUSTOM_SIGNED_REQUEST_SIGNATURE_HEADER=
CUSTOM_SIGNED_REQUEST_ALGORITHM_HEADER=
CUSTOM_SIGNED_REQUEST_KEY=
CUSTOM_SIGNED_REQUEST_ALLOW_REPLAYS=
CUSTOM_SIGNED_REQUEST_TOLERANCE_SECONDS=
```

Update the configuration in `signed-requests.php`

```php
    'default' => [
        ...
    ],
    'custom' => [
        'algorithm' => env('CUSTOM_SIGNED_REQUEST_ALGORITHM', 'sha256'),
        'cache-prefix' => env('CUSTOM_SIGNED_REQUEST_CACHE_PREFIX', 'signed-requests'),
        'headers' => [
            'signature' => env('CUSTOM_SIGNED_REQUEST_SIGNATURE_HEADER', 'X-Signature'),
            'algorithm' => env('CUSTOM_SIGNED_REQUEST_ALGORITHM_HEADER', 'X-Signature-Algorithm')
        ],
        'key' => env('CUSTOM_SIGNED_REQUEST_KEY', 'key'),
        'request-replay' => [
            'allow' => env('CUSTOM_SIGNED_REQUEST_ALLOW_REPLAYS', false),
            'tolerance' => env('CUSTOM_SIGNED_REQUEST_TOLERANCE_SECONDS', 30)
        ]
    ]
```

Set up your route to use the custom key. The param you pass must be the same name as the key you set in the configuration in `signed-requests.php`

```php
Route::get('/fire', function () {
    return "You'll only see this if the signature of the request is valid!";
})->middleware('verify-signature:custom');
```

### Signing Postman Requests

If you, like us, like to use [postman](https://www.getpostman.com/) to share your api internally you can use the following pre-request script to automatically sign your postman requests:

```js
function guid() {
  function s4() {
    return Math.floor((1 + Math.random()) * 0x10000)
      .toString(16)
      .substring(1);
  }
  return s4() + s4() + '-' + s4() + '-' + s4() + '-' +
    s4() + '-' + s4() + s4() + s4();
}

function getTimestamp() {
    var date = (new Date()).toISOString();
    date = date.split("T");
    date[1] = date[1].split(".")[0];
    return date.join(' ');
}

postman.setEnvironmentVariable("x-signed-id", guid());
postman.setEnvironmentVariable("x-signed-timestamp", getTimestamp());
postman.setEnvironmentVariable("x-algorithm", "sha256");

var payload = {
    "id": postman.getEnvironmentVariable("x-signed-id"),
    "method": request.method,
    "timestamp": postman.getEnvironmentVariable("x-signed-timestamp"),
    "uri": request.url.replace("{{url}}", postman.getEnvironmentVariable("url")),
    "content": (Object.keys(request.data).length === 0) ? "" : JSON.stringify(JSON.parse(request.data))
};

var hash = CryptoJS.HmacSHA256(JSON.stringify(payload), postman.getEnvironmentVariable("key"));
var signature = hash.toString();

postman.setEnvironmentVariable("x-signature", signature);

```

Note for this to work you'll have to setup your environment to have the following variables:

  - `{{url}}` this is the base url to the api you'll be hitting.
  - `{{key}}` this is the shared secret key you'll be using in your environment.

All other environment variables that will be needed will be automatically generated by the above script.
