<?php
require '../lib/toupti.php';

class App extends Toupti
{
    /**
     * Map URLs to actions
     */
    public $routes = array(
        ''                  => 'index',                     // Default root-route
        'test/:plop'        => 'test',                      // Match :plop as a named parameter
        'test/:plop'        => array('action' => 'test', 
                                     ':plop' => '.*'),
        'foo/:bar/:baz'     => array('action' => 'foo',     // You can match several named params with custom regexps
                                     ':bar'   => '\d+',
                                     ':baz'   => '\w+'),
        'foo2/:bar/:baz'    => array('action' => ':baz',     // You can match several named params with custom regexps
                                     ':bar'   => '\d+',
                                     ':baz'   => '\w+'),
        'foo3/:bar/:baz'    => array('action' => ':baz',     // You can match several named params with custom regexps
                                     ':bar'   => '\d+',
                                     ':baz'   => 'plop|foo|index'),
        'foo4/:bar/:baz'    => array('action' => 'foo'),     // You can match several named params with custom regexps
        'say/:what/to/:who' => 'dialogue',                  // matches hello/\w+/to/\w+
        'say/*/to/*'        => 'dialogue',                  // matches hello/.*/to/.* and store into params['splat'] array
        'say/:what/to/*'    => 'dialogue',                  // combining splat and named params works too...
        ':action'           => ':action',
    );

    protected function before_filter($params)
    {
        // Called before any action.
        $this->render('header.php');
    }
    
    protected function before_foo($params)
    {
        // Called before foo action.
    }
    
    protected function after_foo()
    {
        // Only called after foo()
    }
    
    protected function after_filter()
    {
        // Called after any action.
        $this->render('footer.php');
    }

    public function index()
    {
        $this->render( (string) $this->params);
    }

    public function dialogue($params)
    {
        // true == ($params == $this->params)
        var_dump($params);
        $this->render("dialogue action\n");
    }

    public function foo()
    {
        $this->render(array('file' => 'foo.php', 'params' => $this->params));
    }

    public function test()
    {
        var_dump($this->params);
        $this->render('test action');
    }

    public function plop()
    {
        var_dump($this->params);
        $this->render('plop action');
    }

    public function error_404()
    {
        $this->render('404: file not found.');
    }
}

$toupti = new App();
$toupti->run();
