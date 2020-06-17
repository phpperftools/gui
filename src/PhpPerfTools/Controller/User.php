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
use PhpPerfTools\Storage\UserStorageInterface;
use Slim\Slim;

class User extends Controller
{

    /**
     * @var \PhpPerfTools\Storage\UserStorageInterface
     */
    private $users;

    /**
     * @param Slim $app
     * @param \PhpPerfTools\Storage\UserStorageInterface $users
     */
    public function __construct(Slim $app, UserStorageInterface $users)
    {
        parent::__construct($app);
        $this->users = $users;
    }

    /**
     *
     */
    public function accounts()
    {
        \error_reporting(E_ALL);
        \ini_set('display_errors', 1);

        $users = $this->users->getAllUsers();

        $this->_template = 'users/list.twig';
        $this->set(array(
            'users' => $users,
            'show_handler_select' => true,
        ));
    }
}
