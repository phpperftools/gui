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

namespace PhpPerfTools\Controller;

use PhpPerfTools\Controller;
use PhpPerfTools\Storage\WatchedFunctionsStorageInterface;
use Slim\Slim;

/**
 * Class Watch
 */
class Watch extends Controller
{
    /**
     * @var WatchedFunctionsStorageInterface
     */
    protected $watches;

    /**
     * \PhpPerfTools\Controller\Watch constructor.
     * @param Slim $app
     * @param WatchedFunctionsStorageInterface $watches
     */
    public function __construct(Slim $app, WatchedFunctionsStorageInterface $watches)
    {
        parent::__construct($app);
        $this->setWatches($watches);
    }

    /**
     * List watched functions
     */
    public function get()
    {
        $watched = $this->getWatches()->getWatchedFunctions();
        $this->_template = 'watch/list.twig';
        $this->set(array(
            'watched' => $watched,
            'show_handler_select' => true,
        ));
    }

    /**
     * Update watched functions
     */
    public function post()
    {
        $watches = $this->watches;

        $saved = false;
        $request = $this->app->request();
        foreach ((array)$request->post('watch') as $data) {

            $options = !empty($data['options']) ? $data['options'] : array();

            if (empty($data['id'])) {
                $watches->addWatchedFunction($data['name'], $options);
            } elseif (!empty($data['removed']) && $data['removed'] === '1') {
                $watches->removeWatchedFunction($data['id']);
            } else {
                $watches->updateWatchedFunction($data['id'], $data['name'], $options);
            }
            $saved = true;
        }
        if ($saved) {
            $this->app->flash('success', 'Watch functions updated.');
        }
        $this->app->redirect($this->urlFor('watch.list'));
    }

    /**
     * Add new watched function by url ( used from symbol view )
     */
    public function add()
    {
        $watches = $this->watches;
        $request = $this->app->request();
        $watchedFunction = $watches->getWatchedFunctionByName($request->get('symbol'));

        if (empty($watchedFunction)) {
            $this->watches->addWatchedFunction($request->get('symbol'));
        }

        $this->app->flash('success', 'Watch functions added.');
        $this->app->redirect($this->urlFor('run.symbol', array(
            'id' => $request->get('id'),
            'symbol' => $request->get('symbol')
        )));
    }

    /**
     * Remove watched function by id
     */
    public function remove()
    {
        $request = $this->app->request();
        $this->watches->removeWatchedFunction($request->get('watch'));
        $this->app->redirect($this->urlFor('run.symbol', array(
            'symbol' => $request->get('symbol'),
            'id' => $request->get('id'),
        )));
    }

    /**
     * @return WatchedFunctionsStorageInterface
     */
    public function getWatches()
    {
        return $this->watches;
    }

    /**
     * @param WatchedFunctionsStorageInterface $watches
     */
    public function setWatches($watches)
    {
        $this->watches = $watches;
    }
}
