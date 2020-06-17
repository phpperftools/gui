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

use PhpPerfTools\Profile;

/**
 * Class ProfileTest
 *
 * @package PhpPerfTools\Tests
 */
class ProfileTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Object of the test.
     *
     * @var \PhpPerfTools\Profile
     */
    public $object;

    /**
     * @var mixed
     */
    public $fixture;

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();

        $contents = file_get_contents(PHPPERFTOOLS_ROOT_DIR . '/tests/fixtures/results.json');
        $this->fixture = json_decode($contents, true);
    }

    /**
     *
     */
    public function testSplitName()
    {
        $this->object = new Profile(array(), false);
        $ret = $this->object->splitName("main()==>test()");

        self::assertSame('main()', $ret[0]);
        self::assertSame('test()', $ret[1]);

        $ret = $this->object->splitName("main()");

        self::assertSame('main()', $ret[1]);

    }

    public function testProcessIncompleteData()
    {
        $data = array(
            'main()' => array(),
            'main()==>do_thing()' => array(// empty because of bad extension
            ),
            'other_thing()==>do_thing()' => array(
                'cpu' => 1,
            ),
        );
        $profile = new Profile(array('profile' => $data));
        static::assertNotEmpty($profile->get('do_thing()'));
    }

    public function testGetRelatives()
    {
        $data = array(
            'main()' => array(),
            'main()==>other_func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
            'main()==>your_func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
            'other_func==>func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
            'other_func==>isset' => array(
                'ct' => 10,
                'cpu' => 10,
                'wt' => 1,
                'mu' => 5,
                'pmu' => 1,
            ),
            'your_func==>func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
            'func==>strlen' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
            'func==>isset' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
        );
        $profile = new Profile(array('profile' => $data));

        $result = $profile->getRelatives('not there at all');
        static::assertCount(3, $result);
        static::assertEquals(array(), $result[0]);
        static::assertEquals(array(), $result[1]);
        static::assertEquals(array(), $result[2]);

        $result = $profile->getRelatives('func');
        static::assertCount(3, $result);

        list($parent, $current, $children) = $result;
        static::assertCount(2, $parent);
        static::assertEquals('other_func', $parent[0]['function']);
        static::assertEquals('your_func', $parent[1]['function']);

        static::assertCount(2, $children);
        static::assertEquals('strlen', $children[0]['function']);
        static::assertEquals('isset', $children[1]['function']);

        static::assertEquals('func', $current['function']);
        static::assertEquals(2, $current['ct']);
        static::assertEquals(2, $current['wt']);
        static::assertEquals(2, $current['mu']);
        static::assertEquals(2, $current['pmu']);
    }

    public function testGetRelativesWithThreshold()
    {
        $data = array(
            'main()' => array(
                'ct' => 1,
                'wt' => 100,
            ),
            'main()==>other_func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 50,
                'mu' => 1,
                'pmu' => 1,
            ),
            'main()==>your_func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 50,
                'mu' => 1,
                'pmu' => 1,
            ),
            'other_func==>func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 10,
                'mu' => 1,
                'pmu' => 1,
            ),
            'other_func==>isset' => array(
                'ct' => 10,
                'cpu' => 10,
                'wt' => 1,
                'mu' => 5,
                'pmu' => 1,
            ),
            'your_func==>func' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
            'func==>strlen' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
            'func==>isset' => array(
                'ct' => 1,
                'cpu' => 1,
                'wt' => 1,
                'mu' => 1,
                'pmu' => 1,
            ),
        );
        $profile = new Profile(array('profile' => $data));

        $result = $profile->getRelatives('other_func', 'wt', 0.1);
        static::assertCount(3, $result);

        list($parent, $current, $children) = $result;
        static::assertCount(1, $parent);
        static::assertEquals('main()', $parent[0]['function']);

        static::assertCount(1, $children, 'One method below threshold');
        static::assertEquals('func', $children[0]['function']);
    }

    public function testGet()
    {
        $fixture = $this->fixture[0];
        $profile = new Profile($fixture);
        static::assertEquals($fixture['profile']['main()']['wt'], $profile->get('main()', 'wt'));

        $expected = $fixture['profile']['main()'];
        $result = $profile->get('main()');
        unset($result['parents']);
        static::assertEquals($expected, $result);

        static::assertNull($profile->get('main()', 'derp'));
        static::assertNull($profile->get('derp', 'wt'));
    }

    public function testGetMeta()
    {
        $fixture = $this->fixture[0];
        $profile = new Profile($fixture);

        static::assertEquals($fixture['meta'], $profile->getMeta());

        static::assertEquals($fixture['meta']['simple_url'], $profile->getMeta('simple_url'));
        static::assertEquals($fixture['meta']['SERVER']['REQUEST_TIME'], $profile->getMeta('SERVER.REQUEST_TIME'));

        static::assertNull($profile->getMeta('not there'));
        static::assertNull($profile->getMeta('SERVER.NOT_THERE'));
    }

    public function testExtractDimension()
    {
        $profile = new Profile($this->fixture[0]);
        $result = $profile->extractDimension('mu', 1);

        static::assertCount(1, $result);
        $expected = array(
            'name' => 'main()',
            'value' => 3449360
        );
        static::assertEquals($expected, $result[0]);
    }

    public function testCalculateSelf()
    {
        $profile = new Profile($this->fixture[1]);
        $result = $profile->calculateSelf()->getProfile();

        $main = $result['main()'];
        static::assertEquals(800, $main['emu']);
        static::assertEquals(250, $main['epmu']);
        static::assertEquals(array(null), $main['parents']);

        $func = $result['eat_burger()'];
        static::assertEquals(2, $func['ewt']);
        static::assertEquals(1850, $func['emu']);
        static::assertEquals(2300, $func['epmu']);
        static::assertEquals(array('main()'), $func['parents']);
    }

    public function testSort()
    {
        $data = array(
            'main()' => array(
                'mu' => 12345
            ),
            'main()==>class_exists()' => array(
                'mu' => 34567
            ),
        );
        $profile = new Profile(array());
        $result = $profile->sort('mu', $data);

        $expected = array(
            'main()==>class_exists()' => array(
                'mu' => 34567
            ),
            'main()' => array(
                'mu' => 12345
            ),
        );
        static::assertSame($expected, $result);
    }

    public function testGetWatched()
    {
        $fixture = $this->fixture[0];
        $profile = new Profile($fixture);
        $data = $profile->getProfile();

        static::assertEmpty($profile->getWatched(array("name"=>'not there')));
        $matches = $profile->getWatched(array("name"=>'strpos.*'));

        static::assertCount(1, $matches);
        static::assertEquals('strpos()', $matches[0]['function']);
        static::assertEquals($data['strpos()']['wt'], $matches[0]['wt']);

        $matches = $profile->getWatched(array("name"=>'str.*'));
        static::assertCount(1, $matches);
        static::assertEquals('strpos()', $matches[0]['function']);
        static::assertEquals($data['strpos()']['wt'], $matches[0]['wt']);

        $matches = $profile->getWatched(array("name"=>'[ms].*'));
        static::assertCount(2, $matches);
        static::assertEquals('strpos()', $matches[0]['function']);
        static::assertEquals($data['strpos()']['wt'], $matches[0]['wt']);

        static::assertEquals('main()', $matches[1]['function']);
        static::assertEquals($data['main()']['wt'], $matches[1]['wt']);
    }

    public function testGetFunctionCount()
    {
        $fixture = $this->fixture[0];
        $profile = new Profile($fixture);

        static::assertEquals(11, $profile->getFunctionCount());
    }

    public function testCompareAllTheSame()
    {
        $fixture = $this->fixture[0];
        $base = new Profile($fixture);
        $head = new Profile($fixture);

        $result = $base->compare($head);

        static::assertArrayHasKey('diffPercent', $result);
        static::assertArrayHasKey('diff', $result);
        static::assertArrayHasKey('head', $result);
        static::assertArrayHasKey('base', $result);

        static::assertSame($base, $result['base']);
        static::assertSame($head, $result['head']);

        static::assertEquals(0, $result['diff']['main()']['ewt']);
        static::assertEquals(0, $result['diff']['functionCount']);
        static::assertEquals(0, $result['diff']['strpos()']['ewt']);
    }

    public function testCompareWithDifferences()
    {
        $fixture = $this->fixture[0];
        $base = new Profile($this->fixture[3]);
        $head = new Profile($this->fixture[4]);
        $result = $base->compare($head);

        static::assertEquals(0, $result['diff']['main()']['ct']);
        static::assertEquals(9861, $result['diff']['main()']['wt']);

        static::assertEquals(
            -10,
            $result['diff']['strpos()']['wt'],
            'Missing functions should show as negative'
        );
        static::assertEquals(
            -10,
            $result['diff']['strpos()']['ewt'],
            'Should include exclusives'
        );
        static::assertEquals(0.33, number_format($result['diffPercent']['functionCount'], 2));
    }

    public function testGetCallgraph()
    {
        $profile = new Profile($this->fixture[1]);

        $expected = array(
            'metric' => 'wt',
            'total' => 35,
            'nodes' => array(
                array(
                    'name' => 'main()',
                    'value' => 35,
                    'callCount' => 1,
                ),
                array(
                    'name' => 'eat_burger()',
                    'value' => 25,
                    'callCount' => 1,
                ),
                array(
                    'name' => 'chew_food()',
                    'value' => 22,
                    'callCount' => 10,
                ),
                array(
                    'name' => 'strlen()',
                    'value' => 2,
                    'callCount' => 2,
                ),
                array(
                    'name' => 'drink_beer()',
                    'value' => 14,
                    'callCount' => 1,
                ),
                array(
                    'name' => 'lift_glass()',
                    'value' => 10,
                    'callCount' => 5,
                ),
            ),
            'links' => array(
                array(
                    'source' => 'main()',
                    'target' => 'eat_burger()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'eat_burger()',
                    'target' => 'chew_food()',
                    'callCount' => 10,
                ),
                array(
                    'source' => 'eat_burger()',
                    'target' => 'strlen()',
                    'callCount' => 2,
                ),
                array(
                    'source' => 'main()',
                    'target' => 'drink_beer()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'drink_beer()',
                    'target' => 'lift_glass()',
                    'callCount' => 5,
                ),
                array(
                    'source' => 'drink_beer()',
                    'target' => 'strlen()',
                    'callCount' => 2,
                ),
            )
        );
        $result = $profile->getCallgraph();
        static::assertEquals($expected, $result);
    }

    public function testGetCallgraphNoDuplicates()
    {
        $profile = new Profile($this->fixture[2]);

        $expected = array(
            'metric' => 'wt',
            'total' => 50139,
            'nodes' => array(
                array(
                    'name' => 'main()',
                    'value' => 50139,
                    'callCount' => 1,
                ),
                array(
                    'name' => 'load_file()',
                    'value' => 10000,
                    'callCount' => 1,
                ),
                array(
                    'name' => 'open()',
                    'value' => 10000,
                    'callCount' => 2,
                ),
                array(
                    'name' => 'strlen()',
                    'value' => 5000,
                    'callCount' => 1,
                ),
                array(
                    'name' => 'parse_string()',
                    'value' => 10000,
                    'callCount' => 1,
                )
            ),
            'links' => array(
                array(
                    'source' => 'main()',
                    'target' => 'load_file()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'load_file()',
                    'target' => 'open()',
                    'callCount' => 2,
                ),
                array(
                    'source' => 'open()',
                    'target' => 'strlen()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'main()',
                    'target' => 'parse_string()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'parse_string()',
                    'target' => 'open()',
                    'callCount' => 2,
                ),
            )
        );
        $result = $profile->getCallgraph();
        static::assertEquals($expected, $result);
    }

    public function testGetCallgraphWithMetricFromExclusiveList()
    {
        $profile = new Profile($this->fixture[2]);


        $expected = array(
            'metric' => 'ewt',
            'total' => 30139,
            'nodes' => array(
                array(
                    'name' => 'main()',
                    'callCount' => 1,
                    'value' => 30139,
                ),
                array(
                    'name' => 'load_file()',
                    'callCount' => 1,
                    'value' => 5000,
                ),
                array(
                    'name' => 'open()',
                    'callCount' => 2,
                    'value' => 5000,
                ),
                array(
                    'name' => 'strlen()',
                    'callCount' => 1,
                    'value' => 5000,
                ),
                array(
                    'name' => 'parse_string()',
                    'callCount' => 1,
                    'value' => 5000,
                ),
            ),
            'links' => array(
                array(
                    'source' => 'main()',
                    'target' => 'load_file()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'load_file()',
                    'target' => 'open()',
                    'callCount' => 2,
                ),
                array(
                    'source' => 'open()',
                    'target' => 'strlen()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'main()',
                    'target' => 'parse_string()',
                    'callCount' => 1,
                ),
                array(
                    'source' => 'parse_string()',
                    'target' => 'open()',
                    'callCount' => 2,
                ),
            ),
        );
        $result = $profile->getCallgraph('ewt');
        static::assertEquals($expected, $result);
    }

    public function testGetDateFallback()
    {
        $data = array(
            'meta' => array(
                'SERVER' => array()
            )
        );
        $profile = new Profile($data);
        $result = $profile->getDate();
        static::assertInstanceOf('DateTime', $result);
    }
}
