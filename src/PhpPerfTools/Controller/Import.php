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

use PhpPerfTools\Config;
use PhpPerfTools\Controller;
use PhpPerfTools\Saver;
use PhpPerfTools\Storage\Factory;
use PhpPerfTools\Storage\Filter;
use Slim\Slim;

/**
 * Import controller.
 *
 * It allows import between handlers.
 */
class Import extends Controller
{
    /**
     * @var Factory
     */
    protected $storageFactory;

    /**
     * @var Saver
     */
    protected $saver;

    /**
     * @param Slim $app
     * @param Factory $storageFactory
     * @param Saver $saver
     */
    public function __construct(Slim $app, Factory $storageFactory, Saver $saver)
    {
        parent::__construct($app);
        $this->setStorageFactory($storageFactory);
        $this->setSaver($saver);
    }

    /**
     * Import controller main page. Used to select source and target.
     */
    public function index()
    {
        $handlers = Config::read('handlers');
        $this->_template = 'import/index.twig';
        $this->set(array(
            'base_url'              => 'home',
            'configured_handlers'   => $handlers,
            'status'                => $this->app->flashData()
        ));
    }

    /**
     * Main import function. It does all the work.
     */
    public function import()
    {
        $request = $this->app->request();
        $this->_template = '';

        $source = $request->get('source', null);
        $target = $request->get('target', null);

        if (empty($source) OR empty($target)) {
            $this->app->redirect($this->urlFor('import', array('msg'=>'missing_options')));
            return false;
        }

        $readHandler = Config::getHandler($source);
        $writeHandler = Config::getHandler($target);

        // get data to import
        $reader = $this->getStorageFactory()->create($readHandler);

        \set_time_limit((int)\PhpPerfTools\Config::read('import_time_limit', 600));

        // get save handler:
        $saver = $this->getSaver()->create($writeHandler);

        try {
            $filter = new Filter();
            $page = 0;
            $filter->setPage($page);
            do {
                $allRows = $reader->find($filter, true);
                foreach ($allRows as $row) {
                    $saver->save($row);
                }

                $filter->setPage($page++);
            } while (count($allRows) > 0);

            $this->app->flash('success', 'Import successful');
        } catch (Exception $e) {
            $this->app->flash('failure', 'Import failed');
        }

        $this->app->redirect($this->urlFor('import', array('handler'=>$target)));
    }

    /**
     * Import one document to storage. Used with upload save handler.
     *
     * Document should contain json in body.
     *
     * @return false|string
     * @throws \Exception
     */
    public function save()
    {
        $request = $this->app->request();
        $this->_template = '';

        $handler = $request->get('handler', null);

        $writeHandler = Config::getHandler($handler);

        $saver = $this->getSaver()->create($writeHandler);

        $currentErrorReporting = \error_reporting();
        \error_reporting(0);

        $document = \json_decode(\file_get_contents('php://input'), true);
        \error_reporting($currentErrorReporting);

        if (empty($document)) {
            return \json_encode(array("status"=>false));
        }

        $saver->save($document);

        return \json_encode(array("status"=>true));
    }

    /**
     * @return Factory
     */
    public function getStorageFactory()
    {
        return $this->storageFactory;
    }

    /**
     * @param Factory $storageFactory
     */
    public function setStorageFactory($storageFactory)
    {
        $this->storageFactory = $storageFactory;
    }

    /**
     * @return Saver
     */
    public function getSaver()
    {
        return $this->saver;
    }

    /**
     * @param Saver $saver
     */
    public function setSaver($saver)
    {
        $this->saver = $saver;
    }
}
