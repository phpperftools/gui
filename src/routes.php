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

/**
 * Routes for PhpPerfTools
 */

use PhpPerfTools\Twig\Extension;

/** @var \Slim\Slim $app */
/** @var \PhpPerfTools\ServiceContainer $di */

$app->error(function (Exception $e) use ($di, $app) {
    $view = $di['view'];
    $view->parserOptions['cache'] = false;
    $view->parserExtensions = array(
        new Extension($app)
    );

    // Remove the controller so we don't render it.
    unset($app->controller);

    $app->view($view);
    $app->render('error/view.twig', array(
        'message' => $e->getMessage(),
        'stack_trace' => $e->getTraceAsString(),
        'save_handlers' => \PhpPerfTools\Config::read('handlers'),
        'current_handler' => \PhpPerfTools\Config::getCurrentHandlerConfig(),
        'show_handler_select' => true,
        'exception'  => $e,
    ));
});

// Profile Runs routes
$app->get('/', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->index();
})->name('home');

$app->get('/run/view', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->view();
})->name('run.view');

$app->get('/run/delete', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->deleteForm();
})->name('run.delete.form');

$app->post('/run/delete', function () use ($di, $app) {
    $di['runController']->deleteSubmit();
})->name('run.delete.submit');

$app->get('/run/delete_all', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->deleteAllForm();
})->name('run.deleteAll.form');

$app->post('/run/delete_all', function () use ($di, $app) {
    $di['runController']->deleteAllSubmit();
})->name('run.deleteAll.submit');

$app->get('/url/view', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->url();
})->name('url.view');

$app->get('/run/compare', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->compare();
})->name('run.compare');

$app->get('/run/symbol', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->symbol();
})->name('run.symbol');

$app->get('/run/symbol/short', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->symbolShort();
})->name('run.symbol-short');

$app->get('/run/callgraph', function () use ($di, $app) {
    $app->controller = $di['runController'];
    $app->controller->callgraph();
})->name('run.callgraph');

$app->get('/run/callgraph/data', function () use ($di, $app) {
    $di['runController']->callgraphData(false);
})->name('run.callgraph.data');

$app->get('/run/callgraph/dot', function () use ($di, $app) {
    $di['runController']->callgraphDataDot(true);
})->name('run.callgraph.dot');

// Import route
$app->post('/import/save', function () use ($di, $app) {
    $app->controller = $di['importController'];
    $app->controller->save();
})->name('import.save');

$app->get('/import/form', function () use ($di, $app) {
    $app->controller = $di['importController'];
    $app->controller->index();
})->name('import');

$app->get('/import', function () use ($di, $app) {
    $app->controller = $di['importController'];
    $app->controller->import();
})->name('import.data');


// Watch function routes.
$app->get('/watch', function () use ($di, $app) {
    $app->controller = $di['watchController'];
    $app->controller->get();
})->name('watch.list');

$app->post('/watch', function () use ($di) {
    $di['watchController']->post();
})->name('watch.save');

$app->get('/watch/add', function () use ($di, $app) {
    $app->controller = $di['watchController'];
    $di['watchController']->add();
})->name('watch.add');

$app->get('/watch/remove', function () use ($di, $app) {
    $app->controller = $di['watchController'];
    $di['watchController']->remove();
})->name('watch.remove');

// Waterfall routes
$app->get('/waterfall', function () use ($di, $app) {
    $app->controller = $di['waterfallController'];
    $app->controller->index();
})->name('waterfall.list');

$app->get('/waterfall/data', function () use ($di) {
    $di['waterfallController']->query();
})->name('waterfall.data');

$app->get('/login', function () use ($di) {
    $di['userController']->loginForm();
})->name('user.login.form');

$app->post('/login', function () use ($di) {
    $di['userController']->login();
})->name('user.login.post');

$app->get('/register', function () use ($di) {
    $di['userController']->registerForm();
})->name('user.register.form');

$app->post('/register', function () use ($di) {
    $di['userController']->register();
})->name('user.register.post');

$app->get('/accounts', function () use ($di) {
    $di['userController']->accounts();
})->name('user.accounts');
