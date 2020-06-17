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

use Slim\Environment;
use Slim\Slim;

class WatchTest extends \PhpPerfTools\Tests\CommonTestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PhpPerfTools\Storage\StorageInterface
     */
    protected $dbMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Slim
     */
    protected $appMock;
    
    /**
     * Setup method
     */
    public function setUp()
    {
        parent::setUp();
        $di = \PhpPerfTools\ServiceContainer::instance();

        $this->appMock  = $this->m('\Slim\Slim', array('redirect', 'render', 'urlFor', 'request', 'response', 'flash'));

        $this->dbMock   = $this->m('\PhpPerfTools\Storage\File', array(
            'getAll',
            'getWatchedFunctions',
            'addWatchedFunction',
            'removeWatchedFunction',
            'updateWatchedFunction'
        ));

        $this->appMock->expects(self::any())->method('request')->willReturn($this->requestMock);

        $di['app'] = $di->share(function ($c) {
            return $this->appMock;
        });

        $di['db'] = $di->share(function ($c) {
            return $this->dbMock;
        });

        $this->watches = $di['watchController'];
        $this->app = $di['app'];
    }

    public function testGet()
    {
        $expected = 'getWatchedFunctions return';
        $this->dbMock->expects(self::once())->method('getWatchedFunctions')->willReturn($expected);

        $this->watches->get();
        $result = $this->watches->templateVars();
        $this->assertEquals($expected, $result['watched']);
    }


    /**
     * @param string $method
     * @param array  $payload
     * @dataProvider postDataProvider
     */
    public function testPost($method, $payload)
    {
        $this->preparePostRequestMock(array(
            array('watch', null, $payload),
        ));

        $this->dbMock->expects(self::exactly(2))->method($method);

        $this->appMock->expects(self::once())->method('urlFor');
        $this->appMock->expects(self::once())->method('redirect');

        $this->watches->post();
    }

    public function postDataProvider()
    {
        return array(
            array(
                'updateWatchedFunction',
                array(
                    array('id' => 1, 'name' => 'test'),
                    array('id' => 2, 'name' => 'different test')
                )
            ),
            array(
                'addWatchedFunction',
                array(
                    array('name' => 'test'),
                    array('name' => 'different test')
                )
            ),
            array(
                'removeWatchedFunction',
                array(
                    array('id' => 1, 'removed' => '1'),
                    array('id' => 2, 'removed' => '1')
                )
            ),
        );
    }
}
