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

use PhpPerfTools\Config;
use PhpPerfTools\Controller\Import;
use PhpPerfTools\Controller\Run;
use PhpPerfTools\Controller\User;
use PhpPerfTools\Controller\Watch;
use PhpPerfTools\Controller\Waterfall;
use PhpPerfTools\Middleware\Render;
use PhpPerfTools\Saver;
use PhpPerfTools\Storage\Factory;
use PhpPerfTools\Twig\Extension;
use Slim\Slim;
use Slim\Views\Twig;
use Slim\Middleware\SessionCookie;

class ServiceContainer extends \Pimple
{
    protected static $_instance;

    /**
     * @return self
     */
    public static function instance()
    {
        if (empty(static::$_instance)) {
            static::$_instance = new self();
        }
        return static::$_instance;
    }

    /**
     * PhpPerfTools_ServiceContainer constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this['config'] = Config::all();
        $this->_slimApp();
        $this->_services();
        $this->_controllers();
    }

    /**
     * Create the Slim app.
     */
    protected function _slimApp()
    {
        $this['view'] = function ($c) {
            $cacheDir = isset($c['config']['cache']) ? $c['config']['cache'] : PHPPERFTOOLS_ROOT_DIR . '/cache';

            // Configure Twig view for slim
            $view = new Twig();

            $view->twigTemplateDirs = array(dirname(__DIR__) . '/templates');
            $view->parserOptions = array(
                'charset' => 'utf-8',
                'cache' => $cacheDir,
                'auto_reload' => true,
                'debug' => true,
                'strict_variables' => false,
                'autoescape' => true
            );

            return $view;
        };

        $this['app'] = $this->share(function ($c) {
            $app = new Slim($c['config']);

            // Enable cookie based sessions
            $app->add(new SessionCookie(array(
                'httponly' => true,
            )));

            // Add renderer.
            $app->add(new Render());

            $view = $c['view'];
            $view->parserExtensions = array(
                new Extension($app)
            );
            $app->view($view);

            return $app;
        });
    }

    /**
     * Add common service objects to the container.
     */
    protected function _services()
    {
        $this['db'] = $this->share(function ($c) {
            return Factory::factory(Config::getCurrentHandlerConfig());
        });

        $this['watchFunctions'] = function ($c) {
            return $c['db'];
        };

        $this['users'] = function ($c) {
            return $c['db'];
        };

        $this['profiles'] = function ($c) {
            return new Profiles($c['db']);
        };

        $this['storageFactory'] = function ($c) {
            return new Factory();
        };
    }

    /**
     * Add controllers to the DI container.
     */
    protected function _controllers()
    {
        $this['watchController'] = function ($c) {
            return new Watch($c['app'], $c['watchFunctions']);
        };

        $this['runController'] = function ($c) {
            return new Run($c['app'], $c['profiles'], $c['watchFunctions']);
        };

        $this['waterfallController'] = function ($c) {
            return new Waterfall($c['app'], $c['profiles']);
        };

        $this['importController'] = function ($c) {
            return new Import($c['app'], new Factory(), new Saver());
        };

        $this['userController'] = function ($c) {
            return new User($c['app'], $c['db']);
        };
    }

}
