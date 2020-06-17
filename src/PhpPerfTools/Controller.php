<?php
/*
 * Original code Copyright 2013 Mark Story & Paul Reinheimer
 * Changes Copyright Grzegorz Drozd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace PhpPerfTools;

use Slim\Slim;

abstract class Controller
{
    /**
     * @var array
     */
    protected $_templateVars = array();

    /**
     * @var string|null
     */
    protected $_template = null;

    /**
     * @var Slim
     */
    protected $app;

    /**
     * \PhpPerfTools\Controller constructor.
     * @param Slim $app
     */
    public function __construct(Slim $app)
    {
        $this->app = $app;
    }

    /**
     * Set template variables
     *
     * @param $vars
     */
    public function set(array $vars = array())
    {
        $this->_templateVars = array_merge($this->_templateVars, $vars);
    }

    /**
     * Get all defined template variables
     *
     * @return array
     */
    public function templateVars()
    {
        return $this->_templateVars;
    }

    /**
     * Render template if template name is set.
     */
    public function render()
    {
        // render body only if template is set. Useful for ajax/json response.
        if (!empty($this->_template)) {
            // assign application settings to template variable named config.
            $container = $this->app->container->all();
            $this->_templateVars['config'] = $container['settings'];

            $this->_templateVars['save_handlers'] = Config::read('handlers');
            $this->_templateVars['current_handler'] = Config::getCurrentHandlerConfig();


            // We want to render the specified Twig template to the output buffer.
            // The simplest way to do that is Slim::render, but that is not allowed
            // in middleware, because it uses Slim\View::display which prints
            // directly to the native PHP output buffer.
            // Doing that is problematic, because the HTTP headers set via $app->response()
            // must be output first, which won't happen until after the middleware
            // is completed. Output of headers and body is done by the Slim::run entry point.

            // The below is copied from Slim::render (slim/slim@2.6.3).
            // Modified to use View::fetch + Response::write, instead of View::display.
            $this->app->view->appendData($this->_templateVars);

            $body = $this->app->view->fetch($this->_template);
            $this->app->response->write($body);
        }
    }

    /**
     * @param $name
     * @param array $params
     * @return string
     */
    public function urlFor($name, $params = array())
    {
        $handler = Config::getCurrentHandlerConfig();
        $params['handler'] = $handler['id'];
        $url = $this->app->urlFor($name, $params);
        $url .= '?'.\http_build_query($params);
        return $url;
    }
}
