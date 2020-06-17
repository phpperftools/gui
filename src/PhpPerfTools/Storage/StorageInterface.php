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
 * Interface that all storage classes must implement
 */
interface StorageInterface
{
    /**
     * Find profiles based on filter.
     *
     * @param Filter $filter
     * @param bool $includeProfiles
     * @return mixed
     */
    public function find(Filter $filter, $includeProfiles = false);

    /**
     * Count number of profiles that match given filter
     *
     * @param Filter $filter
     * @return mixed
     */
    public function count(Filter $filter);

    /**
     * Return aggregated result
     *
     * @param Filter $filter
     * @param int $percentile
     * @param string $aggregationFormat
     * @return mixed
     */
    public function aggregate(Filter $filter, $percentile = 1, $aggregationFormat = 'Y-m-d H:i:00');

    /**
     * Get one profile by id
     *
     * @param $id
     * @return mixed
     */
    public function findOne($id);

    /**
     * Delete one profile by id
     *
     * @param $id
     * @return mixed
     */
    public function remove($id);

    /**
     * Drop all profiles
     *
     * @return mixed
     */
    public function drop();
}
