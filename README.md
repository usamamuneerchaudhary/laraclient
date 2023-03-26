[![image](https://www.linkpicture.com/q/laraclient.jpg)](https://thewebtier.com)
## Introduction
Lara Client simplifies the process of working with APIs in Laravel, making it easy to handle authentication, rate 
limiting, and error handling. 
It allows to set up several API connections in a central configuration file, specifying the credentials for each 
connection. 
The package also includes a cache layer to speed up requests, and a logging system to track API requests and responses for debugging purposes. 
With Lara Client, developers can quickly integrate multiple APIs into their Laravel applications, reducing development 
time and effort, and making it easier to manage API integrations over time.

Here's a quick example of what you can do in your models to enable tagging:

- *This package supports Laravel 10*
- *Minimum PHP v8.1 supported*

```
namespace App\Http\Controllers;

use Usamamuneerchaudhary\LaraClient\LaraClient;

class ApiController extends Controller
{
    $client = new LaraClient('weatherapi');
    $response = $client->get('current.json', ['q' => 'london']);
    $currentWeather = $response->getData();
    
    $client2 = new LaraClient('geodb');
    $georesponse = $client->get('countries');
    $countries = $response->getData();
    
    ....
    ....
    
}
```

## Installation
You can install the package via composer:

`composer require usamamuneerchaudhary/laraclient`
## Run Migrations

Once the package is installed, you can run migrations,

`php artisan migrate`


## Publish Config File
```
 php artisan vendor:publish --provider="Usamamuneerchaudhary\LaraClient\LaraClientServiceProvider" --tag="config"
``` 
This will create a `lara_client.php` file, where you can define multiple third party API connections.

## Service Provider

Don't forget to add the ServiceProvider in `app.php`:

```
\Usamamuneerchaudhary\LaraClient\LaraClientServiceProvider::class,
```
## Logging & Publish Views

We're using a logging table to store requests and responses, you can access this by the following route:
```
http://yourwebsite.com/laraclient/logs

//to access logs for any specific endpoint
http://yourwebsite.com/laraclient/logs?endpoint=https://weatherapi-com.p.rapidapi.com/current.json
```



## Tests
`composer test`

## Security
If you discover any security related issues, please email hello@usamamuneer.me instead of using the issue tracker.

## Credits

- [Usama Muneer](https://github.com/usamamuneerchaudhary)

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


