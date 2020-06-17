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

use Slim\Slim;
use Slim\Environment;

class ExtensionTest extends CommonTestCase
{

    /**
     * @var \PhpPerfTools\Twig\Extension
     */
    public $object;

    /**
     * 
     */
    public function setUp()
    {
        parent::setUp();
        $app = new Slim();
        $app->get('/test', function () {})->name('test');
        $this->object = new \PhpPerfTools\Twig\Extension($app);
    }

    public function testFormatBytes()
    {
        $result = $this->object->formatBytes(2999);
        $expected = '2,999&nbsp;<span class="units">bytes</span>';
        $this->assertEquals($expected, $result);
    }

    public function testFormatTime()
    {
        $result = $this->object->formatTime(2999);
        $expected = '2,999&nbsp;<span class="units">µs</span>';
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function makePercentProvider() {
        return array(
            array(
                10,
                100,
                '10 <span class="units">%</span>'
            ),
            array(
                0.5,
                100,
                '1 <span class="units">%</span>'
            ),
            array(
                100,
                0,
                '0 <span class="units">%</span>'
            )
        );
    }

    /**
     * @dataProvider makePercentProvider
     */
    public function testMakePercent($value, $total, $expected)
    {
        $result = $this->object->makePercent($value, $total, $total);
        $this->assertEquals($expected, $result);
    }

    public static function urlProvider()
    {
        return array(
            // simple no query string
            array(
                'test',
                null,
                '/test'
            ),
            // simple with query string
            array(
                'test',
                array('test' => 'value'),
                '/test?test=value'
            ),
        );
    }

    /**
     * @dataProvider urlProvider
     */
    public function testUrl($url, $query, $expected)
    {
        $_SERVER['PHP_SELF'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = '80';

        $result = $this->object->url($url, $query);
        $this->assertRegExp('|/test\?.*handler\=|', $result);
    }

    public function testStaticUrlNoIndexPhp()
    {
        Environment::mock(array(
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'REQUEST_URI' => '/',
        ));
        $result = $this->object->staticUrl('css/bootstrap.css');
        $this->assertEquals('/css/bootstrap.css', $result);
    }

    public function testStaticUrlWithIndexPhp()
    {
        Environment::mock(array(
            'SCRIPT_NAME' => '/xhgui/webroot/index.php',
            'PHP_SELF' => '/xhgui/webroot/index.php/',
            'REQUEST_URI' => '/xhgui/webroot/index.php/',
        ));
        $result = $this->object->staticUrl('css/bootstrap.css');
        $this->assertEquals('/xhgui/webroot/css/bootstrap.css', $result);
    }
}
