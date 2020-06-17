PhpPerfTools/GUI
=====

A graphical interface for XHProf data.

This tool requires that [XHProf](http://pecl.php.net/package/xhprof) or its one
of its forks [Uprofiler](https://github.com/FriendsOfPHP/uprofiler),
[Tideways](https://github.com/tideways/php-profiler-extension) are installed.
XHProf is a PHP Extension that records and provides profiling data.
PhpPerfTools/GUI (this tool) takes that information and provides
a convenient GUI for working with it.

System Requirements
===================

PhpPerfTools/GUI has the following requirements:

 * PHP version 5.3 or later.
 * Mongo based installation:
    *  [MongoDB Extension](http://pecl.php.net/package/mongodb) MongoDB PHP driver.
       PhpPerfTools/GUI requires verison 1.3.0 or later.
    * [MongoDB](http://www.mongodb.org/) MongoDB Itself. PhpPerfTools/GUI requires version 2.2.0 or later.
 * PDO based installation:
    * [PDO](https://www.php.net/manual/en/book.pdo.php) extension.
    * One of the sql servers: MySQL/MariaDB or PostgreSQL. It will also work with SQLite.
 * For upload config:
    * [CURL](https://www.php.net/manual/en/book.curl.php) extension.
    * Proper network/firewall rules: GUI instance and Collector instance must see each other.
 * One of [XHProf](http://pecl.php.net/package/xhprof),
   [Uprofiler](https://github.com/FriendsOfPHP/uprofiler) or
   [Tideways](https://github.com/tideways/php-profiler-extension) to actually profile the data.
 * [dom](http://php.net/manual/en/book.dom.php) If you are running the tests
   you'll need the DOM extension (which is a dependency of PHPUnit).


Installation from source
========================

1. Clone or download `PhpPerfTools/GUI` from GitHub.

2. Point your webserver to the `public` directory.

3. Set the permissions on the `cache` directory to allow the
   webserver to create files. If you're lazy, `0777` will work.

   The following command changes the permissions for the `cache` directory:

   ```bash
   chmod -R 0777 cache
   ```

4. Set up your webserver. The Configuration section below describes how
   to setup the rewrite rules for both nginx and apache.

MongoDB storage
========================
1. Start a MongoDB instance.

2. If your MongoDB setup uses authentication, or isn't running on the
   default port and localhost, update PhpPerfTools/GUI's `config/config.php` so that PhpPerfTools/GUI
   can connect to your `mongod` instance.

3. (**Optional**, but recommended) Add indexes to MongoDB to improve performance.

   PhpPerfTools/GUI stores profiling information in a `results` collection in the
   `xhprof` database in MongoDB. Adding indexes improves performance,
   letting you navigate pages more quickly.

   To add an index, open a `mongo` shell from your command prompt.
   Then, use MongoDB's `db.collection.ensureIndex()` method to add
   the indexes, as in the following:

   ```
   $ mongo
   > use xhprof
   > db.results.ensureIndex( { 'meta.SERVER.REQUEST_TIME' : -1 } )
   > db.results.ensureIndex( { 'profile.main().wt' : -1 } )
   > db.results.ensureIndex( { 'profile.main().mu' : -1 } )
   > db.results.ensureIndex( { 'profile.main().cpu' : -1 } )
   > db.results.ensureIndex( { 'meta.url' : 1 } )
   > db.results.ensureIndex( { 'meta.simple_url' : 1 } )
   ```

Limiting MongoDB Disk Usage
---------------------------

Disk usage can grow quickly, especially when profiling applications with large
code bases or that use larger frameworks.

To keep the growth
in check, configure MongoDB to automatically delete profiling documents once they
have reached a certain age by creating a [TTL index](http://docs.mongodb.org/manual/core/index-ttl/).

Decide on a maximum profile document age in seconds: you
may wish to choose a lower value in development (where you profile everything),
than production (where you profile only a selection of documents). The
following command instructs Mongo to delete documents over 5 days (432000
seconds) old.

```
$ mongo
> use xhprof
> db.results.ensureIndex( { "meta.request_ts" : 1 }, { expireAfterSeconds : 432000 } )
```


PDO Storage
========================

Schema
------

Schema files are in root directory of GUI. Import schema into database and configure storage handler.

Configuration
-------------

```
array(
    'id' => 'sqlite-test',
    'name' => 'Test sqlite instance',
    'type' => 'pdo',

    'dsn' => 'sqlite:../test.sqlite',
    'user' => 'user',
    'password' => 'password'
),

```


Configuration
=============

Configure Webserver Re-Write Rules
----------------------------------

PhpPerfTools/GUI prefers to have URL rewriting enabled, but will work without it.
For Apache, you can do the following to enable URL rewriting:

1. Make sure that an .htaccess override is allowed and that AllowOverride
   has the directive FileInfo set for the correct DocumentRoot.

    Example configuration for Apache 2.4:
    ```apache
    <Directory /var/www/gui/>
        Options Indexes FollowSymLinks
        AllowOverride FileInfo
        Require all granted
    </Directory>
    ```
2. Make sure you are loading up mod_rewrite correctly.
   You should see something like:

    ```apache
    LoadModule rewrite_module libexec/apache2/mod_rewrite.so
    ```

3. PhpPerfTools/GUI comes with a `.htaccess` file to enable the remaining rewrite rules.

For nginx and fast-cgi, you can the following snippet as a start:

```nginx
server {
    listen   80;
    server_name example.com;

    # root directive should be global
    root   /var/www/example.com/public/gui/public/;
    index  index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include /etc/nginx/fastcgi_params;
        fastcgi_pass    127.0.0.1:9000;
        fastcgi_index   index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```


Configure PhpPerfTools/GUI Profiling Rate
-------------------------------

After installing PhpPerfTools/GUI, you may want to do change how frequently you
profile the host application. The `profiler_enable` configuration option
allows you to provide a callback function that specifies the requests that
are profiled. By default, PhpPerfTools/GUI profiles 1 in 100 requests.

The following example configures PhpPerfTools/GUI to only profile requests
from a specific URL path:

The following example configures PhpPerfTools/GUI to profile 1 in 100 requests,
excluding requests with the `/blog` URL path:

```php
// In config/config.php
return array(
    // Other config
    'profiler_enable' => function() {
        $url = $_SERVER['REQUEST_URI'];
        if (strpos($url, '/blog') === 0) {
            return false;
        }
        return rand(1, 100) === 42;
    }
);
```

In contrast, the following example configured PhpPerfTools/GUI to profile *every*
request:

```php
// In config/config.php
return array(
    // Other config
    'profiler_enable' => function() {
        return true;
    }
);
```


Configure 'Simple' URLs Creation
--------------------------------

PhpPerfTools/GUI generates 'simple' URLs for each profile collected. These URLs are
used to generate the aggregate data used on the URL view. Since
different applications have different requirements for how URLs map to
logical blocks of code, the `profile.simple_url` configuration option
allows you to provide specify the logic used to generate the simple URL.
By default, all numeric values in the query string are removed.

```php
// In config/config.php
return array(
    // Other config
    'profile.simple_url' => function($url) {
        // Your code goes here.
    }
);
```

The URL argument is the `REQUEST_URI` or `argv` value.

Configure ignored functions
---------------------------

You can use the `profiler_options` configuration value to set additional options
for the profiler extension. This is useful when you want to exclude specific
functions from your profiler data:

```php
// In config/config.php
return array(
    //Other config
    'profiler_options' => [
        'ignored_functions' => ['call_user_func', 'call_user_func_array']
    ]
);
```

Profiling a Web Request or CLI script
=====================================

Using [collector](https://github.com/phpperftools/collector) you can
collect data from your web applications and CLI scripts. This data is then
pushed into PhpPerfTools/GUI's database where it can be viewed with this application.

Waterfall Display
-----------------

The goal of PhpPerfTools/GUI's waterfall display is to recognize that concurrent requests can
affect each other. Concurrent database requests, CPU-intensive
activities and even locks on session files can become relevant. With an
Ajax-heavy application, understanding the page build is far more complex than
a single load: hopefully the waterfall can help. Remember, if you're only
profiling a sample of requests, the waterfall fills you with impolite lies.

Using Tideways Extension
========================

The XHProf PHP extension is not compatible with PHP7.0+. Instead you'll need to
use the [tideways_xhprof extension](https://github.com/tideways/php-profiler-extension).

Once installed, you can use the following configuration data:

```ini
[tideways_xhprof]
extension="/path/to/tideways/tideways_xhprof.so"
```

License
=======

Original code Copyright 2013 Mark Story & Paul Reinheimer
Changes Copyright Grzegorz Drozd

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

