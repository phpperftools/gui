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

class WaterfallTest extends \PhpPerfTools\Tests\CommonTestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PhpPerfTools\Storage\StorageInterface
     */
    protected $dbMock;

    /**
     * @var \PhpPerfTools\Controller\Waterfall
     */
    protected $object;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PhpPerfTools\Profiles
     */
    protected $profilesMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Response
     */
    protected $responseMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Slim
     */
    protected $appMock;

    /**
     * @return mixed|void
     */
    public function setUp()
    {
        parent::setUp();
        $di = \PhpPerfTools\ServiceContainer::instance();

        $this->profilesMock = $this->m('\PhpPerfTools\Profiles', array(
            'getAll',
        ));

        $this->appMock = $this->m('\Slim\Slim', array('redirect', 'render', 'urlFor', 'request', 'response', 'flash'));

        $this->dbMock = $this->m('\PhpPerfTools\Storage\File', array('getAll', 'getWatchedFunctions'));

        $this->appMock->expects(self::any())->method('request')->willReturn($this->requestMock);
        $this->appMock->expects(self::any())->method('response')->willReturn($this->responseMock);

        $di['db'] = $di->share(function ($c) {
            return $this->dbMock;
        });

        $di['app'] = $di->share(function ($c) {
            return $this->appMock;
        });

        $di['profiles'] = $di->share(function ($c) {
            return $this->profilesMock;
        });

        $this->object = $di['waterfallController'];
        $this->app = $di['app'];
    }

    /**
     *
     */
    public function testIndex()
    {
        $this->profilesMock->expects(self::once())->method('getAll')->willReturn(array(
            'totalPages' => 1,
            'page' => 1,
            'direction' => 'asc',
            'results' => array('results'),
        ));

        $this->object->index();
        $result = $this->object->templateVars();

        self::assertArrayHasKey('runs', $result);
        self::assertArrayHasKey('search', $result);
        self::assertArrayHasKey('paging', $result);
        self::assertArrayHasKey('base_url', $result);

        self::assertSame(array('results'), $result['runs']);
    }

    /**
     *
     */
    public function testQuery()
    {
        $profile = $this->m('\PhpPerfTools\Profile', array('get', 'getMeta', 'getId'));

        $profile->expects(self::once())->method('get')->willReturn(10);
        $profile->expects(self::exactly(2))
                ->method('getMeta')
                ->willReturnOnConsecutiveCalls(1, 'url');
        $profile->expects(self::once())->method('getId')->willReturn('id');


        $this->responseMock->expects(self::once())->method('body')->with($this->callback(function ($resp) {
            return '[{"id":"id","title":"url","start":1000,"duration":0.01}]' === $resp;
        }));

        $this->profilesMock->expects(self::once())->method('getAll')->willReturn(array(
            'totalPages' => 1,
            'page' => 1,
            'direction' => 'asc',
            'results' => array(
                $profile
            ),
        ));

        $this->object->query();
    }
}
