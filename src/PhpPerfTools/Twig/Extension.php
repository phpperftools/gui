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

namespace PhpPerfTools\Twig;

use Slim\Slim;

/**
 * Class Extension
 * @package PhpPerfTools\Twig
 */
class Extension extends \Twig_Extension
{
    /**
     * @var
     */
    protected $_app;

    /**
     * Extension constructor.
     * @param $app
     */
    public function __construct(Slim $app)
    {
        $this->_app = $app;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'PhpPerfTools';
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return array(
            'url' => new \Twig_Function_Method($this, 'url'),
            'static' => new \Twig_Function_Method($this, 'staticUrl'),
            'percent' => new \Twig_Function_Method($this, 'makePercent', array(
                'is_safe' => array('html')
            )),
        );
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return array(
            'simple_url' => new \Twig_Filter_Function('PhpPerfTools\Util::simpleUrl'),
            'as_bytes' => new \Twig_Filter_Method($this, 'formatBytes', array('is_safe' => array('html'))),
            'as_time' => new \Twig_Filter_Method($this, 'formatTime', array('is_safe' => array('html'))),
            'as_diff' => new \Twig_Filter_Method($this, 'formatDiff', array('is_safe' => array('html'))),
            'as_percent' => new \Twig_Filter_Method($this, 'formatPercent', array('is_safe' => array('html'))),
            'truncate' => new \Twig_Filter_Method($this, 'truncate'),
            'debug' => new \Twig_Filter_Method($this, 'debug'),
        );
    }

    /**
     * @param $value
     */
    public function debug($value) {

        return '<pre>'.print_r($value, 1).'</pre>';
    }

    /**
     * @return string
     */
    protected function _getBase()
    {
        $base = dirname($_SERVER['PHP_SELF']);
        if ($base == '/') {
            return '';
        }
        return $base;
    }

    /**
     * @param $input
     * @param int $length
     * @return string
     */
    public function truncate($input, $length = 50)
    {
        if (strlen($input) < $length) {
            return $input;
        }
        return substr($input, 0, $length) . "\xe2\x80\xa6";
    }

    /**
     * Get a URL for PhpPerfTools.
     *
     * @param string $name The file/path you want a link to
     * @param array $queryargs Additional querystring arguments.
     * @return string url.
     */
    public function url($name, $queryargs = array())
    {
        $query = '';

        $c = \PhpPerfTools\Config::getCurrentHandlerConfig();
        if (!empty($c['id'])) {
            $queryargs['handler'] = $c['id'];
        }

        if (!empty($queryargs)) {
            foreach($queryargs as $key => $val) {
                if (\is_array($val) AND !empty($val)) {
                    $queryargs[$key] = join(',', $val);
                }
            }

            $query = '?' . http_build_query($queryargs);
        }
        return $this->_app->urlFor($name)  . $query;
    }

    /**
     * Get the URL for static content relative to webroot
     *
     * @param string $url The file/path you want a link to
     * @return string url.
     */
    public function staticUrl($url)
    {
        $rootUri = $this->_app->request()->getRootUri();

        // Get URL part prepending index.php
        $indexPos = strpos($rootUri, 'index.php');
        if ($indexPos > 0) {
            return substr($rootUri, 0, $indexPos) . $url;
        }
        return $rootUri . '/' . $url;
    }

    /**
     * @param $value
     * @return string
     */
    public function formatBytes($value)
    {
        return number_format((float)$value) . '&nbsp;<span class="units">bytes</span>';
    }

    /**
     * @param $value
     * @return string
     */
    public function formatTime($value)
    {
        return number_format((float)$value) . '&nbsp;<span class="units">µs</span>';
    }

    /**
     * @param $value
     * @return string
     */
    public function formatDiff($value)
    {
        $class = $value > 0 ? 'diff-up' : 'diff-down';
        if ($value == 0) {
            $class = 'diff-same';
        }
        return sprintf(
            '<span class="%s">%s</span>',
            $class,
            number_format((float)$value)
        );
    }

    /**
     * @param $value
     * @param $total
     * @return string
     */
    public function makePercent($value, $total)
    {
        $value = (false === empty($total)) ? $value / $total : 0;
        return $this->formatPercent($value);
    }

    /**
     * @param $value
     * @return string
     */
    public function formatPercent($value)
    {
        return number_format((float)$value * 100, 0) . ' <span class="units">%</span>';
    }
}
