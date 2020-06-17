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


use Slim\Http\Request;

/**
 * Class Filter
 */
class Filter
{

    /**
     *
     */
    const SORT_WT = 1;

    /**
     *
     */
    const SORT_CPU = 2;

    /**
     *
     */
    const SORT_MU = 3;

    /**
     *
     */
    const SORT_PMU = 4;

    /**
     *
     */
    const SORT_TIME = 5;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var bool
     */
    protected $hasSearch = false;

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var int
     */
    protected $perPage = 25;

    /**
     */
    public function __construct()
    {
        $this->data = array(
            'id'          => null,
            'startDate'   => null,
            'endDate'     => null,
            'url'         => null,
            'method'      => null,
            'sessionId'   => null,
            'controller'  => null,
            'action'      => null,
            'version'     => null,
            'branch'      => null,
            'application' => null,
            'sort'        => null,
            'direction'   => null,
            'cookie'      => null,
            'ip'          => null,
        );

        $this->perPage = \PhpPerfTools\Config::read('rows_per_page', 25);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $ret = array();
        foreach ($this->data as $key => $value) {
            if (isset($this->data[$key])) {
                $ret[$key] = $value;
            }
        }

        return $ret;
    }

    /**
     * @param $request
     * @return Filter
     */
    public static function fromRequest(Request $request)
    {
        $instance = new self;

        $instance->setUrl($request->get('url', null));
        $instance->setStartDate($request->get('startDate', null));
        $instance->setEndDate($request->get('endDate', null));

        $instance->setSort($request->get('sort', 'time'));
        $instance->setDirection($request->get('direction', 'desc'));

        $instance->setPage($request->get('page', null));

        $instance->setCookie($request->get('cookie', null));
        $instance->setIp($request->get('remote_addr', null));

        if ($request->get('application')) {
            $instance->setApplication($request->get('application'));
        }
        if ($request->get('controller')) {
            $instance->setController($request->get('controller'));
        }
        if ($request->get('action')) {
            $instance->setAction($request->get('action'));
        }

        return $instance;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->data['id'];
    }

    /**
     * @param string $id
     * @return Filter
     */
    public function setId($id)
    {
        $this->hasSearch = true;
        $this->data['id'] = $id;
        return $this;
    }

    /**
     * @returnDateTime
     */
    public function getStartDate()
    {
        return $this->data['startDate'];
    }

    /**
     * @paramDateTime $startDate
     * @return Filter
     */
    public function setStartDate($startDate)
    {
        if (empty($startDate)) {
            return $this;
        }
        $this->hasSearch = true;

        $this->data['startDate'] = !empty($startDate) ? $startDate : null;
        return $this;
    }

    /**
     * @returnDateTime
     */
    public function getEndDate()
    {
        return $this->data['endDate'];
    }

    /**
     * @paramDateTime $endDate
     * @return Filter
     */
    public function setEndDate($endDate)
    {
        if (empty($endDate)) {
            return $this;
        }
        $this->hasSearch = true;

        $this->data['endDate'] = $endDate;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->data['url'];
    }

    /**
     * @param string $url
     * @return Filter
     */
    public function setUrl($url)
    {
        if (empty($url)) {
            return $this;
        }
        $this->hasSearch = true;

        $this->data['url'] = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->data['method'];
    }

    /**
     * @param string $method
     * @return Filter
     */
    public function setMethod($method)
    {
        $this->hasSearch = true;

        $this->data['method'] = $method;
        return $this;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->data['sessionId'];
    }

    /**
     * @param string $sessionId
     * @return Filter
     */
    public function setSessionId($sessionId)
    {
        $this->hasSearch = true;

        $this->data['sessionId'] = $sessionId;
        return $this;
    }

    /**
     * @return array
     */
    public function getController()
    {
        return $this->data['controller'];
    }

    /**
     * @param string $controller
     * @return Filter
     */
    public function setController($controller)
    {
        $this->hasSearch = true;

        $this->data['controller'] = $this->prepareMultiValueData($controller);
        return $this;
    }

    /**
     * @return array
     */
    public function getAction()
    {
        return $this->data['action'];
    }

    /**
     * @param string $action
     * @return Filter
     */
    public function setAction($action)
    {
        $this->hasSearch = true;

        $this->data['action'] = $this->prepareMultiValueData($action);
        return $this;
    }

    /**
     * @return array
     */
    public function getVersion()
    {
        return $this->data['version'];
    }

    /**
     * @param string $version
     * @return Filter
     */
    public function setVersion($version)
    {
        $this->hasSearch = true;

        $this->data['version'] = $this->prepareMultiValueData($version);
        return $this;
    }

    /**
     * @return array
     */
    public function getBranch()
    {
        return $this->data['branch'];
    }

    /**
     * @param string $branch
     * @return Filter
     */
    public function setBranch($branch)
    {
        $this->hasSearch = true;

        $this->data['branch'] = $this->prepareMultiValueData($branch);
        return $this;
    }

    /**
     * @return string
     */
    public function getApplication()
    {
        return $this->data['application'];
    }

    /**
     * @param string $application
     * @return Filter
     */
    public function setApplication($application)
    {
        $this->hasSearch = true;

        $this->data['application'] = $this->prepareMultiValueData($application);
        return $this;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param int $page
     * @return Filter
     */
    public function setPage($page)
    {
        if (empty($page)) {
            return $this;
        }
        $this->page = $page;
        return $this;
    }

    /**
     * @return int
     */
    public function getPerPage()
    {
        return $this->perPage;
    }

    /**
     * @param int $perPage
     * @return Filter
     */
    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * @return string
     */
    public function getSort()
    {
        return $this->data['sort'];
    }

    /**
     * @param string $sort
     * @return Filter
     */
    public function setSort($sort)
    {
        if (empty($sort)) {
            return $this;
        }
        $this->data['sort'] = $sort;
        return $this;
    }

    /**
     * @return Filter
     */
    public function clearSort()
    {
        $this->data['sort'] = null;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasSort()
    {
        if (empty($this->data['sort'])) {
            return false;
        }
        // this is default sort!! we treat it as false to skip sort of data
        if ($this->data['sort'] === 'time' && $this->data['direction'] === 'desc') {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getDirection()
    {
        return $this->data['direction'];
    }

    /**
     * @param string $direction
     * @return Filter
     */
    public function setDirection($direction)
    {
        if (empty($direction)) {
            return $this;
        }
        $this->data['direction'] = $direction;
        return $this;
    }

    /**
     * @return string
     */
    public function hasDirection()
    {
        return !empty($this->data['direction']);
    }

    /**
     * @return string
     */
    public function getCookie()
    {
        return $this->data['cookie'];
    }

    /**
     * @param string $cookie
     * @return Filter
     */
    public function setCookie($cookie)
    {
        if (empty($cookie)) {
            return $this;
        }
        $this->data['cookie'] = $cookie;
        return $this;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->data['ip'];
    }

    /**
     * @param string $ip
     * @return Filter
     */
    public function setIp($ip)
    {
        if (empty($ip)) {
            return $this;
        }
        $this->data['ip'] = $ip;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasSearch()
    {
        return $this->hasSearch;
    }

    /**
     * @param bool $hasSearch
     * @return Filter
     */
    public function setHasSearch($hasSearch)
    {
        $this->hasSearch = $hasSearch;
        return $this;
    }

    /**
     * @param $data
     *
     * @return array|false|string[]
     */
    protected function prepareMultiValueData($data)
    {
        $data = \preg_split('/[\s,]+/', $data, null, PREG_SPLIT_NO_EMPTY) ?: array();
        \array_walk($data, function ($row) {
            return trim($row);
        });

        return $data;
    }
}
