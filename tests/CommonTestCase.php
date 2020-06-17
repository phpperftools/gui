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

namespace PhpPerfTools\Tests;

use \PHPUnit\Framework\TestCase;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\Response;

class CommonTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Slim\Http\Request
     */
    protected $requestMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Slim\Http\Response
     */
    protected $responseMock;

    /**
     * Basic setup
     */
    public function setUp()
    {
        $this->requestMock = $this->getMockBuilder('\Slim\Http\Request')
                                  ->setMethods(array('get', 'post'))
                                  ->disableOriginalConstructor()
                                  ->getMock();

        $this->responseMock = $this->getMockBuilder('\Slim\Http\Response')
                                  ->setMethods(array('get', 'post', 'body'))
                                  ->disableOriginalConstructor()
                                  ->getMock();

        $this->responseMock->headers = $this->getMockBuilder('\Slim\Http\Headers')
                                  ->setMethods()
                                  ->disableOriginalConstructor()
                                  ->getMock();
    }


    /**
     * @param array $override
     */
    protected function prepareGetRequestMock($override =  array())
    {

        $default = array(
            array('url',         null, 'testUrl'),
            array('startDate',   null, '2019-01-01'),
            array('endDate',     null, '2019-02-01'),
            array('sort',        null, 'time'),
            array('direction',   null, 'desc'),
            array('page',        null, '1'),
        );

        $this->requestMock->expects(self::any())
                          ->method('get')
                          ->willReturnMap(array_merge($default, $override));
    }

    /**
     * @param array $override
     */
    protected function preparePostRequestMock($override = array())
    {
        $this->requestMock->expects(self::any())
                          ->method('post')
                          ->willReturnMap($override);
    }

    /**
     * Shorthand helper for mock creation
     *
     * @param $class
     * @param array $methods
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function m($class, $methods = array())
    {
        return $this->getMockBuilder($class)->disableOriginalConstructor()->setMethods($methods)->getMock();
    }
}
