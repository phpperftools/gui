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

namespace PhpPerfTools;

use PhpPerfTools\Storage\Filter;
use PhpPerfTools\Storage\ResultSet;
use PhpPerfTools\Storage\StorageInterface;

/**
 * Contains logic for getting/creating/removing profile records.
 */
class Profiles
{
    /**
     * @var StorageInterface
     */
    protected $storage;

    /**
     * PhpPerfTools_Profiles constructor.
     * @param StorageInterface $storage
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param $conditions
     * @param null $fields
     * @return mixed
     */
    public function query($conditions, $fields = null)
    {
        return $this->storage->find($conditions, $fields);
    }

    /**
     * Get a single profile run by id.
     *
     * @param string $id The id of the profile to get.
     * @return Profile
     * @throws Exception
     */
    public function get($id)
    {
        return $this->wrap($this->storage->findOne($id));
    }

    /**
     * Get the list of profiles for a simplified url.
     *
     * @param string $url The url to load profiles for.
     * @param array $options Pagination options to use.
     * @param array $conditions The search options.
     * @return MongoCursor
     */
    public function getForUrl($url, $options, $conditions = array())
    {
        $conditions = array_merge(
            (array)$conditions,
            array('simple_url' => $url)
        );
        $options = array_merge($options, array(
            'conditions' => $conditions,
        ));
        return $this->paginate($options);
    }

    /**
     * @param Filter $filter
     * @return array
     * @throws Exception
     */
    public function paginate(Filter $filter)
    {
        $result = $this->storage->find($filter);

        $totalRows = $this->storage->count($filter);
        $totalPages = max(ceil($totalRows / $filter->getPerPage()), 1);

        return array(
            'results'       => $this->wrap($result),
            'sort'          => $filter->getSort(),
            'direction'     => $filter->getDirection(),
            'page'          => $filter->getPage(),
            'perPage'       => $filter->getPerPage(),
            'totalPages'    => $totalPages
        );
    }

    /**
     * Get the Percentile metrics for a URL
     *
     * This will group data by date and returns only the
     * percentile + date, making the data ideal for time series graphs
     *
     * @param integer $percentile The percentile you want. e.g. 90.
     * @param string $url
     * @param Filter $filter Search options containing startDate and or endDate
     * @return array Array of metrics grouped by date
     */
    public function getPercentileForUrl($percentile, $url, $filter)
    {
        $col = '$meta.request_date';

        $results = $this->storage->aggregate($filter, $percentile);
        if (empty($results['result'])) {
            return array();
        }
        $keys = array(
            'wall_times'    => 'wt',
            'cpu_times'     => 'cpu',
            'mu_times'      => 'mu',
            'pmu_times'     => 'pmu'
        );
        foreach ($results['result'] as &$result) {
            if ($result['_id'] instanceof \MongoDate) {
                $result['date'] = date('Y-m-d H:i:s', $result['_id']->sec);
            } else {
                $result['date'] = $result['_id'];
            }

            unset($result['_id']);
            $index = max(round($result['raw_index']) - 1, 0);
            foreach ($keys as $key => $out) {
                sort($result[$key]);
                $result[$out] = isset($result[$key][$index]) ? $result[$key][$index] : null;
                unset($result[$key]);
            }
        }
        return array_values($results['result']);
    }

    /**
     *
     */
    public function getAggregatedData()
    {
        $filter = new Filter();

        return $this->storage->aggregate($filter, 1, 'Y-m-d H');
    }

    /**
     * Get a paginated set of
     *
     * @param Filter $filter The find options to use.
     * @return array An array of result data.
     */
    public function getAll($filter)
    {
        return $this->paginate($filter);
    }

    /**
     * Delete a profile run.
     *
     * @param string $id The profile id to delete.
     * @return array|bool
     */
    public function delete($id)
    {
        return $this->storage->remove($id);
    }

    /**
     * Used to truncate a collection.
     *
     * Primarly used in test cases to reset the test db.
     *
     * @return boolean
     */
    public function truncate()
    {
        return $this->storage->drop();
    }

    /**
     * Converts arrays + MongoCursors into Profile instances.
     *
     * @param array|MongoCursor $data The data to transform.
     * @return Profile|array The transformed/wrapped
     * @throws Exception
     */
    protected function wrap($data)
    {
        if ($data === null) {
            throw new Exception('No profile data found.');
        }

        if (!($data instanceof ResultSet)) {
            return new Profile($data, true);
        }

        $results = array();
        foreach ($data as $row) {
            $results[] = new Profile($row, true);
        }
        return $results;
    }

    /**
     * @return StorageInterface
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param StorageInterface $storage
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
    }
}
