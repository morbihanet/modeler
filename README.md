### Usage

This package contains a class to make dynamic models easily without migration.

Once this package is installed, you can do these things:

```php
<?php
namespace App\Models;

use Morbihanet\Modeler\Modeler;

class Book extends Modeler {}
```
```php
<?php
namespace App\Models;

use Morbihanet\Modeler\Modeler;

class Author extends Modeler {}
```
```php
<?php
use App\Models\Author;
use App\Models\Book;

$author = Author::create(['lastname' => 'Hugo', 'firstname' => 'Victor']);

Book::create(['title' => 'Notre Dame de Paris', 'author_id' => $author->id]);
```
```php
<?php
namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;

class HomeController extends Controller 
{
    public function index()
    {
        $victorHugo = Author::find(1);
        $books = $victorHugo->books;

        return view('home', compact('victorHugo', 'books'));
    }
}
```
### Installation

This package can be used with Laravel 5.8 or higher.

This package publishes a config/modeler.php file. If you already have a file by that name, you must rename or remove it.

You can install the package via composer:
```
composer require morbihanet/modeler
```

Optional: The service provider will automatically get registered. Or you may manually add the service provider in your config/app.php file:

```php
'providers' => [
    // ...
    Morbihanet\Modeler\ModelerServiceProvider::class,
];
```
You should publish the migration and the config/modeler.php config file with:
```
php artisan vendor:publish --provider="Morbihanet\Modeler\ModelerServiceProvider"
```

Run the migrations: After the config and migration have been published and configured, you can create the table for this package by running:
```
php artisan migrate
```
