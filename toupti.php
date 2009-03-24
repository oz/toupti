<?php
/* Copyright (c) 2009, Arnaud Berthomier
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the University of California, Berkeley nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHORS AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHORS AND CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Toupti: yet another micro-framework made out of PHP.
 *
 * @author  Arnaud Berthomier <oz@cyprio.net>
 */
class Toupti
{
    /**
     * Application routes config.
     */
    public $routes = array();

    /**
     * Application views templates config.
     */
    public $template_path = 'template';

    /**
     * Parameters from _GET, _POST, and defined routes.
     */
    protected $params = array();

    /**
     * The action we'll want to run...
     */
    protected $action = null;

    /**
     * Routing setup
     */
    private $_routes = array();

    /**
     * Internal template path
     */
    private $_template_path = null;

    /**
     * Toupti constructor
     */
    public function __construct()
    {
        // So we know where we are...
        $this->app_root = dirname(__FILE__) . '/..';

        // Check template path
        $this->_template_path = $this->app_root . '/' . $this->template_path;
        if ( ! file_exists($this->_template_path) ||
             ! is_dir($this->_template_path) )
        {
            throw new Exception("Invalid template path", true);
        }

        // Read user routes, and set-up internal dispatcher
        $this->setup_routes();
    }

    /**
     * Dispatch browser query to the appropriate action.
     * This our "main" entry point.
     * @return void
     */
    public function run()
    {
        // Find an action for the query, and set params accordingly.
        list($action, $params) = $this->find_route();

        // Update ourself
        $this->action = $action;

        // Merge route params with POST/GET values
        $params = array_merge($params, $_POST, $_GET);
        $this->params = $params;

        // Dispatch the routed action !
        if ( is_callable(array($this, $action)) )
        {
            return $this->call_action($action, $params);
        }

        // Uh oh...
        if ( method_exists($this, 'error_404') )
        {
            return $this->error_404($action);
        }

        // I tried hard, but nothing worked..
        throw new Exception("Sorry, no appropriate action was found.  ".
                            "Furthermore, the 404 handler is not set. ");
    }

    /**
     * Call a user action:
     *  - First, try to call any otherwised defined filter,
     *  - Then, call the user action.
     *  - Finally, call any post filter that is callable.
     *
     * @param  string   Name of the action to call
     * @param  array    Request parameters
     * @return mixed    User-defined action's return value
     */
    private function call_action($action, $params)
    {
        $return_value = null;
        $callables = array( "before_filter",
                            "before_$action",
                            $action,
                            "after_$action",
                            "after_filter"
        );

        foreach ( $callables as $callable )
        {
            if ( $calable == $action )
            {
                $return_value = $this->$callable($params);
            }
            elseif ( is_callable(array($this, $callable)) )
            {
                $this->$callable($params);
            }
        }
        return $return_value;
    }

    /**
     * Get caller function name from a PHP debug backtrace
     * @param  mixed    $backtrace  A PHP debug_backtrace() return value
     * @return string   Caller function name from backtrace
     * @return null     null if no function is found in $backtrace
     */
    private function get_template_from_backtrace($backtrace = null)
    {
        if ( is_array($backtrace) &&
             isset($backtrace[1]) &&
             !empty($backtrace[1]['function']) )
        {
            return $backtrace[1]['function'];
        }
        return null;
    }

    /**
     * Verifiy wether a string is a valid template or not.
     * @param  string   filename
     * @return boolean  true if $file is an exising template file
     */
    private function is_template($file = null)
    {
        if ( null === $file )
            return false;
        return file_exists($this->_template_path . '/' . $file);
    }
    
    /**
     * Redirect to another path, and stops un
     * @param  string    $path          Redirects to $path
     * @param  boolean   $we_are_done   Stops PHP if true (defaults to true)
     * @return void
     */
    protected function redirect_to($path = '', $we_are_done = true)
    {
        header("Location: $path");
        if ( $we_are_done )
            exit(0);
    }

    /**
     * Render text, or template.
     *
     * FIXME Must think about how to do rendering cleanly :)
     * 
     * $this->render(array('file' => 'template.haml'));
     *    should fail if template.haml is missing
     *
     * $this->render('template.haml');
     *    should show 'template.haml' text if template is missing or render 'template.haml' if it is found
     *
     * $this->render($my_object->to_json());
     *    should show JSON representation of $my_object
     *
     */
    protected function render($args = null)
    {
        $opts = array();
        $explicit_template = false;
        $guessed_template = true;

        // Try to render string
        if ( is_string($args) )
        {
            if ( $this->is_template($args) )
                return $this->render_file($this->_template_path . '/' . $args);

            return $this->render_raw($args);
        }

        /*
         * Try to render a template file:
         * If no args were passed ? Try to guess a template name
         */
        $guessed_file = $this->get_template_from_backtrace(debug_backtrace());
        if ( is_null($args) && $this->is_template($guessed_file) )
        {
            return $this->render_file($guessed_file);
        }

        /*
         * if $args is an array, then try to render with more
         * options, and use $args as a simple store for interpolated
         * template values.
         */
        if ( is_array($args) )
        {
            // Explicit template name
            if ( isset($args['file']) )
            {
                if ( ! $this->is_template($args['file']) )
                    throw new Exception("Required template (" .
                        $args['file'] . ") is missing in action " .
                        $guessed_file, true);

                $file = $this->_template_path . '/' . $args['file'];
                return $this->render_file($file, $args);
            }
            
            // No explicit template name, use guessed name
            if ( ! $this->is_template($guessed_file) )
                throw new Exception("Required template is missing in action " .
                    $guessed_file, true);

            return $this->render_file($this->_template_path . '/' . $guessed_file, $args);
        }
        throw new Exception("Required template is missing in action " . $guessed_file, true);
    }

    /**
     * Render file.
     * @param  string   $filename   Name of the file to render
     * @param  array    $bindings   User bound variables
     * @return void
     */
    protected function render_file($filename, $bindings = array())
    {
        ob_start();
        $v = $bindings;
        require $filename;
        ob_end_flush();
    }

    /**
     * Render raw text
     */
    protected function render_raw($text, $format = 'text/plain')
    {
        echo $text;
    }

    /**
     * Setup default routes unless user defined them himself,
     * then add this routes to the dispatcher.
     */
    private function setup_routes()
    {
        // Setuip routing scheme
        if ( empty($this->routes) )
        {
            $this->routes = array('' => 'index', ':action' => ':action');
        }

        // Feed the dispatcher
        foreach ( $this->routes as $path => $scheme )
        {
            $this->add_route($path, $scheme);
        }
    }

    /**
     * Add a new route to the internal dispatcher.
     *
     * @param  String  $path    Route path : a key from the user's routes
     * @param  mixed   $scheme  Which action to take for this $path
     * @return Void
     */
    private function add_route($path, $scheme)
    {
        $scheme_array = array();
        $route = array('path'   => $path,
                       'rx'     => '',
                       'action' => null);

        // Scheme can be either a string, or an associative array.
        $scheme_array = is_array($scheme) ? $scheme : array('action' => $scheme);

        if ( empty($scheme_array['action']) )
        {
            throw new Exception('Invalid route for path: ' . $path, true);
        }

        // Escape path for rx (XXX use preg_quote ?)
        $rx = str_replace('/', '\/', $path);
        
        // named path
        if ( strstr($path, ':') )
        {
            $matches = null;

            if ( preg_match_all('/:\w+/', $rx, $matches) )
            {
                foreach ( $matches[0] as $match )
                {
                    $group = isset($scheme_array[$match]) ? $scheme_array[$match] : '\w+';
                    $rx = preg_replace('/'.$match.'/', '('.$group.')', $rx);
                }
            }
        }

        // splat path
        if ( strstr($path, '*') )
        {
            $matches = null;

            if ( preg_match_all('/\*/', $rx, $matches) )
            {
                $rx = str_replace('*', '(.*)', $rx);
            }
        }
        $route['rx'] = '\/' . $rx . '\/?';
        $route['action'] = $scheme_array['action'];

        // Add new route
        $this->_routes []= $route;
    }

    /**
     * Try to map browser request to one of the defined routes
     *
     * @return  Array   [0] => 'action name', [1] => array( params... )
     */
    private function find_route()
    {
        list($action, $params) = array(null, array());

        // Get the query string without the eventual GET parameters passed.
        $query = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if ( $offset = strpos($query, '?') )
        {
            $query = substr($query, 0, $offset);
        }

        // Try each route
        foreach ( $this->_routes as $route )
        {
            $rx = '/^' . $route['rx'] . '$/';
            $matches = array();

            // Found a match ?
            if ( preg_match($rx, $query, $matches) )
            {
                $params = array();

                if ( count($matches) == 1 )
                {
                    $action = $route['action'];
                }
                else
                {
                    $params = $this->get_route_params($matches, $route);
                    $action = $params['action'];
                    unset($params['action']);     // don't pollute $params
                }
                break;
            }
        }
        return array($action, $params);
    }

    /**
     * Extract params from the request with the corresponding path matches
     *
     * @param   Array    $matches    preg_match $match array
     * @param   Array    $route      corresponding route array
     * @return  Array    Hash of request values, with param names as keys.
     */
    private function get_route_params($matches, $route)
    {
        $params      = array();
        $path_parts  = array();
        $param_count = 0;
        $path_array  = explode('/', $route['path']);

        // Handle each route modifier...
        foreach ( $path_array as $param_name )
        {
            // Handle splat parameters (regexps like '.*')
            if ( substr($param_name, 0, 1) == '*' )
            {
                ++$param_count;
                if ( ! isset($params['splat']) ) $params['splat'] = array();
                $params['splat'] []= $matches[$param_count];
                continue;
            }

            // Don't treat non-parameters as parameters
            if ( substr($param_name, 0, 1) != ":" )
            {
                $path_parts []= $param_name;
                continue;
            }

            // Extract param value
            ++$param_count;
            if ( isset($matches[$param_count]) )
            {
                $name = substr($param_name, 1, strlen($param_name));
                $params[$name] = $matches[$param_count];
            }
        }

        if ( !array_key_exists('action', $params) )
        {            
            // This permits the value of a :named_match to be the routed action
            if ( $route['action'][0] == ':' )
            {
                $key = substr($route['action'], 1, strlen($route['action']));

                if ( array_key_exists($key, $params) )
                    $params['action'] = $params[$key];
            }

            /*
             * Check for an explicit action-name in route, if
             * no :action parameter was found inside the route rx.
             */
            if ( empty($params['action']) )
            {
                $params['action'] = $route['action'];
            }
        }
        return $params;
    }
}
