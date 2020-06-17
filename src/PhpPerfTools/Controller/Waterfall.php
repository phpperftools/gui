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
use PhpPerfTools\Profiles;
use PhpPerfTools\Storage\Filter;
use Slim\Slim;

class Waterfall extends Controller
{
    /**
     * @var PhpPerfTools_Profiles
     */
    protected $profiles;

    /**
     * \PhpPerfTools\Controller\Waterfall constructor.
     * @param Slim $app
     * @param Profiles $profiles
     */
    public function __construct(Slim $app, Profiles $profiles)
    {
        parent::__construct($app);
        $this->profiles = $profiles;
    }

    /**
     * List profiles in waterfall style
     */
    public function index()
    {
        $request = $this->app->request();
        $filter = Filter::fromRequest($request);

        $result = $this->profiles->getAll($filter);

        $paging = array(
            'total_pages' => $result['totalPages'],
            'page' => $result['page'],
            'sort' => 'asc',
            'direction' => $result['direction']
        );

        $this->_template = 'waterfall/list.twig';
        $this->set(array(
            'runs' => $result['results'],
            'search' => $filter->toArray(),
            'paging' => $paging,
            'base_url' => 'waterfall.list',
            'show_handler_select' => true,
        ));
    }

    public function query()
    {
        $request = $this->app->request();
        $response = $this->app->response();
        $filter = Filter::fromRequest($request);

        $result = $this->profiles->getAll($filter);

        $data = array();
        /** @var Profile $r */
        foreach ($result['results'] as $r) {
            $duration = $r->get('main()', 'wt');
            $start = $r->getMeta('SERVER.REQUEST_TIME_FLOAT');
            $title = $r->getMeta('url');
            $data[] = array(
                'id' => (string)$r->getId(),
                'title' => $title,
                'start' => $start * 1000,
                'duration' => $duration / 1000 // Convert to correct scale
            );
        }
        $response->body(json_encode($data));
        $response->headers->set('Content-Type', 'application/json');
    }

}
