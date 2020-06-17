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


use PhpPerfTools\Config;

/**
 * Class PhpPerfTools_Storage_Mongo
 */
class Mongo extends AbstractStorage implements StorageInterface, WatchedFunctionsStorageInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \MongoDB
     */
    protected $connection;

    /**
     * @var int
     */
    protected $defaultPerPage = 25;

    /**
     * @var string
     */
    protected $collectionName;

    /**
     * @var \MongoClient
     */
    protected $mongoClient;

    /**
     * Mongo constructor.
     *
     * @param $config
     * @param string $collection
     */
    public function __construct($config, $collection = 'results')
    {
        // set default number of rows for all  This can be changed
        // for each query
        $this->defaultPerPage = !empty($config['rows_per_page']) ? $config['rows_per_page'] : 25;

        $this->collectionName = $collection;

        // make sure options is an array
        if (empty($config['options'])) {
            $config['options'] = array();
        }

        $config['options']['connect'] = true;

        $this->config = $config;
    }

    /**
     * @inheritDoc
     * @param \PhpPerfTools\Storage\Filter $filter
     * @param bool $includeProfiles
     * @return ResultSet
     * @throws \MongoConnectionException
     * @throws \MongoException
     */
    public function find(Filter $filter, $includeProfiles = false)
    {
        $sort = array();
        switch ($filter->getSort()) {
            case 'ct':
            case 'wt':
            case 'cpu':
            case 'mu':
            case 'pmu':
                $sort['profile.main().' . $filter->getSort()] = $filter->getDirection() === 'asc' ? 1 : -1;
                break;
            case 'time':
                $sort['meta.request_ts'] = $filter->getDirection() === 'asc' ? 1 : -1;
                break;
        }

        $conditions = $this->getConditions($filter);

        $ret = $this->getCollection()
                    ->find($conditions)
                    ->sort($sort)
                    ->skip((int)($filter->getPage() - 1) * $filter->getPerPage())
                    ->limit($filter->getPerPage());


        $result = new ResultSet(iterator_to_array($ret));
        return $result;
    }

    /**
     * @inheritDoc
     * @param Filter $filter
     * @return int
     * @throws \MongoConnectionException
     * @throws \MongoException
     */
    public function count(Filter $filter)
    {
        $conditions = $this->getConditions($filter);

        $ret = $this->getCollection()->find($conditions, array('_id' => 1))->count();
        return $ret;
    }

    /**
     * @inheritDoc
     * @param $id
     * @return array|null
     * @throws \MongoException
     */
    public function findOne($id)
    {
        $ret = $this->getCollection()
                    ->findOne(array('_id' => new \MongoId($id)));
        return $ret;
    }

    /**
     * @inheritDoc
     * @param $id
     * @return array|bool
     * @throws \MongoConnectionException
     * @throws \MongoException
     */
    public function remove($id)
    {
        return $this->getCollection()->remove(
            array('_id' => new \MongoId($id)),
            array('w' => 1)
        );
    }

    /**
     * @inheritDoc
     */
    public function drop()
    {
        // TODO: Implement drop() method.
    }

    /**
     * @inheritDoc
     * @param $match
     * @param $col
     * @param int $percentile
     * @codeCoverageIgnore despite appearances this is very simple function and there is nothing to test here.
     * @return array
     * @throws \MongoException
     */
    public function aggregate(Filter $filter, $percentile = 1, $aggregationFormat = 'Y-m-d H:i:00')
    {
        $conditions = $this->getConditions($filter);
        $param = array();

        if (!empty($conditions)) {
            $param[] = array('$match' => $conditions);
        }
        $param[] = array(
            '$project' => array(
                'date'  => array(
                    '$dateToString'=>array(
                        "format" => $this->getAggregationDateFormatFromPHP($aggregationFormat),
                        "date"=>'$meta.request_ts'
                    )
                ),
                'profile.main()' => 1
            )
        );

        $param[] =  array(
            '$group' => array(
                '_id'        => '$date',
                'wall_times' => array('$push' => '$profile.main().wt'),
                'cpu_times'  => array('$push' => '$profile.main().cpu'),
                'mu_times'   => array('$push' => '$profile.main().mu'),
                'pmu_times'  => array('$push' => '$profile.main().pmu'),
                'row_count'  => array('$sum' => 1),
            )
        );

        $param[] = array(
            '$project' => array(
                'date'       => '$date',
                'row_count'  => '$row_count',
                'raw_index'  => array(
                    '$multiply' => array(
                        '$row_count',
                        $percentile / 100
                    )
                ),
                'wall_times' => '$wall_times',
                'cpu_times'  => '$cpu_times',
                'mu_times'   => '$mu_times',
                'pmu_times'  => '$pmu_times',
            )
        );

        $param[] = array(
            '$sort' => array('_id' => 1)
        );
        $ret = $this->getCollection()->aggregate(
            $param,
            array(
                'cursor' => array('batchSize' => 0)
            )
        );

        return $ret;
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function getWatchedFunctions()
    {
        $ret = array();
        try {
            $cursor = $this->getConnection()->watches->find()->sort(array('name' => 1));
            $ret = array();
            foreach ($cursor as $row) {
                $ret[] = array('id' => $row['_id']->__toString(), 'name' => $row['name']);
            }
        } catch (\Exception $e) {
            // if something goes wrong just return empty array
            // @todo add exception
        }
        return $ret;
    }

    /**
     * @inheritDoc
     * @param $name
     * @return bool
     */
    public function addWatchedFunction($name, array $options = array())
    {

        $name = trim($name);
        if (empty($name)) {
            return false;
        }

        try {
            $id = new \MongoId();

            $data = array(
                '_id'  => $id,
                'name' => $name
            );
            $this->getConnection()->watches->insert($data);

            return true;
        } catch (\Exception $e) {
            // if something goes wrong just ignore for now
            // @todo add exception
        }
        return false;
    }

    /**
     * @inheritDoc
     * @param $id
     * @param $name
     * @return bool
     */
    public function updateWatchedFunction($id, $name, array $options = array())
    {
        $name = trim($name);
        if (empty($name)) {
            return false;
        }

        try {
            $id = new \MongoId($id);
            $data = array(
                '_id'  => $id,
                'name' => $name
            );
            $this->getConnection()->watches->save($data);

            return true;
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @inheritDoc
     * @param $id
     * @return bool
     */
    public function removeWatchedFunction($id)
    {

        try {
            $id = new \MongoId($id);

            $this->getConnection()->watches->remove(array('_id' => $id));

            return true;
        } catch (\Exception $e) {
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getWatchedFunctionByName($name)
    {
        // TODO: Implement getWatchedFunctionByName() method.
    }

    /**
     * Convert filter into mongo condition
     *
     * @param Filter $filter
     * @return array
     * @throws \MongoException
     */
    protected function getConditions(Filter $filter)
    {
        $conditions = array();
        if (null !== $filter->getStartDate()) {
            $conditions['meta.request_ts']['$gte'] = new \MongoDate(
                $this->getDateTimeFromString($filter->getStartDate(), 'start')->format('U')
            );
        }

        if (null !== $filter->getEndDate()) {
            $conditions['meta.request_ts']['$lte'] = new \MongoDate(
                $this->getDateTimeFromString($filter->getEndDate(), 'end')->format('U')
            );
        }

        if (null !== $filter->getUrl()) {
            $conditions['meta.simple_url'] = new \MongoRegex('/'.preg_quote($filter->getUrl(), '/').'/');
        }

        if (null !== $filter->getIp()) {
            $conditions['meta.SERVER.REMOTE_ADDR'] = $filter->getIp();
        }

        if (null !== $filter->getCookie()) {
            $conditions['meta.SERVER.HTTP_COOKIE'] = new \MongoRegex('/'.preg_quote($filter->getCookie(), '/').'/');
        }

        foreach (array(
                     'method'      => 'method',
                     'application' => 'application',
                     'version'     => 'version',
                     'branch'      => 'branch',
                     'controller'  => 'controller',
                     'action'      => 'action',
                 ) as $dbField => $field) {
            $method = 'get' . ucfirst($field);
            if ($filter->{$method}()) {
                $conditions['meta.' . $dbField] = $filter->{$method}();
            }
        }

        return $conditions;
    }

    /**
     * Get mongo client from config
     *
     * @return \MongoClient
     * @throws MongoConnectionException
     * @throws \MongoConnectionException
     */
    public function getMongoClient()
    {
        if (empty($this->mongoClient)) {
            $this->mongoClient = new \MongoClient($this->config['host'], $this->config['options']);
        }
        return $this->mongoClient;
    }

    /**
     * Set prepared mongo client.
     *
     * @param MongoClient $mongoClient
     */
    public function setMongoClient($mongoClient)
    {
        $this->mongoClient = $mongoClient;
    }

    /**
     * Get connection.
     *
     * @return \MongoDB
     * @throws MongoConnectionException
     * @throws \MongoConnectionException
     */
    public function getConnection()
    {
        if (empty($this->connection)) {
            $this->connection = $this->getMongoClient()->{$this->config['collection']};
        }

        return $this->connection;
    }

    /**
     * Set existing connection
     *
     * @param MongoDB $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Select specific connection
     *
     * @return \MongoCollection
     * @throws Exception
     * @throws \MongoConnectionException
     */
    public function getCollection()
    {
        return $this->getConnection()->selectCollection($this->collectionName);
    }

    /**
     * @param $aggregationFormat
     * @return string
     */
    private function getAggregationDateFormatFromPHP($aggregationFormat)
    {
        switch ($aggregationFormat) {
            case 'Y-m-d H':
            case 'Y-m-d H:00:00':
                return "%Y-%m-%d %H:00:00";
                break;

            case 'Y-m-d H:i':
            case 'Y-m-d H:i:00':
                return "%Y-%m-%d %H:%M:00";
                break;

            default:
                throw new \InvalidArgumentException("Unknown date format");
        }
    }
}
