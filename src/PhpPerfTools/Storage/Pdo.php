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
 * Get profiles using PDO database connection
 */
class Pdo extends AbstractStorage implements StorageInterface, WatchedFunctionsStorageInterface
{
    /**
     * @var \PDO
     */
    protected $connection;

    /**
     * @var string
     */
    protected $driver = false;

    /**
     * Mapping between filter fields and sql fields
     *
     * @var array
     */
    private $sqlToFilterFields = array(
        'url' => 'url',
        'method' => 'method',
        'application' => 'application',
        'version' => 'version',
        'branch' => 'branch',
        'controller' => 'controller',
        'action' => 'action',
        'cookie' => 'cookie',
        'remote_addr' => 'ip',
    );

    /**
     * PDO constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->connection = new \PDO(
            $config['dsn'],
            !empty($config['user']) ? $config['user'] : null,
            !empty($config['password']) ? $config['password'] : null,
            !empty($config['options']) ? $config['options'] : array()
        );
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        if (!empty($config['dsn'])) {
            $this->driver = substr($config['dsn'], 0, strpos($config['dsn'], ':'));
        }
    }

    /**
     * @inheritDoc
     * @param Filter $filter
     * @param bool $includeProfiles
     * @return ResultSet
     */
    public function find(Filter $filter, $includeProfiles = false)
    {
        list($query, $params) = $this->getQuery($filter, false, $includeProfiles);

        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
        } catch (\Exception $e) {
            print_r($e->getMessage());
            exit;
        }

        $tmp = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $meta = json_decode($row['meta'], true);
            if ($filter->getCookie()) {
                // because cookie is not parsed and stored in separate structure we need to double check if
                // value that we search is in fact in http cookie server field. SQL filter only checks whole
                // meta
                if (strpos($meta['SERVER']['HTTP_COOKIE'], $filter->getCookie()) === false) {
                    continue;
                }
            }
            $tmp[$row['id']] = $row;
            if (!empty($row['profiles'])) {
                $tmp[$row['id']]['profile'] = json_decode($row['profiles'], true);
                unset($row['profiles'], $tmp[$row['id']]['profiles']);
            } else {
                $tmp[$row['id']]['profile'] = array();
            }

            $tmp[$row['id']]['meta'] = $meta;
            $tmp[$row['id']]['meta']['summary'] = array(
                'wt'    =>$row['main_wt'],
                'cpu'   =>$row['main_cpu'],
                'mu'    =>$row['main_mu'],
                'pmu'   =>$row['main_pmu'],
            );
        }

        return new ResultSet($tmp);
    }

    /**
     * Get query that is used for both list and count
     *
     * @param Filter $filter
     * @param bool $count
     * @return array
     */
    protected function getQuery(Filter $filter, $count = false, $includeProfiles = false)
    {
        $params = array();

        if ($count === true) {
            $columns = ' count(*) as c ';
        } elseif ($includeProfiles === true) {
            $columns = ' i.*, m.*, i.id as _id, main_wt as duration, p.profiles ';
        } else {
            $columns = ' i.*, m.*, i.id as _id, main_wt as duration ';
        }

        if ($includeProfiles === true) {
            $sql = "
select 
    $columns
from 
    profiles_info as i LEFT JOIN
    profiles_meta as m on (i.id = m.profile_id) left join 
    `profiles` as p on (p.profile_id = i.id) 
";
        } else {
            $sql = "
select 
    $columns
from 
    profiles_info as i LEFT JOIN
    profiles_meta as m on (i.id = m.profile_id)
";
        }

        $where = array();

        foreach ($this->sqlToFilterFields as $dbField => $field) {
            $method = 'get' . ucfirst($field);

            if ($filter->{$method}()) {
                switch ($field) {
                    case 'url':
                        $url = $filter->{$method}();
                        $where[] = ' ( url like :url OR simple_url like :simple_url)';
                        $params['url'] = '%' . $url . '%';
                        $params['simple_url'] = '%' . $url . '%';
                        break;

                    case 'action':
                    case 'controller':
                    case 'application':
                    case 'version':
                        $subWhere = array();
                        foreach($filter->{$method}() as $i => $search) {
                            $subWhere[] = ' ' . $dbField . ' = :' . $field.$i . ' ';
                            $params[$field.$i] = $search ;
                        }
                        $where[] = ' ( '.join(' OR ', $subWhere).' ) ';
                        break;

                    case 'cookie':
                        // @todo move this to driver specific storage class
                        list($where, $params) = $this->compareWithJson(
                            $where,
                            $params,
                            $field,
                            $filter->{$method}(),
                            'meta',
                            array('SERVER', 'HTTP_COOKIE')
                        );
                        break;

                    default:
                        $where[] = ' ' . $dbField . ' = :' . $field . ' ';
                        $params[$field] = $filter->{$method}();
                        break;
                }
            }
        }

        if ($filter->getStartDate()) {
            $where[] = ' request_time >= :startDate';
            $params['startDate'] = $this->getDateTimeFromString($filter->getStartDate(), 'start')
                                        ->format('Y-m-d H:i:s');
        }

        if ($filter->getEndDate()) {
            $where[] = ' request_time <= :endDate';
            $params['endDate'] = $this->getDateTimeFromString($filter->getEndDate(), 'end')
                                      ->format('Y-m-d H:i:s');
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . join(' AND ', $where);
        }

        if ($count === true) {
            return array($sql, $params);
        }

        switch ($filter->getSort()) {
            case 'ct':
                $sql .= ' order by main_ct';
                break;

            case 'wt':
                $sql .= ' order by main_wt';
                break;

            case 'cpu':
                $sql .= ' order by main_cpu';
                break;

            case 'mu':
                $sql .= ' order by main_mu';
                break;

            case 'pmu':
                $sql .= ' order by main_pmu';
                break;

            case 'controller':
                $sql .= ' order by controller';
                break;

            case 'action':
                $sql .= ' order by action';
                break;

            case 'application':
                $sql .= ' order by application';
                break;

            case 'branch':
                $sql .= ' order by branch';
                break;

            case 'version':
                $sql .= ' order by version';
                break;

            case 'time':
            default:
                $sql .= ' order by request_time';
                break;
        }

        switch ($filter->getDirection()) {
            case 'asc':
                $sql .= ' asc ';
                break;

            default:
            case 'desc':
                $sql .= ' desc ';
                break;
        }

        if ($filter->getPerPage()) {
            $sql .= ' LIMIT :limit ';
            $params['limit'] = (int)$filter->getPerPage();
        }

        if ($filter->getPage()) {
            $sql .= ' OFFSET :offset ';
            $params['offset'] = (int)($filter->getPerPage() * ($filter->getPage() - 1));
        }

        return array($sql, $params);
    }

    /**
     * @inheritDoc
     * @param Filter $filter
     * @param $col
     * @param int $percentile
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    public function aggregate(Filter $filter, $percentile = 1, $aggregationFormat = 'Y-m-d H:i:00')
    {
        list($sql, $params) = $this->getQuery($filter);

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $aggregatedData = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $date = new \DateTime($row['request_time']);
            $formattedDate = $date->format($aggregationFormat);
            if (empty($aggregatedData[$formattedDate])) {
                $aggregatedData[$formattedDate] = array(
                    '_id'        => '',
                    'row_count'  => 0,
                    'raw_index'  => 0,
                    'wall_times' => array(),
                    'cpu_times'  => array(),
                    'mu_times'   => array(),
                    'pmu_times'  => array(),
                );
            }

            $aggregatedData[$formattedDate]['_id'] = $date->format($aggregationFormat);
            $aggregatedData[$formattedDate]['wall_times'][] = (int)$row['main_wt'];
            $aggregatedData[$formattedDate]['cpu_times'][] = (int)$row['main_cpu'];
            $aggregatedData[$formattedDate]['mu_times'][] = (int)$row['main_mu'];
            $aggregatedData[$formattedDate]['pmu_times'][] = (int)$row['main_pmu'];
            $aggregatedData[$formattedDate]['row_count']++;
            $aggregatedData[$formattedDate]['raw_index'] =
                $aggregatedData[$formattedDate]['row_count'] * ($percentile / 100);
        }

        $return = array(
            'ok'     => 1,
            'result' => array_values($aggregatedData),
        );
        return $return;
    }

    /**
     * @inheritDoc
     * @@param Filter $filter
     * @return int
     */
    public function count(Filter $filter)
    {
        list($query, $params) = $this->getQuery($filter, true);
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
        } catch (\Exception $e) {
            print_r($e->getMessage());
            exit;
        }

        $ret = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($ret['c'])) {
            return $ret['c'];
        }
        return 0;
    }

    /**
     * @inheritDoc
     * @param $id
     * @return mixed
     */
    public function findOne($id)
    {
        $stmt = $this->connection->prepare('
select 
    * 
from 
    profiles as p left join 
    profiles_info as i on (p.profile_id = i.id) LEFT JOIN
    profiles_meta as m on (p.profile_id = m.profile_id)
where 
    p.profile_id = :id
');

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $row['profile'] = json_decode($row['profiles'], true);
        $row['meta'] = json_decode($row['meta'], true);
        $row['_id'] = $id;

        return $row;
    }

    /**
     * @inheritDoc
     * @param $id
     */
    public function remove($id)
    {
        $this->connection->beginTransaction();
        try {
            $profileStmt = $this->connection->prepare('delete from profiles where profile_id = :id');
            $profileStmt->execute(array('id' => $id));

            $metaStmt = $this->connection->prepare('delete from profiles_meta where profile_id = :id');
            $metaStmt->execute(array('id' => $id));

            $infoStmt = $this->connection->prepare('delete from profiles_info where id = :id');
            $infoStmt->execute(array('id' => $id));

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
        }
    }

    /**
     * @inheritDoc
     * Remove all data from profile tables
     */
    public function drop()
    {
        $this->connection->exec('delete from profiles');
        $this->connection->exec('delete from profiles_meta');
        $this->connection->exec('delete from profiles_info');
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function getWatchedFunctions()
    {
        $stmt = $this->connection->query('select * from watched order by name desc');
        $return = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $return[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'options' => array(
                    'counted' => $row['counted'],
                    'grouped' => $row['grouped'],
                ),
            );
        }
        return $return;
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

        $stmt = $this->connection->prepare('INSERT INTO watched (name, counted, grouped) VALUES (:name, :counted, :grouped)');
        $stmt->execute(array(
            'name' => trim($name),
            'counted' => !empty($options['counted']),
            'grouped' => !empty($options['grouped']),
        ));
        return true;
    }

    /**
     * @inheritDoc
     * @param $id
     * @param $name
     */
    public function updateWatchedFunction($id, $name, array $options = array())
    {
        $stmt = $this->connection->prepare('update watched set name=:name, counted = :counted, grouped = :grouped where id = :id');
        $stmt->execute(array(
            'id' => $id,
            'name' => $name,
            'counted' => !empty($options['counted']) ? (int)$options['counted'] : 0,
            'grouped' => !empty($options['grouped']) ? (int)$options['grouped'] : 0,
        ));
    }

    /**
     * @inheritDoc
     * @param $id
     */
    public function removeWatchedFunction($id)
    {
        $stmt = $this->connection->prepare('delete from watched where id = :id');
        $stmt->execute(array('id' => $id));
    }

    /**
     * @param string $name
     *
     * @return array|mixed
     */
    public function getWatchedFunctionByName($name)
    {
        $stmt = $this->connection->prepare('select * from watched where name=:name limit 1');
        $stmt->execute(array('name'=>$name));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * This method will look into json stored data in native way (if db supports that) and it will match row based on
     * that.
     *
     * @param array $where
     * @param array $params
     * @param $field
     * @param $value
     * @param $fieldToLookIn
     * @param array $path
     * @return array
     * @todo this should be moved to engine specific storage classes in the future.
     */
    protected function compareWithJson(array $where, array $params, $field, $value, $fieldToLookIn, array $path)
    {
        switch ($this->driver) {
            case 'mysql':
                $where[] = ' JSON_EXTRACT(' . $fieldToLookIn . ", '$." . join('.', $path) . "') like :cookie";
                $params[$field] = '%' . $value . '%';
                break;

            case 'pgsql':
                // to match using like we need to cast last leaf to a string.
                $lastElement = array_pop($path);
                $where[] = ' ' . $fieldToLookIn . "->'" . join("'->'", $path)
                    . "'->>'" . $lastElement . "' like :param_1";
                $params['param_1'] = '%' . $value . '%';

                break;
            default:
                $where[] = ' ' . $fieldToLookIn . ' like :param_1 ';
                $params['param_1'] = '%' . $value . '%';
                break;
        }
        return array($where, $params);
    }
}
