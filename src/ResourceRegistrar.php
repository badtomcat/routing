<?php
/**
 *
 *  GET	        /photo	                index	    photo.index
    GET	        /photo/create	        create	    photo.create
    POST	    /photo	                store	    photo.store
    GET	        /photo/{photo}	        show	    photo.show
    GET	        /photo/{photo}/edit	    edit	    photo.edit
    PUT/PATCH	/photo/{photo}	        update	    photo.update
    DELETE	    /photo/{photo}	        destroy	    photo.destroy
 */
namespace Badtomcat\Routing;


class ResourceRegistrar
{
    public $defaultWildCard = 'id';
    /**
     * The router instance.
     *
     * @var Router
     */
    protected $router;

    /**
     * The default actions for a resourceful controller.
     *
     * @var array
     */
    protected $resourceDefaults = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    /**
     * The parameters set for this resource instance.
     *
     * @var array|string
     */
    protected $parameters;

    /**
     * The global parameter mapping.
     *
     * @var array
     */
    protected static $parameterMap = [];

    /**
     * Singular global parameters.
     *
     * @var bool
     */
    protected static $singularParameters = true;

    /**
     * The verbs used in the resource URIs.
     *
     * @var array
     */
    protected static $verbs = [
        'create' => 'create',
        'edit' => 'edit',
    ];

    /**
     * Create a new resource registrar instance.
     *
     * @param  Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Route a resource to a controller.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return RouteCollection
     */
    public function register($name, $controller, array $options = [])
    {
        $coll = new RouteCollection();
        if (isset($options['parameters']) && ! isset($this->parameters)) {
            $this->parameters = $options['parameters'];
        }
        $base = $this->getResourceWildcard($options);

        $defaults = $this->resourceDefaults;

        foreach ($this->getResourceMethods($defaults, $options) as $m) {
            if (ucfirst($m) == "Create" || ucfirst($m) == "Store" || ucfirst($m) == "Index")
            {
                $coll->add($this->{'addResource'.ucfirst($m)}($name, $controller, $options));
            }
            else
            {
                $coll->add($this->{'addResource'.ucfirst($m)}($name, $base, $controller, $options));
            }
        }
        return $coll;
    }

    /**
     * Extract the resource and prefix from a resource name.
     *
     * @param  string  $name
     * @return array
     */
    protected function getResourcePrefix($name)
    {
        $segments = explode('/', $name);

        // To get the prefix, we will take all of the name segments and implode them on
        // a slash. This will generate a proper URI prefix for us. Then we take this
        // last segment, which will be considered the final resources name we use.
        $prefix = implode('/', array_slice($segments, 0, -1));

        return [end($segments), $prefix];
    }

    /**
     * Get the applicable resource methods.
     *
     * @param  array  $defaults
     * @param  array  $options
     * @return array
     */
    protected function getResourceMethods($defaults, $options)
    {
        if (isset($options['only'])) {
            return array_intersect($defaults, (array) $options['only']);
        } elseif (isset($options['except'])) {
            return array_diff($defaults, (array) $options['except']);
        }

        return $defaults;
    }

    /**
     * @param Route $route
     * @param array $action
     * @return Route
     */
    protected function fixAction(Route $route,array $action)
    {
        if (isset($action["as"])) {

            $route->name($action["as"]);
        }
        if (isset($action["middleware"])) {
            $route->setMiddlewares($action["middleware"]);
        }
        return $route;
    }

    /**
     * Add the index method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return Route
     */
    protected function addResourceIndex($name, $controller, $options)
    {
        $uri = $this->getResourceUri($name);

        $action = $this->getResourceAction($name, $controller, 'index', $options);

        return $this->fixAction($this->router->get($uri, $action['uses']),$action);
    }

    /**
     * Add the create method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return Route
     */
    protected function addResourceCreate($name, $controller, $options)
    {
        $uri = $this->getResourceUri($name).'/'.static::$verbs['create'];

        $action = $this->getResourceAction($name, $controller, 'create', $options);

        return $this->fixAction($this->router->get($uri, $action['uses']),$action);
    }

    /**
     * Add the store method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return Route
     */
    protected function addResourceStore($name, $controller, $options)
    {
        $uri = $this->getResourceUri($name);

        $action = $this->getResourceAction($name, $controller, 'store', $options);


        return $this->fixAction($this->router->post($uri, $action["uses"]),$action);
    }



    /**
     * Add the show method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return Route
     */
    protected function addResourceShow($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}';

        $action = $this->getResourceAction($name, $controller, 'show', $options);

        return $this->fixAction($this->router->get($uri, $action['uses']),$action);
    }

    /**
     * Add the edit method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return Route
     */
    protected function addResourceEdit($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}/'.static::$verbs['edit'];

        $action = $this->getResourceAction($name, $controller, 'edit', $options);

        return $this->fixAction($this->router->get($uri, $action['uses']),$action);
    }

    /**
     * Add the update method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return Route
     */
    protected function addResourceUpdate($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}';

        $action = $this->getResourceAction($name, $controller, 'update', $options);

        return $this->fixAction($this->router->match(['PUT', 'PATCH'], $uri, $action['uses']),$action);
    }

    /**
     * Add the destroy method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string  $controller
     * @param  array   $options
     * @return Route
     */
    protected function addResourceDestroy($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}';

        $action = $this->getResourceAction($name, $controller, 'destroy', $options);

        return $this->fixAction($this->router->delete($uri, $action['uses']),$action);
    }

    /**
     * Get the base resource URI for a given resource.
     *
     * @param  string  $resource
     * @return string
     */
    public function getResourceUri($resource)
    {
        return '/'.$resource;
    }

//    /**
//     * Get the URI for a nested resource segment array.
//     *
//     * @param  array   $segments
//     * @return string
//     */
//    protected function getNestedResourceUri(array $segments)
//    {
//        // We will spin through the segments and create a place-holder for each of the
//        // resource segments, as well as the resource itself. Then we should get an
//        // entire string for the resource URI that contains all nested resources.
//        return implode('/', array_map(function ($s) {
//            return $s.'/{'.$this->getResourceWildcard($s).'}';
//        }, $segments));
//    }

    /**
     * Format a resource parameter for usage.
     *
     * @param  array  $options
     * @return string
     */
    public function getResourceWildcard($options)
    {
        if (isset($options["wildcard"])) {
            return $options["wildcard"];
        }
        return $this->defaultWildCard;
    }

    /**
     * Get the action array for a resource route.
     *
     * @param  string  $resource
     * @param  string  $controller
     * @param  string  $method
     * @param  array   $options
     * @return array
     */
    protected function getResourceAction($resource, $controller, $method, $options)
    {
        $name = $this->getResourceRouteName($resource, $method, $options);

        $action = ['as' => $name, 'uses' => $controller.'@'.$method];

        if (isset($options['middleware'])) {
            $action['middleware'] = $options['middleware'];
        }

        return $action;
    }

    /**
     * Get the name for a given resource.
     *
     * @param  string  $resource
     * @param  string  $method
     * @param  array   $options
     * @return string
     */
    protected function getResourceRouteName($resource, $method, $options)
    {
        $name = $resource;

        // If the names array has been provided to us we will check for an entry in the
        // array first. We will also check for the specific method within this array
        // so the names may be specified on a more "granular" level using methods.
        if (isset($options['names'])) {
            if (is_string($options['names'])) {
                $name = $options['names'];
            } elseif (isset($options['names'][$method])) {
                return $options['names'][$method];
            }
        }

        // If a global prefix has been assigned to all names for this resource, we will
        // grab that so we can prepend it onto the name when we create this name for
        // the resource action. Otherwise we'll just use an empty string for here.
        $prefix = isset($options['as']) ? $options['as'].'.' : '';

        return trim(sprintf('%s%s.%s', $prefix, $name, $method), '.');
    }

    /**
     * Set or unset the unmapped global parameters to singular.
     *
     * @param  bool  $singular
     * @return void
     */
    public static function singularParameters($singular = true)
    {
        static::$singularParameters = (bool) $singular;
    }

    /**
     * Get the global parameter map.
     *
     * @return array
     */
    public static function getParameters()
    {
        return static::$parameterMap;
    }

    /**
     * Set the global parameter mapping.
     *
     * @param  array $parameters
     * @return void
     */
    public static function setParameters(array $parameters = [])
    {
        static::$parameterMap = $parameters;
    }

    /**
     * Get or set the action verbs used in the resource URIs.
     *
     * @param  array  $verbs
     * @return array
     */
    public static function verbs(array $verbs = [])
    {
        if (!empty($verbs)) {
            static::$verbs = array_merge(static::$verbs, $verbs);
        }
        return static::$verbs;
    }
}
