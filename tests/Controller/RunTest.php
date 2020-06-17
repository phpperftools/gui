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

class RunTest extends \PhpPerfTools\Tests\CommonTestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PhpPerfTools\Storage\StorageInterface
     */
    protected $dbMock;

    /**
     * @var \PhpPerfTools\Controller\Run
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
            'get',
            'getPercentileForUrl',
            'getRelatives',
            'delete',
            'truncate'
        ));

        $this->appMock = $this->m('\Slim\Slim', array('redirect', 'render', 'urlFor', 'request', 'response', 'flash'));

        $this->dbMock = $this->m('\PhpPerfTools\Storage\File', array('getAll', 'getWatchedFunctions', 'getWatchedFunctionByName'));

        $this->appMock->expects(self::any())->method('request')->willReturn($this->requestMock);
        $this->appMock->expects(self::any())->method('response')->willReturn($this->responseMock);

        $di['db'] = $di::share(function ($c) {
            return $this->dbMock;
        });

        $di['app'] = $di::share(function ($c) {
            return $this->appMock;
        });

        $di['profiles'] = $di::share(function ($c) {
            return $this->profilesMock;
        });

        $this->object = $di['runController'];
        $this->app = $di['app'];

        $this->object->setWatches($this->dbMock);
    }

    /**
     */
    public function testIndexEmpty()
    {
        $sort = 'time';
        $this->profilesMock->expects(self::once())->method('getAll')->willReturn(array(
            'totalPages' => 1,
            'page' => 1,
            'direction' => $sort,
            'results' =>  array(),
            'has_search' => 'No search being done.'
        ));

        $this->prepareGetRequestMock(array('sort', 'time'));

        $this->object->index();
        $result = $this->object->templateVars();

        static::assertEquals('Recent runs', $result['title']);
        $expected = array(
            'total_pages' => 1,
            'sort' => null,
            'page' => 1,
            'direction' => $sort,
        );
        static::assertEquals($expected, $result['paging']);
    }

    /**
     *
     */
    public function testIndexWithSearch()
    {
        $this->prepareGetRequestMock(array('sort', 'time'));

        $this->object->index();
        $result = $this->object->templateVars();
        static::assertEquals('Recent runs', $result['title']);
        static::assertEquals(null, $result['paging']['sort']);
        static::assertArrayHasKey('url', $result['search']);
        static::assertEquals('testUrl', $result['search']['url']);
    }

    /**
     *
     */
    public function testView()
    {
        $id = 1;
        $this->prepareGetRequestMock(array(
            array('id', null, $id),
            array(\PhpPerfTools\Controller\Run::FILTER_ARGUMENT_NAME, false, false),
        ));

        // mocked result set.
        $profileMock = $this->m('\PhpPerfTools\Profile', array(
            'calculateSelf',
            'extractDimension',
            'getWatched',
            'getProfile',
            'sort'
        ));

        $profileMock->expects(self::any())->method('calculateSelf');
        $profileMock->expects(self::exactly(2))->method('extractDimension')->willReturnOnConsecutiveCalls(
            'ewt_values',
            'emu_values'
        );

        // watched functions
        $profileMock->expects(self::any())->method('sort')->willReturnSelf();

        $this->dbMock->expects(self::any())->method('getWatchedFunctions')->willReturn(array());

        // merging matched function
        $profileMock->expects(self::any())
                    ->method('getWatched')
                    ->with(static::stringContains('testWatchedFunction'))
                    ->willReturn(array('test'));

        $this->profilesMock->expects(self::any())->method('get')->willReturn($profileMock);

        // run controller action
        $this->object->view();
        // get results passed to template
        $result = $this->object->templateVars();

        self::assertSame($profileMock, $result['profile']);
        self::assertSame($profileMock, $result['result']);
        self::assertSame('ewt_values', $result['wall_time']);
        self::assertSame('emu_values', $result['memory']);
    }


    /**
     *
     */
    public function testUrl()
    {
        $url = 'testUrl';
        $this->prepareGetRequestMock(array(
            array('url', $url),
        ));

        // make sure that we call profiles storage with url filter
        $this->profilesMock->expects(self::once())
                           ->method('getAll')
                           ->with(static::callback(function ($filter) use ($url) {
                                if (!($filter instanceof \PhpPerfTools\Storage\Filter)) {
                                    return false;
                                }

                                return $filter->getUrl() == $url;
                            }))->willReturn(array(
                                'results' => array('fake_results'),
                                'totalPages' => 0,
                                'page' => 0,
                                'direction' => 'desc',
                            ));

        // chart data. For this we mock it with fake data, we don't process it action
        // we just pass it to view
        $this->profilesMock->expects(self::once())
                           ->method('getPercentileForUrl')
                           ->willReturn('mocked_chart_data');

        $this->object->url();

        $result = $this->object->templateVars();

        self::assertSame($url, $result['url']);
        self::assertSame(array('fake_results'), $result['runs']);
        self::assertSame(array('fake_results'), $result['runs']);
    }

    /**
     *
     */
    public function testCompareNoBase()
    {
        $base = null;
        $head = null;
        $this->prepareGetRequestMock(array(
            array('base', null, $base),
            array('head', null, $head),
        ));

        $this->object->compare();
        $result = $this->object->templateVars();

        self::assertNull($result['base_run']);
        self::assertNull($result['head_run']);
        self::assertSame($base, $result['search']['base']);
        self::assertSame($head, $result['search']['head']);

        self::assertNull($result['candidates']);
        self::assertNull($result['comparison']);
    }

    /**
     *
     */
    public function testCompareWithBase()
    {
        $base = 1;
        $head = null;
        $url = 'testUrl';
        $this->prepareGetRequestMock(array(
            array('base', null, $base),
            array('head', null, $head)
        ));

        // mocked result set.
        $baseRunMock = $this->m('\PhpPerfTools\Profile', array(
            'getMeta',
            'compare',
        ));

        $baseRunMock->expects(self::any())->method('getMeta')->willReturnMap(array(
            array('simple_url', $url)
        ));
        $this->profilesMock->expects(self::once())->method('get')->willReturn($baseRunMock);

        // get candidate
        $this->profilesMock->expects(self::once())
                           ->method('getAll')
                           ->with(static::callback(function ($filter) use ($url) {
                                if (!($filter instanceof \PhpPerfTools\Storage\Filter)) {
                                    return false;
                                }

                                return $filter->getUrl() == $url;
                           }))
                           ->willReturn(array(
                                'results' => array('fake_results'),
                                'totalPages' => 0,
                                'page' => 0,
                                'direction' => 'desc',
                            ));

        $this->object->compare();
        $result = $this->object->templateVars();

        self::assertInstanceOf('\PhpPerfTools\Profile', $result['base_run']);
        self::assertNull($result['head_run']);
        self::assertSame($base, $result['search']['base']);
        self::assertSame($head, $result['search']['head']);

        self::assertNotNull($result['candidates']['results']);
        self::assertNull($result['comparison']);
    }

    /**
     *
     */
    public function testCompareWithBaseAndHead()
    {
        $base = 1;
        $head = 2;
        $url = 'testUrl';
        $compareResult = "CompareResult";

        $this->prepareGetRequestMock(array(
            array('base', null, $base),
            array('head', null, $head)
        ));

        // mocked result set.
        $baseRunMock = $this->m('\PhpPerfTools\Profile', array(
            'getMeta',
            'compare',
        ));

        $baseRunMock->expects(self::any())->method('getMeta')->willReturnMap(array(
            array('simple_url', $url)
        ));
        $baseRunMock->expects(self::once())->method('compare')->willReturn($compareResult);

        // mocked result set.
        $headRunMock = $this->m('\PhpPerfTools\Profile');

        $this->profilesMock->expects(self::exactly(2))->method('get')->willReturnMap(array(
            array($base, $baseRunMock),
            array($head, $headRunMock)
        ));

        $this->object->compare();
        $result = $this->object->templateVars();

        self::assertInstanceOf('\PhpPerfTools\Profile', $result['base_run']);
        self::assertInstanceOf('\PhpPerfTools\Profile', $result['head_run']);
        self::assertSame($base, $result['search']['base']);
        self::assertSame($head, $result['search']['head']);

        self::assertNull($result['candidates']['results']);
        self::assertSame($compareResult, $result['comparison']);
    }

    /**
     *
     */
    public function testSymbol($watchedFunction = array())
    {
        $id = 1;
        $symbol = 'main()';

        $this->prepareGetRequestMock(array(
            array('id', null, $id),
            array('symbol', null, $symbol)
        ));
        // mocked result set.
        $profileMock = $this->m('\PhpPerfTools\Profile', array(
            'calculateSelf',
            'getRelatives',
        ));

        $profileMock->expects(self::any())
                    ->method('calculateSelf');


        $profileMock->expects(self::any())
                    ->method('getRelatives')
                    ->with(static::equalTo($symbol))
                    ->willReturn(array('parents', 'current', 'children'));

        $this->profilesMock->expects(self::any())
                           ->method('get')
                           ->with(static::equalTo($id))
                           ->willReturn($profileMock);

        $this->dbMock->expects(self::any())
                     ->method('getWatchedFunctionByName')
                     ->willReturn($watchedFunction);

        $this->object->symbol();
        $result = $this->object->templateVars();

        self::assertSame('parents', $result['parents']);
        self::assertSame('current', $result['current']);
        self::assertSame('children', $result['children']);

    }

    /**
     *
     */
    public function symbolDataProvider()
    {
        return array(
            array('id'=>'1', 'name'=>'main()'),
            array('id'=>'1', 'name'=>'name'),
            array(),
        );
    }

    /**
     *
     */
    public function testSymbolShort()
    {
        $id = 1;
        $threshold = 2;
        $symbol = 'main()';
        $metric = 3;

        $this->prepareGetRequestMock(array(
            array('id', null, $id),
            array('threshold', null, $threshold),
            array('symbol', null, $symbol),
            array('metric', null, $metric),
        ));
        // mocked result set.
        $profileMock = $this->m('\PhpPerfTools\Profile', array(
            'calculateSelf',
            'getRelatives',
        ));

        $profileMock->expects(self::any())
                    ->method('calculateSelf');


        $profileMock->expects(self::any())
                    ->method('getRelatives')
                    ->with(static::equalTo($symbol), static::equalTo($metric), static::equalTo($threshold))
                    ->willReturn(array('parents', 'current', 'children'));

        $this->profilesMock->expects(self::any())
                           ->method('get')
                           ->with(static::equalTo($id))
                           ->willReturn($profileMock);

        $this->object->symbolShort();
        $result = $this->object->templateVars();

        self::assertSame('parents', $result['parents']);
        self::assertSame('current', $result['current']);
        self::assertSame('children', $result['children']);
    }

    /**
     *
     */
    public function testCallgraph()
    {
        $id = 1;

        $this->prepareGetRequestMock(array(
            array('id', null, $id),
        ));

        // mocked result set.
        $profileMock = $this->m('\PhpPerfTools\Profile');

        $this->profilesMock->expects(self::any())
                           ->method('get')
                           ->with(static::equalTo($id))
                           ->willReturn($profileMock);

        $this->object->callgraph();
        $result = $this->object->templateVars();

        static::assertArrayHasKey('profile', $result);
        static::assertArrayHasKey('date_format', $result);
        static::assertArrayNotHasKey('callgraph', $result);
    }

    /**
     * @throws Exception
     */
    public function testCallgraphData()
    {
        $id = 1;
        $metric = 'metric';
        $threshold = 1;

        $this->prepareGetRequestMock(array(
            array('id', null, $id),
            array('metric', null, $metric),
            array('threshold', null, $threshold),
        ));

        // mocked result set.
        $profileMock = $this->m('\PhpPerfTools\Profile', array(
            'getCallgraphNodes',
            'getCallgraph'
        ));

        $this->profilesMock->expects(self::any())
                           ->method('get')
                           ->with(static::equalTo($id))
                           ->willReturn($profileMock);

        $this->responseMock->expects(self::once())->method('body')->willReturn(array(''));

        $this->object->callgraphData();

        static::assertEquals('application/json', $this->responseMock->headers->get('Content-Type'));
    }

    /**
     * @throws Exception
     */
    public function testDeleteForm()
    {
        $id = 1;

        $this->prepareGetRequestMock(array(
            array('id', null, $id),
        ));

        // mocked result set.
        $profileMock = $this->m('\PhpPerfTools\Profile');

        $this->profilesMock->expects(self::any())
                           ->method('get')
                           ->with(static::equalTo($id))
                           ->willReturn($profileMock);

        $this->object->deleteForm();
        $result = $this->object->templateVars();

        self::assertArrayHasKey('result', $result);
        self::assertSame($profileMock, $result['result']);

    }

    /**
     *
     */
    public function testDeleteSubmit()
    {
        $id = 1;

        $this->preparePostRequestMock(array(
            array('id', null, $id),
        ));

        // mocked result set.
        $profileMock = $this->m('\PhpPerfTools\Profile');

        $this->profilesMock->expects(self::any())
                           ->method('delete')
                           ->with(static::equalTo($id))
                           ->willReturn($profileMock);

        $this->appMock->expects(self::once())->method('urlFor');
        $this->appMock->expects(self::once())->method('redirect');

        $this->object->deleteSubmit();
    }

    /**
     *
     */
    public function testDeleteAllSubmit()
    {
        $this->profilesMock->expects(self::any())
                           ->method('truncate');

        $this->appMock->expects(self::once())->method('urlFor');
        $this->appMock->expects(self::once())->method('redirect');

        $this->object->deleteAllSubmit();

    }
}
