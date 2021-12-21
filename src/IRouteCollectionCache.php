<?php

namespace IOguns\HttpRouter;

interface IRouteCollectionCache {

    function get(string $cache_key): mixed;

    function clear(string $cache_key);

    function set(string $cache_key, array $data, int $ttl = 60);
}
