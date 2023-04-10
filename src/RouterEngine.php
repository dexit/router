<?php

/**
 * This class routes the URL to corrosponding controller.
 *
 * @author : Pranjal Pandey
 */

namespace Scrawler\Router;

use Symfony\Component\HttpFoundation\Request;

class RouterEngine
{
    //---------------------------------------------------------------//

    /**
     * Stores the URL broken logic wise.
     *
     * @var array
     */
    private $path_info = [];

    /**
     * Stores the request method i.e get,post etc.
     *
     * @var string
     */
    private $request_method;

    /**
     * Stores the Request Object
     */
    private $request;

    /**
     * Stores the RouterCollection object.
     */
    private $collection;

    /**
     * Stores the controller being dispatched
     */
    private $controller;

    /**
     * Store the method being dispatched
     */
    private $method;

    /**
     * Stores dir mode
     */
    private $dirMode = false;

    /**
     * Store Dirctory during dir Mode
     */
    private $dir = '';

    /**
     * Stores if not found error occured
     */
    private $not_found = false;

    //---------------------------------------------------------------//

    /**
     * constructor overloading for auto routing.
     */
    public function __construct(Request $request, RouteCollection $collection)
    {
        $this->request = $request;
        $this->collection = $collection;
    }

    //---------------------------------------------------------------//

    /**
     * Detects the URL and call the corrosponding method
     * of corrosponding controller.
     */
    public function route()
    {

        // Get URL and request method.
        $this->request_method = strtolower($this->request->getMethod());

        //Break URL into segments
        $this->path_info = explode('/', $this->request->getPathInfo());

        array_shift($this->path_info);
        $this->setRequestArguments();

        return true;
    }

    //---------------------------------------------------------------//

    /**
     * Set Arguments on the request object.
     */
    private function setRequestArguments()
    {
        $this->getController();
        $this->method = $this->getMethod($this->controller);
        $arguments = $this->getArguments();
        $this->request->attributes->set('_controller', $this->controller . '::' . $this->method);
        $this->request->attributes->set('_arguments', $arguments);
        if ($this->collection->isCacheEnabled()) {
            $this->collection->getCache()->set($this->request_method.'_'.$this->request->getPathInfo(), ['controller'=>$this->controller . '::' . $this->method,'arguments'=>$arguments]);
        }
    }

    //---------------------------------------------------------------//

    /**
     * Function to get namespace
     *
     *@param string $message
     */
    private function getNamespace()
    {
        if ($this->dirMode) {
            return $this->collection->getNamespace() . '\\' . $this->dir;
        }

        return $this->collection->getNamespace();
    }

    //---------------------------------------------------------------//

    /**
     * Function to get controller
     *
     *@param string $message
     */
    private function getController()
    {
        $this->controller = ucfirst($this->path_info[0]);
        

        if (isset($this->path_info[0]) && $this->collection->isDir(ucfirst($this->path_info[0]))) {
            $this->dir = ucfirst($this->path_info[0]);
            $this->dirMode = true;
            if (isset($this->path_info[1])) {
                $this->controller = $this->dir . '/' . ucfirst($this->path_info[1]);
            }
            array_shift($this->path_info);
        }

        //Set corrosponding controller
        if (isset($this->path_info[0]) && !empty($this->path_info[0])) {
            $this->controller = $this->collection->getController($this->controller);
        } else {
            $this->controller = $this->getNamespace() . '\Main';
        }

        //Sets the Request attribute according to the route
        if (!class_exists($this->controller)) {
            $this->controller = $this->getNamespace() . '\Main';
            if (class_exists($this->controller)) {
                array_unshift($this->path_info, '');
            } else {
                $this->error('No Controller could be resolved:' . $this->controller);
            }
        }
    }

    //---------------------------------------------------------------//

    /**
     * Function to throw 404 error.
     *
     *@param string $message
     */
    protected function error($message)
    {
        $this->not_found = true;
        throw new NotFoundException('Oops its an 404 error! :' . $message);
    }

    //---------------------------------------------------------------//

    /**
     * Function to dispach the method if method exist.
     *
     */
    private function getArguments()
    {
        $controller = new $this->controller;

        $arguments = [];
        for ($j = 2; $j < count($this->path_info); $j++) {
            array_push($arguments, $this->path_info[$j]);
        }
        //Check weather arguments are passed else throw a 404 error
        $classMethod = new \ReflectionMethod($controller, $this->method);

        //Optional parameter introduced in version 3.0.2
        if (count($arguments) < count($classMethod->getParameters())) {
            $this->error('Not enough arguments given to the method');
        }
        // finally fix the long awaited allIndex bug !
        elseif (count($arguments) > count($classMethod->getParameters())) {
            $this->error('Not able to resolve '.$this->method. 'for' . $this->controller . 'controller');
        } else {
            return implode(",", $arguments);
        }
    }

    //---------------------------------------------------------------//

    /**
     * Returns the method to be called according to URL.
     *
     * @param string $controller
     *
     * @return string
     */
    private function getMethod($controller)
    {

        //Set Method from second argument from URL
        if (isset($this->path_info[1])) {
            if (method_exists($controller, $function = $this->request_method . ucfirst($this->path_info[1]))) {
                return $function;
            }
            if (method_exists($controller, $function = 'all' . ucfirst($this->path_info[1]))) {
                return $function;
            }
        }

        //Introduced in v2.1.2
        //Give Scrawler last chance to resolve index method before declaring not found
        //Store the last tested function before all index used for better debugging
        // if (!isset($this->path_info[1])) {
        if (isset($function)) {
            $last_function = $function;
        }
        if (method_exists($controller, $function = $this->request_method . 'Index')) {
            array_unshift($this->path_info, '');
            return $function;
        }
        //Last attempt to invoke allIndex
        if (method_exists($controller, $function = 'allIndex')) {
            array_unshift($this->path_info, '');
            return $function;
        }

        if (isset($last_function)) {
            $this->error('Neither ' . $function . ' method nor ' . $last_function . ' method you found in ' . $controller . ' controller');
        } else {
            $this->error($function . ' method not found in ' . $controller . ' controller');
        }
    }

   
}
