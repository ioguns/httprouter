<?php

namespace IOguns\HttpRouter\Cache;

class FileCache implements \IOguns\HttpRouter\IRouteCollectionCache {

    private ?string $cache_dir = null;
    private bool $can_cache = false;
    private static \Closure $error_handler;

    public function __construct(?string $cache_dir = null) {

        self::$error_handler ??= static function (): void {

        };

        if (!is_dir($cache_dir)) {
            set_error_handler(self::$error_handler);
            mkdir($cache_dir, 0777, true);
            restore_error_handler();
        }

        if (is_dir($cache_dir)) {
            $this->cache_dir = $cache_dir;
            $this->can_cache = true;
        }
    }

    public function clear(string $cache_key) {
        set_error_handler(self::$error_handler);
        unlink($this->cache_dir . DIRECTORY_SEPARATOR . $cache_key . '.rcache');
        restore_error_handler();
    }

    public function get(string $cache_key): mixed {
        if (!$this->can_cache) {
            return null;
        }

        $file = ($this->cache_dir . DIRECTORY_SEPARATOR . $cache_key . '.rcache');
        if (file_exists($file)) {
            $file_content = include $file;
            if ($file_content[1] > hrtime(true)) {
                return $file_content[0];
            }
        }

        return null;
    }

    public function set(string $cache_key, array $data, int $ttl = 60) {
        if (!$this->can_cache) {
            return null;
        }

        set_error_handler(self::$error_handler);
        $file = $this->cache_dir . DIRECTORY_SEPARATOR . $cache_key . '.rcache';
        file_put_contents($file, '<?php return ' . var_export([$data, hrtime(true) + ($ttl * 1e+9)], true) . ';', LOCK_EX);
        restore_error_handler();
    }

}
