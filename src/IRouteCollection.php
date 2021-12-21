<?php

namespace IOguns\HttpRouter;

interface IRouteCollection {

    /**
     * Route not found
     */
    public const ROUTE_NOT_FOUND = 0;

    /**
     * Route found
     */
    public const ROUTE_FOUND = 1;

    /**
     * Route is found but the method is not allowed
     */
    public const ROUTE_METHOD_NOT_ALLOWED = 2;

    /**
     * Define GET HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function get(string $path, $data, ?string $name = null): ?IRouteCollection;

    /**
     * Define POST HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function post(string $path, $data, ?string $name = null): ?IRouteCollection;

    /**
     * Define OPTIONS HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function options(string $path, $data, ?string $name = null): ?IRouteCollection;

    /**
     * Define HEAD HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function head(string $path, $data, ?string $name = null): ?IRouteCollection;

    /**
     * Define DELETE HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function delete(string $path, $data, ?string $name = null): ?IRouteCollection;

    /**
     * Define PUT HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function put(string $path, $data, ?string $name = null): ?IRouteCollection;

    /**
     * Define PATCH HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function patch(string $path, $data, ?string $name = null): ?IRouteCollection;

    /**
     * Define any valid HTTP Method route
     *
     * @param string $path
     * @param mixed $data
     * @param string|null $name
     * @return ?IRouteCollection
     */
    public function any(string $path, $data, ?string $name = null): ?IRouteCollection;

    public function getRoute(string $http_verb, string $url): ?array;

    public function addRouteRegex(string $name, string $regex): ?IRouteCollection;

    public function hasRouteRegex(string $name): bool;

    public function resolveNamedRoute(string $name, array $params = [],
            ?string $default = null): ?string;

    public function addGroup(string $group_prefix, callable $callable): ?IRouteCollection;
}
