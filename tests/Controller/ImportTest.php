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

namespace PhpPerfTools\Tests\Controller;

use Slim\Http\Response;
use Slim\Slim;

class ImportTest extends \PhpPerfTools\Tests\CommonTestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|StorageInterface
     */
    protected $dbMock;
    
    /**
     * @var \PhpPerfTools\Controller\Import
     */
    protected $object;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Response
     */
    protected $responseMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Slim\Slim
     */
    protected $appMock;

    /**
     * @var Slim
     */
    protected $app;

    /**
     * Common setup
     */
    public function setUp()
    {
        parent::setUp();
        $di = \PhpPerfTools\ServiceContainer::instance();

        $this->appMock = $this->m('Slim\Slim', array(
            'redirect',
            'render',
            'urlFor',
            'request',
            'response',
            'flash',
            'flashData'
        ));

        $this->appMock->expects(self::any())->method('request')->willReturn($this->requestMock);
        $this->appMock->expects(self::any())->method('response')->willReturn($this->responseMock);

        $di['db'] = $di->share(function ($c) {
            return $this->dbMock;
        });

        $di['app'] = $di->share(function ($c) {
            return $this->appMock;
        });

        $this->object = $di['importController'];
        $this->app = $di['app'];

        $this->app->container = $this->m('\Slim\Helper\Set', array('get'));
    }

    /**
     * Test index action and make sure all handlers are present in UI
     */
    public function testIndex()
    {
        $this->object->index();
        $result = $this->object->templateVars();
        self::assertArrayHasKey('configured_handlers', $result);
    }

    /**
     * Test import action
     */
    public function testImport()
    {
        $saverMock = $this->m('\PhpPerfTools\Saver', array('create'));
        $storageFactoryMock = $this->m('\PhpPerfTools\Storage\Factory', array('create'));

        $this->object->setSaver($saverMock);
        $this->object->setStorageFactory($storageFactoryMock);

        $this->prepareGetRequestMock(array(
            array('source', null, 'file'),
            array('target', null, 'pdo'),
        ));

        \PhpPerfTools\Config::write('handlers', array(
            array(
                'id' => 'file',
                'type' => 'file'
            ),
            array(
                'id' => 'pdo',
                'type' => 'pdo',
                'dsn' => 'test'
            ),
        ));

        $reader = $this->m('\PhpPerfTools\Storage\File', array('find'));
        $saver  = $this->m('\PhpPerfTools\Saver\File', array('save'));

        $storageFactoryMock->expects(self::once())->method('create')->willReturn($reader);
        $saverMock->expects(self::once())->method('create')->willReturn($saver);

        $reader->expects(self::exactly(2))->method('find')->willReturnOnConsecutiveCalls(
            array(
                array('row1'),
                array('row2'),
            ),
            array()
        );

        $saver->expects(self::exactly(2))->method('save');

        $this->object->import();
    }
}
