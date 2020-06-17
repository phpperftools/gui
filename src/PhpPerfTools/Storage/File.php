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

class File extends AbstractStorage implements
    StorageInterface,
    WatchedFunctionsStorageInterface,
    LimitedFindInterface,
    UserStorageInterface
{
    /**
     * @var string
     */
    protected $path = '../data/';

    /**
     * @var string
     */
    protected $prefix = 'phpperftools.data';

    /**
     * @var mixed
     */
    protected $dataSerializer;

    /**
     * @var mixed
     */
    protected $metaSerializer;

    /**
     * @var string
     */
    protected $watchedFunctionsPathPrefix = '../watched_functions/';

    /**
     * @var string
     */
    protected $usersPathPrefix = '../users/';

    /**
     * @var int[]
     */
    protected $countCache;

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var int
     */
    protected $hardLimit;

    /**
     * @param $config
     */
    public function __construct($config)
    {
        $this->prefix = !empty($config['prefix']) ? $config['prefix'] : 'phpperftools.data';

        $this->path = $config['path'];
        $this->dataSerializer = !empty($config['serializer']) ? $config['serializer'] : 'json';
        $this->metaSerializer = !empty($config['meta_serializer']) ? $config['meta_serializer'] : 'php';

        $this->setHardLimit(!empty($config['list_hard_limit']) ? $config['list_hard_limit'] : 500);
    }

    /**
     * @inheritDoc
     * @param Filter $filter
     * @param bool $includeProfiles
     * @return ResultSet
     */
    public function find(Filter $filter, $includeProfiles = false)
    {
        if ($filter->getId()) {
            $result = glob($this->path . DIRECTORY_SEPARATOR . $filter->getId());
        } else {
            $result = glob($this->path . $this->prefix . '*');
            sort($result);
        }

        if (count($result) < 0 && $this->bypassHardLimit() == false && count($result) < $this->getHardLimit()) {
            throw new \LengthException("Number of profiles is too big. You can force unlimited profiles but that will disable sorting.");
        }

        if ($this->bypassHardLimit()) {
            $filter->clearSort();
        }

        $offset = $filter->getPerPage() * ($filter->getPage() - 1);

        $ret = array();
        foreach ($result as $i => $file) {
            // skip meta files.
            if (strpos($file, '.meta') !== false) {
                continue;
            }

            // try to detect timestamp in filename, to optimize searching.
            // If that fails we need to get it after file import from meta.
            $reqTimeFromFilename = $this->getRequestTimeFromFilename($file);
            if (!empty($reqTimeFromFilename)) {
                if (null !== $filter->getStartDate() &&
                    $this->getDateTimeFromString($filter->getStartDate(), 'start') >= $reqTimeFromFilename) {
                    continue;
                }

                if (null !== $filter->getEndDate() &&
                    $this->getDateTimeFromString($filter->getEndDate(), 'end') <= $reqTimeFromFilename) {
                    continue;
                }
            }

            $metaFile = $this->getMetafileNameFromProfileName($file);

            $meta = $this->importFile($metaFile, true);
            if ($meta === false) {
                continue;
            }

            if (empty($reqTimeFromFilename) && (null !== $filter->getStartDate() || null !== $filter->getEndDate())) {
                if (!empty($meta['request_ts_micro']['sec'])) {
                    $requestDateTime = \DateTime::createFromFormat(
                        'U u',
                        $meta['request_ts_micro']['sec'].' '.$meta['request_ts_micro']['usec']
                    );
                } else {
                    $requestDateTime = \DateTime::createFromFormat('U', $meta['request_ts']['sec']);
                }
                if (null !== $filter->getStartDate() &&
                    $this->getDateTimeFromString($filter->getStartDate(), 'start') >= $requestDateTime) {
                    continue;
                }
                if (null !== $filter->getEndDate() &&
                    $this->getDateTimeFromString($filter->getEndDate(), 'end') <= $requestDateTime) {
                    continue;
                }
            }

            if ($filter->getUrl() &&
                strpos($meta['simple_url'], $filter->getUrl()) === false &&
                strpos($meta['SERVER']['SERVER_NAME'] . $meta['simple_url'], $filter->getUrl()) === false
            ) {
                continue;
            }

            if (null !== $filter->getCookie() &&
                strpos($meta['SERVER']['HTTP_COOKIE'], $filter->getCookie()) === false
            ) {
                continue;
            }

            if (null !== $filter->getIp() && $meta['SERVER']['REMOTE_ADDR'] !== $filter->getIp()) {
                continue;
            }

            if ($filter->getApplication() && !empty($meta['application']) && !\in_array($meta['application'], $filter->getApplication(),true)) {
                continue;
            }

            if ($filter->getController() && !empty($meta['controller']) && !\in_array($meta['controller'], $filter->getController(),true)) {
                continue;
            }

            if ($filter->getAction() && !empty($meta['action']) && !\in_array($meta['action'], $filter->getAction(),true)) {
                continue;
            }

            if ($filter->getVersion() && !empty($meta['version']) && !\in_array($meta['version'], $filter->getVersion(),true)) {
                continue;
            }



            if (!empty($profile['_id'])) {
                $id = $profile['_id'];
            } else {
                $id = basename($file);
            }
            $ret[$id] = array(
                '_id'     => $id,
                'meta'    => $meta,
            );

            if (!$this->bypassHardLimit() && $this->getHardLimit() < $i) {
                break;
            }
        }

        try {
            if (!empty($ret) && $filter->hasSort()) {
                $this->filter = $filter;
                uasort($ret, array($this, 'sortByColumn'));
                unset($this->filter);
            }
        } catch (InvalidArgumentException $e) {
            // ignore for now.
        }

        $cacheId = md5(serialize($filter->toArray()));

        $this->countCache[$cacheId] = count($ret);
        $ret = array_slice($ret, $offset, $filter->getPerPage(), true);

        return new ResultSet($ret, $this->countCache[$cacheId]);
    }

    /**
     * @inheritDoc
     * @param Filter $filter
     * @return int
     */
    public function count(Filter $filter)
    {
        $cacheId = md5(serialize($filter->toArray()));
        if (empty($this->countCache[$cacheId])) {
            $this->find($filter);
        }
        return $this->countCache[$cacheId];
    }

    /**
     * @inheritDoc
     * @param $id
     * @return mixed
     */
    public function findOne($id)
    {

        $file =$this->path . DIRECTORY_SEPARATOR .$id;
        $meta = $this->importFile($file.'.meta');
        $profile = $this->importFile($file);

        return array('_id'=>$id, 'meta'=>$meta, 'profile'=>$profile);
    }

    /**
     * @inheritDoc
     * @param $id
     * @return bool
     */
    public function remove($id)
    {
        if (file_exists($this->path . $id)) {
            $metaFileName = $this->getMetafileNameFromProfileName($id);
            if (file_exists($this->path . $metaFileName)) {
                unlink($this->path . $metaFileName);
            }
            unlink($this->path . $id);
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function drop()
    {
        array_map('unlink', glob($this->path . '*.xhprof'));
        array_map('unlink', glob($this->path . '*.meta'));
    }

    /**
     * @inheritDoc
     * @param $match
     * @param $col
     * @param int $percentile
     * @return array
     */
    public function aggregate(Filter $filter, $percentile = 1, $aggregationFormat = 'Y-m-d H:i:00')
    {
        $filter->setDirection('desc');
        $ret = $this->find($filter);

        $result = array();
        foreach ($ret as $row) {
            $date = \DateTime::createFromFormat(
                'U u',
                $row['meta']['request_ts_micro']['sec'] . ' ' . $row['meta']['request_ts_micro']['usec']
            );
            $formattedDate = $date->format($aggregationFormat);

            if (empty($result[$formattedDate])) {
                $result[$formattedDate] = array(
                    '_id'        => '',
                    'row_count'  => 0,
                    'raw_index'  => 0,
                    'wall_times' => array(),
                    'cpu_times'  => array(),
                    'mu_times'   => array(),
                    'pmu_times'  => array(),
                );
            }

            $result[$formattedDate]['wall_times'][] = $row['meta']['summary']['wt'];
            $result[$formattedDate]['cpu_times'][] = !empty($row['meta']['summary']['cpu']) ? $row['meta']['summary']['cpu'] : 0;
            $result[$formattedDate]['mu_times'][] = !empty($row['meta']['summary']['mu']) ? $row['meta']['summary']['mu'] : 0;
            $result[$formattedDate]['pmu_times'][] = !empty($row['meta']['summary']['pmu']) ? $row['meta']['summary']['pmu'] : 0;
            $result[$formattedDate]['row_count']++;

            $result[$formattedDate]['raw_index'] =
                $result[$formattedDate]['row_count'] * ($percentile / 100);

            $result[$formattedDate]['_id'] = $date->format($aggregationFormat);
        }

        return array(
            'ok'     => 1,
            'result' => \array_values($result),
        );
    }


    /**
     * Column sorter
     *
     * @param $a
     * @param $b
     * @return int
     */
    public function sortByColumn($a, $b)
    {
        $sort = $this->filter->getSort();
        switch ($sort) {
            case 'ct':
            case 'wt':
            case 'cpu':
            case 'mu':
            case 'pmu':
                $aValue = (!empty($a['meta']['summary'][$sort]) ? $a['meta']['summary'][$sort] : 0);
                $bValue = (!empty($b['meta']['summary'][$sort]) ? $b['meta']['summary'][$sort] : 0);
                break;

            case 'time':
                $aValue = $a['meta']['request_ts']['sec'];
                $bValue = $b['meta']['request_ts']['sec'];
                break;

            case 'controller':
            case 'action':
            case 'application':
            case 'branch':
            case 'version':
                $aValue = $a['meta'][$sort];
                $bValue = $b['meta'][$sort];
                break;

            default:
                throw new InvalidArgumentException('Invalid sort mode');
                break;
        }

        if ($aValue == $bValue) {
            return 0;
        }

        if (is_numeric($aValue) || is_numeric($bValue)) {
            if ($this->filter->getDirection() === 'desc') {
                if ($aValue < $bValue) {
                    return 1;
                }
                return -1;
            }

            if ($aValue > $bValue) {
                return 1;
            }
            return -1;
        }

        if ($this->filter->getDirection() === 'desc') {
            return strnatcmp($aValue, $bValue);
        }
        return strnatcmp($bValue, $aValue);
    }

    /**
     * Generate meta profile name from profile file name.
     *
     * In most cases just add .meta extension
     *
     * @param $file
     * @return mixed
     */
    protected function getMetafileNameFromProfileName($file)
    {
        $metaFile = $file . '.meta';
        return $metaFile;
    }

    /**
     * Load profile file from disk, prepare it and return parsed array
     *
     * @param $path
     * @param bool $meta
     * @return mixed
     */
    protected function importFile($path, $meta = false)
    {
        if ($meta) {
            $serializer = $this->metaSerializer;
        } else {
            $serializer = $this->dataSerializer;
        }

        if (!file_exists($path) || !is_readable($path)) {
            return false;
        }

        switch ($serializer) {
            default:
            case 'json':
                return json_decode(file_get_contents($path), true);

            case 'serialize':
                if (PHP_MAJOR_VERSION > 7) {
                    return unserialize(file_get_contents($path), false);
                }
                /** @noinspection UnserializeExploitsInspection */
                return unserialize(file_get_contents($path));

            case 'igbinary_serialize':
            case 'igbinary_unserialize':
            case 'igbinary':
                /** @noinspection PhpComposerExtensionStubsInspection */
                return igbinary_unserialize(file_get_contents($path));

            // this is a path to a file on disk
            case 'php':
            case 'var_export':
                /** @noinspection PhpIncludeInspection */
                return include $path;
        }
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function getWatchedFunctions()
    {
        $ret = array();
        $files = glob($this->watchedFunctionsPathPrefix . '*.json');
        foreach ($files as $file) {
            $ret[] = json_decode(file_get_contents($file), true);
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
        $id = md5($name);
        $i = file_put_contents(
            $this->watchedFunctionsPathPrefix . $id . '.json',
            json_encode(array('id' => $id, 'name' => $name, 'options'=>$options))
        );
        return $i > 0;
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

        $i = file_put_contents(
            $this->watchedFunctionsPathPrefix . $id . '.json',
            json_encode(array('id' => $id, 'name' => $name, 'options'=>$options))
        );
        return $i > 0;
    }

    /**
     * @inheritDoc
     * @param $id
     */
    public function removeWatchedFunction($id)
    {
        if (file_exists($this->watchedFunctionsPathPrefix . $id . '.json')) {
            unlink($this->watchedFunctionsPathPrefix . $id . '.json');
        }
    }

    /**
     * @inheritDoc
     */
    public function getWatchedFunctionByName($name)
    {
        foreach($this->getWatchedFunctions() as $function) {
            if ($function['name'] == $name || \preg_match('`^' . $function['name'] . '$`', $name)) {
                return $function;
            }
        }
        return array();
    }

    /**
     * Parse filename and try to get request time from filename
     *
     * @param $fileName
     * @return bool \DateTime
     */
    public function getRequestTimeFromFilename($fileName)
    {
        $matches = array();
        // default pattern is: phpperftools.data.<timestamp>.<microseconds>_a68888
        //  phpperftools.data.15 55 31 04 66 .6606_a68888
        preg_match('/(?<t>[\d]{10})(\.(?<m>[\d]{1,6}))?.+/i', $fileName, $matches);
        try {
            return \DateTime::createFromFormat('U u', $matches['t'] . ' ' . $matches['m']);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getHardLimit()
    {
        return $this->hardLimit;
    }

    /**
     * @param int $hardLimit
     */
    public function setHardLimit($hardLimit)
    {
        $this->hardLimit = $hardLimit;
    }

    /**
     * @return bool
     */
    public function bypassHardLimit()
    {
        return $this->getHardLimit() == 0;
    }

    /**
     * @inheritDoc
     */
    public function getAllUsers()
    {
        $ret = array();
        $files = glob($this->usersPathPrefix . '*.json');
        foreach ($files as $file) {
            $ret[] = json_decode(file_get_contents($file), true);
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    public function addUser($email, $name)
    {
        $id = md5($email);
        $user = array(
            'id' => $id,
            'email' => $email,
            'name'  => $name,
        );

        \file_put_contents($this->usersPathPrefix.$id.'.json', \json_encode($user));
        return $id;
    }

    /**
     * @inheritDoc
     */
    public function removeUser($userId)
    {
        if (\file_exists($this->usersPathPrefix . $userId . '.json')) {
            unlink($this->usersPathPrefix . $userId . '.json');
        }
    }
}
