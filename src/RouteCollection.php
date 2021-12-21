<?php

namespace IOguns\HttpRouter;

class RouteCollection implements IRouteCollection {

    private const VERSION_MAJOR = 1;

    /**
     * Group prefix
     * @var string
     */
    private string $group_prefix = '';

    /**
     * Named rules
     * @var array
     */
    private array $named_rules = [];

    /**
     * Allow HTTP Verbs
     * @var array
     */
    private static array $allowed_http_verbs = ['POST', 'GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    private const TEMPLATE_START_CHAR = '{';
    private const TEMPLATE_END_CHAR = '}';
    private const TEMPLATE_OPTIONAL_CHAR = '?';
    private const HTTP_VERB_SEPERATOR = '|';
    private const TEMPLATE_SEPERATOR_CHAR = '=';
    private const NAMED_REGEX_START_CHAR = ':';

    /**
     * Registered REGEX
     * @var array
     */
    private array $regex = [
        self::NAMED_REGEX_START_CHAR . 'num' => '[\d]+',
        self::NAMED_REGEX_START_CHAR . 'all' => '.+',
        self::NAMED_REGEX_START_CHAR . 'any' => '[^/]+',
        self::NAMED_REGEX_START_CHAR . 'alpha' => '[a-zA-Z]+',
        self::NAMED_REGEX_START_CHAR . 'alnum' => '[a-zA-Z\d]+'
    ];

    /**
     * All Routes
     * @var array
     */
    private array $routes = [-1 => []];

    /**
     * All Routes
     * @var array
     */
    private array $http_routes = [
        'POST' => [-1 => [], 0 => 0],
        'GET' => [-1 => [], 0 => 0],
        'HEAD' => [-1 => [], 0 => 0],
        'PUT' => [-1 => [], 0 => 0],
        'PATCH' => [-1 => [], 0 => 0],
        'DELETE' => [-1 => [], 0 => 0],
        'OPTIONS' => [-1 => [], 0 => 0]
    ];

    /**
     *
     * @var array
     */
    private array $options = [
        'enable_cache' => false,
        'cache_handler' => null
    ];

    /**
     *
     * @var IRouteCollectionCache|null
     */
    private ?IRouteCollectionCache $cache_handler = null;

    /**
     *
     * @var bool
     */
    private bool $enable_cache = false;

    public function __construct(array $options = []) {
        $this->options = array_merge($this->options, $options);

        $this->enable_cache = (bool) $this->options['enable_cache'] ?? false;

        if ($this->enable_cache && ($this->options['cache_handler'] instanceof IRouteCollectionCache)) {
            $this->cache_handler = $this->options['cache_handler'];
        }
    }

    /**
     * Add Route
     * @param array $route
     * @return bool
     */
    private function registerRoute(array $route): bool {

        static $count = 'A';

        $verb = $route[0];

        unset($route[0]);

        //create hash id of the route
        $hash_id = crc32(var_export($route, true));

        if (!isset($this->routes[$hash_id])) {
            ++$count;
            $this->routes[-1][$count] = $hash_id;
        }

        $has_regex = str_contains($route[1], '{');
        if ($has_regex) {
            $result = $this->translateRoutePattern($route[1], $count);

            if ($result === false) {
                return false;
            }

            $route[4] = $result['params'] ?? [];
            $route[5] = $result['opts'];
            $route[6] = $result['regex'];

            unset($result);
        }


        if ($has_regex) {
            $rm = intval($this->http_routes[$verb][0] / 10);

            if (!isset($this->http_routes[$verb][-1][$rm])) {
                $this->http_routes[$verb][-1][$rm] = $route[6];
            } else {
                $this->http_routes[$verb][-1][$rm] .= '|' . $route[6];
            }
            $this->http_routes[$verb][0]++;

            unset($route[6]);
        } else {
            $this->http_routes[$verb][1][$route[1]] = $count;
        }


        $this->routes[$hash_id] = $route;

        if (!empty($route[3])) {
            $this->named_rules[$route[3]] = $hash_id;
        }


        return true;
    }

    /**
     *
     * @inheritDoc
     */
    public function get(string $path, $data, ?string $name = null): ?IRouteCollection {
        return $this->addRoute('GET', $path, $data, $name);
    }

    /**
     *
     * @inheritDoc
     */
    public function head(string $path, $data, ?string $name = null): ?IRouteCollection {
        return $this->addRoute('HEAD', $path, $data, $name);
    }

    /**
     *
     * @inheritDoc
     */
    public function post(string $path, $data, ?string $name = null): ?IRouteCollection {
        return $this->addRoute('POST', $path, $data, $name);
    }

    /**
     *
     * @inheritDoc
     */
    public function put(string $path, $data, ?string $name = null): ?IRouteCollection {
        return $this->addRoute('PUT', $path, $data, $name);
    }

    /**
     *
     * @inheritDoc
     */
    public function patch(string $path, $data, ?string $name = null): ?IRouteCollection {
        return $this->addRoute('PATCH', $path, $data, $name);
    }

    /**
     *
     * @inheritDoc
     */
    public function delete(string $path, $data, ?string $name = null): IRouteCollection {
        return $this->addRoute('DELETE', $path, $data, $name);
    }

    /**
     *
     * @inheritDoc
     */
    public function options(string $path, $data, ?string $name = null): ?IRouteCollection {
        return $this->addRoute('OPTIONS', $path, $data, $name);
    }

    /**
     *
     * @inheritDoc
     */
    public function any(string $path, $data, ?string $name = null): IRouteCollection {
        return $this->addRoute(http_methods: '*', path: $path, data: $data, name: $name);
    }

    /**
     * Add route
     *
     * @param string $http_methods HTTP Request method e.g GET or methods using | to seperate them GET|POST|OPTIONS|HEAD
     * @param string $path Route Path
     * @param mixed $data Any stored data with this route
     * @param string|null $name name to refer to the route
     * @return NULL|IRouteCollection Return a null or this object
     */
    public function addRoute(string $http_methods, string $path, $data,
            ?string $name = null): ?IRouteCollection {
        //check that the path begins with a forward splash '/'
        if ($path[0] != '/') {
            throw new RouteError('Path MUST start with a forward splash "/"');
        }

        $http_methods = strtoupper(trim($http_methods));

        $http_verbs = [];

        if (str_contains($http_methods, '|')) {
            $http_verbs = array_intersect(explode(self::HTTP_VERB_SEPERATOR, $http_methods), self::$allowed_http_verbs);
        } else {
            $http_verbs = [$http_methods];
        }

        // '*' is in the verbs
        if (str_contains($http_methods, '*')) {
            // register all http verbs with route
            $http_verbs = self::$allowed_http_verbs;
        }


        foreach ($http_verbs as $http_verb) {
            $this->registerRoute([$http_verb, $this->group_prefix . $path, $data, $name ? strtolower(trim($name)) : null]);
        }


        return $this;
    }

    /**
     * Load route collection from cache
     *
     * @param string $cache_key Cache key
     * @param bool $delete_old_cache Delete expired route cache files
     * @return bool
     */
    function loadRoutes(string $cache_key, bool $delete_old_cache = false): bool {

        if (!$this->cache_handler) {
            return false;
        }

        if ($data = $this->cache_handler->get($cache_key)) {
            if (self::VERSION_MAJOR === $data[4]) {
                $this->routes = $data[0];
                $this->named_rules = $data[1];
                $this->regex = $data[2];
                $this->http_routes = $data[3];
                return true;
            }
        }

        if ($delete_old_cache) {
            $this->cache_handler->clear($cache_key);
        }

        return false;
    }

    /**
     * Save routes to cache
     *
     * @param string $cache_key Cache key
     * @param int $ttl expiration time
     * @return void
     */
    function saveRoutes(string $cache_key, int $ttl = 60): void {
        if (!$this->cache_handler) {
            return;
        }

        $this->cache_handler->set($cache_key, [$this->routes, $this->named_rules, $this->regex, $this->http_routes, self::VERSION_MAJOR], $ttl);
    }

    /**
     * Has Route
     * @param string $http_verb valid HTTP Method verb
     * @param string $url URI to resolve
     * @return array
     */
    public function getRoute(string $http_verb, string $url): array {

        $search_verb = strtoupper(trim($http_verb));

        //check static in http verb group
        $route_id = $this->http_routes[$search_verb][1][$url] ?? null;

        //check GET if HEAD verb
        if ($search_verb === 'HEAD' && $route_id === null) {
            $route_id = $this->http_routes['GET'][1][$url] ?? null;
        }

        if ($route_id) {

            return [self::ROUTE_FOUND, $this->routes[$this->routes[-1][$route_id]][2], []];
        }

        //check complex route
        if ($route_id == null) {

            foreach ($this->http_routes[$search_verb][-1] as $chunck) {
                $result = $this->processRoute($url, $chunck);
                if ($result[0] == self::ROUTE_FOUND) {
                    return $result;
                }
            }

            if ($search_verb === 'HEAD') {
                foreach ($this->http_routes['GET'][-1] as $chunck) {
                    $result = $this->processRoute($url, $chunck);
                    if ($result[0] == self::ROUTE_FOUND) {
                        return $result;
                    }
                }
            }
        }

        //we are here because we could not find the url in the requested verbs group
        $results = [];

        foreach ($this->http_routes as $http => $routes) {
            if ($http == $search_verb) {
                continue;
            }

            if ('HEAD' == $search_verb && $http == 'GET') {
                continue;
            }

            //static
            if (isset($routes[1][$url])) {
                $results[$http] = true;
                continue;
            }

            foreach ($routes[-1] as $chunck) {
                $result = $this->processRoute($url, $chunck);
                if ($result[0] == self::ROUTE_FOUND) {
                    $results[$http] = true;
                    continue 2;
                }
            }
        }

        if (empty($results)) {
            return [self::ROUTE_NOT_FOUND];
        }

        return [self::ROUTE_METHOD_NOT_ALLOWED, array_keys($results)];
    }

    /**
     * Translate template
     *
     * @param string $template
     * @param int $hash_id
     * @return false|array
     */
    private function translateRoutePattern(string $template, $hash_id): false|array {

        $tmpl = str_replace(array_keys($this->regex), array_values($this->regex), $template);

        $result = preg_match_all('#\{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?:=\s*([^{}]*(?:\{(?-1)\}[^{}]*)*))?\}(\?)?#xs', $tmpl, $matches, PREG_SET_ORDER, 0);
        if (!$result) {
            return false;
        }

        $r = ['params' => [], 'opts' => []];

        $subpattern_names = [];

        //  var_dump($result,$matches);die;
        foreach ($matches as $match) {
            $count = count($match);
            if ($count == 2) {
                $match[2] = $this->regex[':any'];
            }

            //optional
            $opt = ($count == 4);

            //prevent duplicate name pattern
            if (isset($subpattern_names[$match[1]])) {
                throw new RouteError("'$match[1]' already exists in the template. Use unique namess for the templates - '$template'.");
            }

            //add to the template sub pattern names
            $subpattern_names[] = $match[1];

            //set template params
            $r['params'][$match[1]] = null;

            //check that this part is optional
            if ($opt) {
                //register regex name as option
                $r['opts'][] = $match[1];
            }

            $regex = $opt ? "($match[2])?" : "($match[2])";
            $tmpl = str_replace($match[0], $regex, $tmpl);
        }

        $r['regex'] = "$tmpl(*MARK:$hash_id)";

        return $r;
    }

    /**
     * Process the path against the stored rules
     *
     * @param string $url
     * @param string $chuck
     * @return array
     */
    private function processRoute(string $url, string $chuck): array {

        if (!preg_match('#^(?|' . $chuck . ')$#x', $url, $match)) {
            return [self::ROUTE_NOT_FOUND];
        }

        $route = $this->routes[$this->routes[-1][$match['MARK']]];
        unset($match[0], $match['MARK']);

        $i = 0;
        $template = [];
        foreach ($route[4] as $param => $_) {
            $template['{' . $param . '}'] = $route[4][$param] = $match[++$i];
        }

        //substitute the path params within the responder
        if (is_string($route[2])) {
            $route[2] = strtr($route[2], $template);
        }


        return [self::ROUTE_FOUND, $route[2], $route[4] ?? []];
    }

    /**
     * Add custom regex to the router
     * <pre>
     * $router->addRegex('hash','[0-9]{4}[A-Z]{3}[0-9]{4}');
     *
     * usages:
     *
     * $router->any('/product/{hashid=:hash}',...);
     * </pre>
     * @param string $name name of the expression
     * @param string $regex the expression
     * @return null|IRouteCollection
     */
    public function addRouteRegex(string $name, string $regex): ?IRouteCollection {
        if (@preg_match('#^' . $regex . '$#', '') === false) {
            throw new RouteError('Invalid route regex. [' . preg_last_error_msg() . '] ');
        }

        $this->regex[self::NAMED_REGEX_START_CHAR . preg_quote(trim($name), '#')] = $regex;
        return $this;
    }

    /**
     * Check if custom regex exists to the router
     * <pre>
     * $router->hasRegex('hash');
     * </pre>
     * @param string $name name of the expression
     * @return bool
     */
    public function hasRouteRegex(string $name): bool {
        return array_key_exists(self::NAMED_REGEX_START_CHAR . $name, $this->regex);
    }

    /**
     * Translate named REGEX route to its HTTP URL
     *
     * <pre>
     * $router->get('/person/{name=:any}',$callback,'person_name');
     *
     * <b>Usage:</b>
     * $router->resolveNamedRoute('person_name',['name'=>'the_name]);
     *
     * <b>Result:</b>ni
     * /person/the_name
     * </pre>
     *
     * @param string $name REGEX named route
     * @param array $params  parameters to pass to the route
     * @return ?string
     */
    public function resolveNamedRoute(string $name, array $params = [],
            ?string $default = null): ?string {
        $k = strtolower($name);
        if (array_key_exists($k, $this->named_rules)) {

            $rule = &$this->routes[$this->named_rules[$k]];
            //copy the path
            $route = $rule[1];

            if (str_contains($route, '{')) {

                //params can not be empty when regex is available and optional is empty
                if (empty($params) && empty($rule[5])) {
                    return $default;
                }

//                $route = $rule['path'];
                //to avoid infinite loop
                $counter = substr_count($route, self::TEMPLATE_START_CHAR);

                while (($start = strpos($route, self::TEMPLATE_START_CHAR)) !== false) {
                    $end = strpos($route, self::TEMPLATE_END_CHAR);
                    $tmp = substr($route, $start, ($end + 1) - $start);

                    $opt = false;
                    //is this segment optional
                    if (substr($route, ($end + 1), 1) === self::TEMPLATE_OPTIONAL_CHAR) {
                        $opt = true;
                    }

                    $_regex = ':any';
                    if (str_contains($tmp, self::TEMPLATE_SEPERATOR_CHAR)) {
                        list($_name, $_regex) = explode(self::TEMPLATE_SEPERATOR_CHAR, trim(str_replace([self::TEMPLATE_START_CHAR, self::TEMPLATE_END_CHAR], '', $tmp)), 2);
                    } else {
                        $_name = trim(str_replace([self::TEMPLATE_START_CHAR, self::TEMPLATE_END_CHAR], '', $tmp));
                    }

                    $regex_name = preg_quote(trim($_name), '#');
                    $regex = trim($_regex);

                    $tmp_regex = null;

                    //if regex begins with : then it might be a registered regex
                    if ($regex[0] === self::NAMED_REGEX_START_CHAR && array_key_exists($regex, $this->regex)) {
                        $tmp_regex = $this->regex[$regex];
                    }

                    $re = ($tmp_regex !== null) ? $tmp_regex : $regex;

                    //is optional
                    if ($opt) {
                        $tmp .= self::TEMPLATE_OPTIONAL_CHAR;
                    }

                    //if name exists in the params
                    if (array_key_exists($regex_name, $params)) {
                        if (preg_match("#^{$re}$#", $params[$regex_name]) === 1) {
                            $route = str_replace($tmp, $params[$regex_name], $route);
                        }
                    }

                    //if names doesn't exist but the regex is optional
                    if (!array_key_exists($regex_name, $params) && $opt) {
                        $route = str_replace($tmp, '', $route);
                    }

                    //prevent infinite loop
                    if ($counter == -1) {
                        $route = $default;
                        break;
                    }

                    $counter--;
                }
            }

            return $route;
        }
        return $default;
    }

    /**
     * Add Route Group
     * @param string $group_prefix
     * @param callable $callable
     * @return null|IRouteCollection
     */
    public function addGroup(string $group_prefix, callable $callable): ?IRouteCollection {

        if ($group_prefix[0] != '/') {
            throw new RouteError("Group prefix must begin with a '/'");
        }
        //make copy of the current prefix
        $copy_group_prefix = $this->group_prefix;

        $this->group_prefix .= $group_prefix;

        $callable($this);

        //restore group prefix
        $this->group_prefix = $copy_group_prefix;

        return $this;
    }

}
