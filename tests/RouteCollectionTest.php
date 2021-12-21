<?php

namespace IOguns\HttpRouter\Tests;

class RouteCollectionTest extends \PHPUnit\Framework\TestCase {

    const SAMPLE_REGEX = '[0-9]{4}[A-Z]{3}[0-9]{4}';

    protected static \IOguns\HttpRouter\RouteCollection $router;

    protected function tearDown(): void {

    }

    static function setUpBeforeClass(): void {
        self::$router = new \IOguns\HttpRouter\RouteCollection(['enable_cache' => true, 'cache_handler' => new \IOguns\HttpRouter\Cache\FileCache(sys_get_temp_dir() . '/')]);
        self::$router->addRouteRegex('hash', self::SAMPLE_REGEX);
    }

    function testRealWorld() {

        $data = 'function';

        if (!self::$router->loadRoutes($cache_key = 'real_life')) {
//            self::$router->get('/', $data);
            for ($i = 0, $str = 'a'; $i < 1; $i++, $str++) {
                self::$router->delete("/user$str$i/{name$str$i}/{id=[0-9]+}/{name=:any}?", "controller->{name$str$i}", "user$str$i");
                self::$router->get("/user$str$i/{name$str$i}/{id=[0-9]+}?", "controller->{name$str$i}", "user$str$i");
            }
            self::$router->get('/about-us', ['name' => 'about-us']);
            self::$router->get('/contact-us', ['name' => 'contact-us']);
            self::$router->post('/contact-us', ['name' => 'contact-us.submit']);
            self::$router->addGroup('/blog', function ($router) {
                $router->get('/', ['name' => 'blog.index']);
                $router->get('/recent', ['name' => 'blog.recent']);
                $router->get('/post/{post_slug=[a-zA-Z0-9\-]+}', ['name' => 'blog.post.show']);
                $router->post('/post/{post_slug=[a-zA-Z0-9\-]+}/comment', ['name' => 'blog.post.comment']);
            });
            self::$router->addGroup('/shop', function ($router) {
                $router->get('/', ['name' => 'shop.index']);
                $router->get('/category', ['name' => 'shop.category.index']);
                $router->get('/category/search/{filter_by=[a-zA-Z]+}:{filter_value}', ['name' => 'shop.category.search']);
                $router->get('/category/{category_id=\d+}', ['name' => 'shop.category.show']);
                $router->get('/category/{category_id=\d+}/product', ['name' => 'shop.category.product.index']);
                $router->get('/category/{category_id=\d+}/product/search/{filter_by=[a-zA-Z]+}:{filter_value}', ['name' => 'shop.category.product.search']);
                $router->get('/product', ['name' => 'shop.product.index']);
                $router->get('/product/search/{filter_1by=[a-zA-Z]+}', ['name' => 'shop.product.search']);
                $router->get('/product/{product_id=\d+}', ['name' => 'shop.product.show']);
                $router->get('/cart', ['name' => 'shop.cart.show']);
                $router->put('/cart', ['name' => 'shop.cart.add']);
                $router->delete('/cart', ['name' => 'shop.cart.empty']);
                $router->get('/cart/checkout', ['name' => 'shop.cart.checkout.show']);
                $router->post('/cart/checkout', ['name' => 'shop.cart.checkout.process']);
            });
            self::$router->addGroup('/admin', function ($router)use ($data) {
                $router->get('/', ['name' => 'admin.home.page']);
                $router->get('/login', ['name' => 'admin.login']);
                $router->post('/login', ['name' => 'admin.login.submit']);
                $router->get('/logout', ['name' => 'admin.logout']);
                $router->get('/', ['name' => 'admin.index']);
                $router->get('/product', ['name' => 'admin.product.index']);
                $router->get('/product/create', ['name' => 'admin.produ'
                    . 'ct.create']);
                $router->any('/product', ['name' => 'admin.product.store']);
                $router->get('/product/{product_id=\d+}', ['name' => 'admin.product.show']);
                $router->get('/product/{product_id=\d+}/edit', ['name' => 'admin.product.edit']);
                $router->addRoute('PUT|PATCH', '/product/{product_id=\d+}', ['name' => 'admin.product.update']);
                $router->delete('/product/{product_id=\d+}', ['name' => 'admin.product.destroy']);
                $router->get('/category', ['name' => 'admin.category.index']);
                $router->get('/category/create', ['name' => 'admin.category.create']);
                $router->post('/category', ['name' => 'admin.category.store']);
                $router->get('/category/{category_id=\d+}', ['name' => 'admin.category.show']);
                $router->get('/category/{category_id=\d+}/edit', ['name' => 'admin.category.edit']);
                $router->addRoute('PUT|PATCH', '/category/{category_id=\d+}', ['name' => 'admin.category.update']);
                $router->delete('/category/{category_id=\d+}', $data, 'admin.category.destroy');
            });

            self::$router->saveRoutes(cache_key: $cache_key, ttl: 10000);
        }


        $res = self::$router->getRoute('post', '/admin/product');

        $this->assertTrue($res[0] == \IOguns\HttpRouter\IRouteCollection::ROUTE_FOUND);
    }

    public function testRegex() {
        $this->assertTrue(self::$router->hasRouteRegex('hash'));
        self::$router->get('/demo/{some=:hash}?', [], 'name');

        self::$router->addRouteRegex('d', '\d+')?->post('/s/{d=:d}', []);
        $this->assertTrue(self::$router->hasRouteRegex('d'));

        $res = self::$router->getRoute('POST', '/s/1');

        $this->assertTrue($res[0] == \IOguns\HttpRouter\IRouteCollection::ROUTE_FOUND);
    }

    public function testAddRoute() {
        self::$router->any('/demo/', 'some');

        $r = self::$router->getRoute('GET', '/demo/');
        $this->assertTrue($r[0] == \IOguns\HttpRouter\IRouteCollection::ROUTE_FOUND, 'Testing simple path');
    }

    public function testMethodNotAllowedRoute() {
        self::$router->post('/demo', 'some');

        $r = self::$router->getRoute('GET', '/demo');
        $this->assertTrue($r[0] == \IOguns\HttpRouter\IRouteCollection::ROUTE_METHOD_NOT_ALLOWED, 'Route method not allowed');
    }

    public function testNotFoundRoute() {
        self::$router->post('/demo', 'some');
        $r = self::$router->getRoute('GET', '/demos');
        $this->assertTrue($r[0] == \IOguns\HttpRouter\IRouteCollection::ROUTE_NOT_FOUND, 'Route not found');
    }

    public function testInvalidRoute() {
        self::$router->get('/demo/{name=any}', 'some');

        $r = self::$router->getRoute('GET', '/demo/any');
        $this->assertTrue($r[0] == \IOguns\HttpRouter\IRouteCollection::ROUTE_FOUND, 'Testing simple path');
    }

    public function testResolveNamedRoute() {
        self::$router->any('/demo/{num=:num}?', 'some', 'name');
        $r = self::$router->resolveNamedRoute('name');
        $this->assertTrue($r === '/demo/');

        $r = self::$router->resolveNamedRoute('name', ['num' => 12]);
        $this->assertTrue($r === '/demo/12');
    }

    public function testGetRoute() {
        self::$router->get('/de mo', 'some', 'name')
                ->post('/user/all.{type=(json|xml)}', ['c' => 'aolsd']);

        $result = self::$router->getRoute('post', '/user/all.json');

        $this->assertTrue($result[0] === \IOguns\HttpRouter\IRouteCollection::ROUTE_FOUND);
        $this->assertTrue($result[1] === ['c' => 'aolsd']);
    }

    public function testGroup() {
        self::$router
                ->addGroup('/p', function (\IOguns\HttpRouter\IRouteCollection $router) {
                    # /p/demo/
                    $router->delete('/demo/', 'some', 'name');

                    $router->addGroup('/p2', function (\IOguns\HttpRouter\IRouteCollection $router) {
                        # /p/p2/demo/
                        $router->get('/demo/', 'some', 'p2_name');

                        $router->addGroup('/{   p=   (a|b)   }', function (\IOguns\HttpRouter\IRouteCollection $router) {
                            # /p/p2/a/demo/
                            $router->get('/demo/', 'some', 'p2name');
                        });
                    });
                })?->addGroup('/p1', function (\IOguns\HttpRouter\IRouteCollection $router) {
                    $router->options('/demo/po', 'some', 'name1');
                })
                ?->addGroup('/ps1/alemo/{p=[a-z]+}', function (\IOguns\HttpRouter\IRouteCollection $router) {
                    $router->get('/de1', 'some', 'name2hash');
                })
                ?->addGroup('/ps1/alemo/{_hash=[a-z]+}', function (\IOguns\HttpRouter\IRouteCollection $router) {
                    $router->post('/de', 'some', 'name1hash');
                    $router->addGroup('/ps1/alemo/{p=[a-z]+}', static function (\IOguns\HttpRouter\IRouteCollection $router) {
                        $router->get('/de1/{d=:any}?', 'some', 'name2_hash');
                    });
                })
                ?->addGroup('/d/{   name   }', function (\IOguns\HttpRouter\IRouteCollection $router) {
                    $router->get('/del', 'some', 'd_name2hash');
                });

        $r = self::$router->resolveNamedRoute('d_name2hash', ['name' => 'demo']);
        $this->assertTrue($r == '/d/demo/del');

        $r = self::$router->resolveNamedRoute('name');
        $this->assertTrue($r == '/p/demo/');

        $r = self::$router->resolveNamedRoute('p2_name');
        $this->assertTrue($r == '/p/p2/demo/');

        $r = self::$router->resolveNamedRoute('p2name', ['p' => 'a']);
        $this->assertTrue($r == '/p/p2/a/demo/');

        $r = self::$router->resolveNamedRoute('name1');
        $this->assertTrue($r == '/p1/demo/po');

        $r = self::$router->resolveNamedRoute('name2hash', ['p' => 'golf']);
        $this->assertTrue($r == '/ps1/alemo/golf/de1');

        $r = self::$router->resolveNamedRoute('name1hash', ['_hash' => 'golf']);
        $this->assertTrue($r == '/ps1/alemo/golf/de');

        $r = self::$router->resolveNamedRoute('name2_hash', ['_hash' => 'golf', 'p' => 'asda', 'd' => 'help']);
        $this->assertTrue($r == '/ps1/alemo/golf/ps1/alemo/asda/de1/help');
    }
}
