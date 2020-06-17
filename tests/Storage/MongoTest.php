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

class MongoTest extends CommonTestCase
{

    /**
     * @var \PhpPerfTools\Storage\Mongo
     */
    public $object;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\MongoCollection
     */
    public $collectionMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\MongoDb
     */
    public $connectionMock;

    /**
     * @throws MongoConnectionException
     * @throws MongoException
     * @requires extension mongodb
     */
    public function setUp()
    {
        $this->object = new \PhpPerfTools\Storage\Mongo(array(
            'rows_per_page' => 20,
            'db.options' =>  array(),
            'db.db' => 'results',
        ));

        $this->collectionMock = $this->m('\ArrayIterator', array(
            'find',
            'sort',
            'skip',
            'limit',
            'count',
            'findOne',
            'remove',
            'insert',
            'update'
        ));
        $this->connectionMock = $this->m('\MongoDB', array('selectCollection'));
        $this->connectionMock->expects(self::any())->method('selectCollection')->willReturn($this->collectionMock);

        $this->object->setConnection($this->connectionMock);
    }

    /**
     * @throws MongoCursorException
     * @requires extension mongodb
     */
    public function testFind()
    {

        $filter = new \PhpPerfTools\Storage\Filter();
        $filter->setSort('ct');
        $filter->setDirection('asc');

        $this->collectionMock->expects(self::once())->method('find')->willReturnSelf();
        $this->collectionMock->expects(self::once())->method('sort')->willReturnSelf();
        $this->collectionMock->expects(self::once())->method('skip')->willReturnSelf();
        $this->collectionMock->expects(self::once())->method('limit')->willReturnSelf();

        $ret = $this->object->find($filter);
        self::assertInstanceOf('\PhpPerfTools\Storage\ResultSet', $ret);
    }

    /**
     * @throws MongoCursorTimeoutException
     * @throws MongoException
     * @requires extension mongodb
     */
    public function testCount()
    {
        $filter = new \PhpPerfTools\Storage\Filter();
        $filter->setSort('ct');
        $filter->setDirection('asc');

        $this->collectionMock->expects(self::once())->method('find')->willReturnSelf();
        $this->collectionMock->expects(self::once())->method('count')->willReturn(5);

        $ret = $this->object->count($filter);
        self::assertSame(5, $ret);
    }

    /**
     * @throws MongoException
     * @requires extension mongodb
     */
    public function testFindOne()
    {
        $this->collectionMock->expects(self::once())->method('findOne')->willReturn(5);

        $ret = $this->object->findOne(1);
        self::assertSame(5, $ret);
    }


    /**
     * @requires extension mongodb
     */
    public function testRemove()
    {
        $this->collectionMock->expects(self::once())->method('remove')->willReturn(true);
        
        $ret = $this->object->remove(1);
        self::assertTrue($ret);
    }

    /**
     * @requires extension mongodb
     */
    public function crudDataProvider()
    {
        return array(
            array(true, 'remove', true, array(1)),
        );
    }

//    public function testGetWatchedFunctions() {
//
//    }
//
//    public function testUpdateWatchedFunction() {
//
//    }
//
//    public function testAddWatchedFunction() {
//
//    }
//
//    public function testRemoveWatchedFunction() {
//
//    }
}
