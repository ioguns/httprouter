HttpRouter - Fast request router for PHP
=======================================

This library provides a fast implementation of a regular expression based router.

Install
-------

To install with composer:

```sh
composer require ioguns/httprouter
```

Requires PHP 8.0 or newer.

Usage
-----

Here's a basic usage example:

```php
<?php

require '/path/to/vendor/autoload.php';

$router = new \IOguns\HttpRouter\RouteCollection();

    $router->get('/', 'demo','home');
    $router->get('/page/{page_slug=[a-zA-Z0-9\-]+}', ['name' => 'page.show']);
    $router->get('/about-us', ['name' => 'about-us']);
    $router->get('/contact-us', ['name' => 'contact-us']);
    $router->post('/contact-us', ['name' => 'contact-us.submit']);

    $router->addGroup('/blog', function ($router) {
        $router->get('/', ['name' => 'blog.index']);
        $router->get('/recent', ['name' => 'blog.recent']);
        $router->get('/post/{post_slug=[a-zA-Z0-9\-]+}', ['name' => 'blog.post.show']);
        $router->post('/post/{post_slug=[a-zA-Z0-9\-]+}/comment', ['name' => 'blog.post.comment']);
    });


// Fetch method and URI from somewhere
$http_method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$route_info = $router->getRoute($http_method, $uri);
switch ($route_info[0]) {
    case \IOguns\HttpRouter\IRouteCollection::ROUTE_NOT_FOUND:
        // ... 404 Not Found
        break;
    case \IOguns\HttpRouter\IRouteCollection::ROUTE_FOUND:
       $data = $route_info[1];
        $route_params = $route_info[2];
        // ... call $handler with $route_params
        break;
    case \IOguns\HttpRouter\IRouteCollection::ROUTE_METHOD_NOT_ALLOWED:
        $allowed_methods = $route_info[1];
        // ... 405 Method Not Allowed
        break;
}
```

#### Route Groups

Additionally, you can specify routes inside of a group. All routes defined inside a group will have a common prefix.

For example, defining your routes as:

```php
$router->addGroup('/admin', function (RouteCollection $router) {
    $router->addRoute('GET', '/do-something', 'handler');
    $router->addRoute('GET', '/do-another-thing', 'handler');
    $router->addRoute('GET', '/do-something-else', 'handler');
});
```

Will have the same result as:

```php
$router->addRoute('GET', '/admin/do-something', 'handler');
$router->addRoute('GET', '/admin/do-another-thing', 'handler');
$router->addRoute('GET', '/admin/do-something-else', 'handler');
```

Nested groups are also supported, in which case the prefixes of all the nested groups are combined.

### Caching

Pass an option `[enable_cache=>true,  'cache_handler' => new \IOguns\HttpRouter\Cache\FileCache(__DIR__)]` 
to the constructor:  

```php
<?php

$router = new \IOguns\HttpRouter\RouteCollection(['enable_cache' => true, 'cache_handler' => new \IOguns\HttpRouter\Cache\FileCache(__DIR__ . '/../caches/')]);
if (!$router->loadRoutes(cache_key: $cache_key = 'real_life')) {
    $router->get('/', 'demo','home');
    $router->get('/page/{page_slug=[a-zA-Z0-9\-]+}', ['name' => 'page.show']);
    $router->get('/about-us', ['name' => 'about-us']);
    $router->get('/contact-us', ['name' => 'contact-us']);
    $router->post('/contact-us', ['name' => 'contact-us.submit']);

    $router->addGroup('/blog', function ($router) {
        $router->get('/', ['name' => 'blog.index']);
        $router->get('/recent', ['name' => 'blog.recent']);
        $router->get('/post/{post_slug=[a-zA-Z0-9\-]+}', ['name' => 'blog.post.show']);
        $router->post('/post/{post_slug=[a-zA-Z0-9\-]+}/comment', ['name' => 'blog.post.comment']);
    });

   $router->saveRoutes(cache_key:$cache_key,ttl:60);
}
```

### Getting a Route path from a named route

```php
<?php

$router = new \IOguns\HttpRouter\RouteCollection();
$router->get('/', 'demo','home');

$url = $router->resolveNamedRoute(name:'home');

var_dump($url == '/'); //true

$router->get('/page/{page_slug=[a-zA-Z0-9\-]+}', ['name' => 'page.show'], 'page_by_slug');

$url = $router->resolveNamedRoute(name:'page_by_slug',['page_slug'=>'home-alone-part-2']);

var_dump($url == '/page/home-alone-part-2'); //true
    
```