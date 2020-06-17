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

namespace PhpPerfTools\Controller;

use PhpPerfTools\Controller;
use PhpPerfTools\Profiles;
use PhpPerfTools\Storage\Filter;
use PhpPerfTools\Storage\LimitedFindInterface;
use PhpPerfTools\Storage\WatchedFunctionsStorageInterface;
use Slim\Slim;

/**
 * List, view or delete profiles
 */
class Run extends Controller
{
    /**
     * HTTP GET attribute name for a comma separated filters
     */
    const FILTER_ARGUMENT_NAME = 'filter';

    /**
     * @var Profiles
     */
    private $profiles;

    /**
     * @var WatchedFunctionsStorageInterface
     */
    private $watches;

    /**
     * Default list of columns for main view
     *
     * @var array
     */
    private $defaultMainColumnList = array(
        'column_method',
        'column_url',
        'column_date',
        'column_wt',
        'column_cpu',
        'column_mu',
        'column_pmu',
    );

    /**
     * Default list of columns for url view
     *
     * @var array
     */
    private $defaultUrlViewColumnList = array(
        'column_date',
        'column_wt',
        'column_cpu',
        'column_mu',
        'column_pmu',
    );

    /**
     * \PhpPerfTools\Controller\Run constructor.
     *
     * @param Slim $app
     * @param Profiles $profiles
     * @param WatchedFunctionsStorageInterface $watches
     */
    public function __construct(Slim $app, Profiles $profiles, WatchedFunctionsStorageInterface $watches)
    {
        parent::__construct($app);
        $this->setProfiles($profiles);
        $this->setWatches($watches);
    }

    /**
     * Lost of runs, optionally ordered by various keys, date desc by default.
     */
    public function index()
    {
        $response = $this->app->response();

        // The list changes whenever new profiles are recorded.
        // Generally avoid caching, but allow re-use in browser's bfcache
        // and by cache proxies for concurrent requests.
        // https://github.com/perftools/xhgui/issues/261
        $response->headers->set('Cache-Control', 'public, max-age=0');

        $request = $this->app->request();

        $filter = Filter::fromRequest($request);

        $filtersAsArray = $filter->toArray();

        $profileStorage = $this->getProfiles();
        $hardLimitBanner = false;
        if ($profileStorage->getStorage() instanceof LimitedFindInterface) {
            if ($request->get('force', 'false') == 'true') {
                $profileStorage->getStorage()->setHardLimit(0);
                $filtersAsArray['force'] = 'true';
            } else {
                $hardLimitBanner = true;
            }
        }

        $result = $profileStorage->getAll($filter);

        $title = 'Recent runs';
        $titleMap = array(
            'wt' => 'Longest wall time',
            'cpu' => 'Most CPU time',
            'mu' => 'Highest memory use',
        );
        if (isset($titleMap[$filter->getSort()])) {
            $title = $titleMap[$filter->getSort()];
        }
        $paging = array(
            'total_pages' => $result['totalPages'],
            'page' => $result['page'],
            'sort' => $filter->getSort(),
            'direction' => $result['direction']
        );

        $this->_template = 'runs/list.twig';
        $this->set(array(
            'paging' => $paging,
            'base_url' => 'home',
            'runs' => $result['results'],
            'date_format' => $this->app->config('date_format'),
            'search' => $filtersAsArray,
            'title'=> $title,
            'show_handler_select' => true,
            "main_list_columns" => !empty($_COOKIE['main_list_columns']) ?
                $_COOKIE['main_list_columns'] : $this->defaultMainColumnList,
            "default_list_columns" => $this->defaultMainColumnList,
            "hard_limit_banner" => count($result['results']) != 0 ? $hardLimitBanner : false,
        ));
    }

    /**
     * View one run by id
     */
    public function view()
    {
        $response = $this->app->response();
        // Permalink views to a specific run are meant to be public and immutable.
        // But limit the cache to only a short period of time (enough to allow
        // handling of abuse or other stampedes). This way we don't have to
        // deal with any kind of purging system for when profiles are deleted,
        // or for after xhgui itself is upgraded and static assets may be
        // incompatible etc.
        // https://github.com/perftools/xhgui/issues/261
        $response->headers->set('Cache-Control', 'public, max-age=60, must-revalidate');

        $request = $this->app->request();
        $detailCount = $this->app->config('detail_count');
        $result = $this->getProfiles()->get($request->get('id'));

        $result->calculateSelf();

        // Self wall time graph
        $timeChart = $result->extractDimension('ewt', $detailCount);

        // Memory Block
        $memoryChart = $result->extractDimension('emu', $detailCount);

        // Watched Functions Block
        $watchedFunctions = array();
        foreach ($this->getWatches()->getWatchedFunctions() as $watch) {
            $watchedFunctions = $result->getWatched($watch, $watchedFunctions);
        }

        if (false !== $request->get(self::FILTER_ARGUMENT_NAME, false)) {
            $profile = $result->sort('ewt', $result->filter($result->getProfile(), $this->getFilters()));
        } else {
            $profile = $result->sort('ewt', $result->getProfile());
        }

        $this->_template = 'runs/view.twig';
        $this->set(array(
            'profile' => $profile,
            'result' => $result,
            'wall_time' => $timeChart,
            'memory' => $memoryChart,
            'watches' => $watchedFunctions,
            'date_format' => $this->app->config('date_format'),
            'function_filter' => \PhpPerfTools\Config::read('function_filter', array()),
        ));
    }

    /**
     * Return list of filters
     *
     * @return array
     */
    protected function getFilters()
    {
        $request = $this->app->request();
        $filterString = $request->get(self::FILTER_ARGUMENT_NAME);
        if (strlen($filterString) > 1 && $filterString !== 'true') {
            $filters = array_map('trim', explode(',', $filterString));
        } else {
            $filters = $this->app->config('function_filter');
        }

        return is_array($filters) ? $filters : array();
    }

    /**
     * Delete one run by id, confirmation
     *
     * @throws Exception
     */
    public function deleteForm()
    {
        $request = $this->app->request();
        $id = $request->get('id');
        if (empty($id)) {
            throw new \Exception('The "id" parameter is required.');
        }

        // Get details
        $result = $this->getProfiles()->get($id);

        $this->_template = 'runs/delete-form.twig';
        $this->set(array(
            'run_id' => $id,
            'result' => $result,
        ));
    }

    /**
     * Delete one run by id
     *
     * @throws Exception
     */
    public function deleteSubmit()
    {
        $request = $this->app->request();
        $id = $request->params('id');
        // Don't call profilers->delete() unless $id is set,
        // otherwise it will turn the null into a MongoId and return "Sucessful".
        if (empty($id)) {
            // Form checks this already,
            // only reachable by handcrafted or malformed requests.
            throw new Exception('The "id" parameter is required.');
        }

        // Delete the profile run.
        $this->getProfiles()->delete($id);

        $this->app->flash('success', 'Deleted profile ' . $id);

        $this->app->redirect($this->urlFor('home'));
    }

    /**
     * Delete all runs form
     */
    public function deleteAllForm()
    {
        $this->_template = 'runs/delete-all-form.twig';
    }

    /**
     * Delete all runs processing action
     */
    public function deleteAllSubmit()
    {
        // Delete all profile runs.
        $this->getProfiles()->truncate();

        $this->app->flash('success', 'Deleted all profiles');

        $this->app->redirect($this->urlFor('home'));
    }

    /**
     * Display list of runs for given url
     */
    public function url()
    {
        $request = $this->app->request();

        $filter = Filter::fromRequest($request);
        $filter->setUrl($request->get('url'));

        if (!$filter->hasSort()) {
            $filter->setDirection('asc');
        }
        $result = $this->getProfiles()->getAll($filter);
        $chartData = $this->getProfiles()->getPercentileForUrl(
            90,
            $request->get('url'),
            $filter
        );

        $paging = array(
            'total_pages' => $result['totalPages'],
            'sort' => $filter->getSort(),
            'page' => $result['page'],
            'direction' => $result['direction']
        );

        $this->_template = 'runs/url.twig';
        $this->set(array(
            'paging' => $paging,
            'base_url' => 'url.view',
            'runs' => $result['results'],
            'url' => $filter->getUrl(),
            'chart_data' => $chartData,
            'date_format' => $this->app->config('date_format'),
            'search' => array_merge($filter->toArray(), array('url' => $request->get('url'))),
            "main_list_columns" => !empty($_COOKIE['url_view_list_columns']) ?
                $_COOKIE['url_view_list_columns'] : $this->defaultUrlViewColumnList,
            "default_list_columns" => $this->defaultUrlViewColumnList
        ));
    }

    /**
     * Compare two runs, "base" and "head" from get query
     */
    public function compare()
    {
        $request = $this->app->request();

        $baseRun = $headRun = $candidates = $comparison = null;
        $paging = array();

        if ($request->get('base')) {
            $baseRun = $this->getProfiles()->get($request->get('base'));
        }

        // we have one selected but we need to list other runs.
        if ($baseRun && !$request->get('head')) {
            $filter = Filter::fromRequest($request);
            $filter->setUrl($baseRun->getMeta('simple_url'));

            $candidates = $this->getProfiles()->getAll($filter);

            $paging = array(
                'total_pages' => $candidates['totalPages'],
                'sort' => $filter->getSort(),
                'page' => $candidates['page'],
                'direction' => $candidates['direction']
            );
        }

        if ($request->get('head')) {
            $headRun = $this->getProfiles()->get($request->get('head'));
        }

        if ($baseRun && $headRun) {
            $comparison = $baseRun->compare($headRun);
        }

        $this->_template = 'runs/compare.twig';
        $this->set(array(
            'base_url' => 'run.compare',
            'base_run' => $baseRun,
            'head_run' => $headRun,
            'candidates' => $candidates,
            'url_params' => $request->get(),
            'date_format' => $this->app->config('date_format'),
            'comparison' => $comparison,
            'paging' => $paging,
            'search' => array(
                'base' => $request->get('base'),
                'head' => $request->get('head'),
            ),
            "main_list_columns" => $this->defaultMainColumnList
        ));
    }

    /**
     * Display one symbol (method call) for GIVEN run.
     */
    public function symbol()
    {
        $request = $this->app->request();
        $id = $request->get('id');
        $symbol = $request->get('symbol');

        $profile = $this->getProfiles()->get($id);
        $profile->calculateSelf();
        list($parents, $current, $children) = $profile->getRelatives($symbol);

        $watchedMatch = $this->getWatches()->getWatchedFunctionByName($symbol);
        $this->_template = 'runs/symbol.twig';
        $this->set(array(
            'symbol' => $symbol,
            'id' => $id,
            'main' => $profile->get('main()'),
            'parents' => $parents,
            'current' => $current,
            'children' => $children,
            'watched_function' => $watchedMatch,
        ));
    }

    /**
     * Display shorten info about one symbol for given run.
     * This is used for callgraph popup view/ajax call
     */
    public function symbolShort()
    {
        $request = $this->app->request();
        $id = $request->get('id');
        $threshold = $request->get('threshold');
        $symbol = $request->get('symbol');
        $metric = $request->get('metric');

        $profile = $this->getProfiles()->get($id);
        $profile->calculateSelf();
        list($parents, $current, $children) = $profile->getRelatives($symbol, $metric, $threshold);

        $this->_template = 'runs/symbol-short.twig';
        $this->set(array(
            'symbol' => $symbol,
            'id' => $id,
            'main' => $profile->get('main()'),
            'parents' => $parents,
            'current' => $current,
            'children' => $children,
        ));
    }

    /**
     * Display callgraph
     */
    public function callgraph()
    {
        $request = $this->app->request();
        $profile = $this->getProfiles()->get($request->get('id'));

        $this->_template = 'runs/callgraph.twig';
        $this->set(array(
            'profile' => $profile,
            'date_format' => $this->app->config('date_format'),
        ));
    }

    /**
     * Get callgraph data using ajax
     *
     * @return string
     * @throws Exception
     */
    public function callgraphData($nodes = false)
    {
        $request = $this->app->request();
        $response = $this->app->response();
        $profile = $this->getProfiles()->get($request->get('id'));
        $metric = $request->get('metric') ?: 'wt';
        $threshold = (float)$request->get('threshold') ?: 0.01;

        if ($nodes) {
            $callgraph = $profile->getCallgraphNodes($metric, $threshold);
        } else {
            $callgraph = $profile->getCallgraph($metric, $threshold);
        }

        $response->headers->set('Content-Type', 'application/json');
        return $response->body(json_encode($callgraph));
    }

    /**
     * @return WatchedFunctionsStorageInterface
     */
    public function getWatches()
    {
        return $this->watches;
    }

    /**
     * @param WatchedFunctionsStorageInterface $watches
     */
    public function setWatches($watches)
    {
        $this->watches = $watches;
    }

    /**
     * @return Profiles
     */
    public function getProfiles()
    {
        return $this->profiles;
    }

    /**
     * @param Profiles $profiles
     */
    public function setProfiles($profiles)
    {
        $this->profiles = $profiles;
    }
}
