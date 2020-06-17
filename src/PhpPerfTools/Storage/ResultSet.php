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

namespace PhpPerfTools\Storage;

/**
 * Class PhpPerfTools_Storage_ResultSet
 */
class ResultSet implements \Iterator, \Countable
{

    /**
     * @var array|null
     */
    protected $data = array();
    /**
     * @var array
     */
    protected $keys = array();

    /**
     * @var int
     */
    protected $i = 0;
    /**
     * @var int
     */
    protected $limit = 25;
    /**
     * @var int
     */
    protected $totalRows = 0;

    /**
     * @param null $data
     * @param int $totalRows
     */
    public function __construct($data = null, $totalRows = 0)
    {
        $this->data = $data;
        $this->keys = array_keys($data);
        $this->totalRows = $totalRows;
        $this->limit = \PhpPerfTools\Config::read('rows_per_page', 25);
    }

    /**
     * @return array|null
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * @return $this
     */
    public function sort()
    {
        return $this;
    }

    /**
     * @param $count
     * @return $this
     */
    public function skip($count)
    {
        $this->i += $count;
        return $this;
    }

    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param $i
     * @return mixed
     */
    public function get($i)
    {
        return $this->data[$i];
    }

    /**
     * Return the current element
     */
    public function current()
    {
        return $this->get($this->keys[$this->i]);
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        $this->i++;
    }

    /**
     * Return the key of the current element
     */
    public function key()
    {
        return $this->keys[$this->i];
    }

    /**
     * Checks if current position is valid
     *
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return !empty($this->keys[$this->i]) && !empty($this->data[$this->keys[$this->i]]) && $this->i < $this->limit;
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        $this->i = 0;
    }

    /**
     * @return int
     */
    public function getTotalRows()
    {
        return $this->totalRows;
    }

    /**
     * @param int $totalRows
     */
    public function setTotalRows($totalRows)
    {
        $this->totalRows = $totalRows;
    }
}
