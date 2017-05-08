<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

$cakeDescription = 'CakePHP: the rapid development php framework';
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>

    <?= $this->Html->meta('icon') ?>
    <?php
    echo $this->Html->css('bootstrap.min.css');
    echo $this->Html->script('jquery-2.1.4.min.js');
    echo $this->Html->script('bootstrap.min.js');
    ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>


<div class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse"> <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>

            </button> <a class="navbar-brand" href="#" contenteditable="false" style="">SCORM Player</a>

        </div>
        <div class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="/scorm/" class="" contenteditable="false">Course List</a>
                </li>
                <li><a href="/scorm/importscorm" class="">Import</a>
                </li>

            </ul>
            <div class="nav navbar-nav navbar-right">
                <ul class="nav navbar-nav">
                    <li ><a href="/users/logout" class="" contenteditable="false">Logout</a>
                    </li>


                </ul>
            </div>
            <div class="nav navbar-nav navbar-right">
                <div  class="navbar-brand"><?php
                    $sess = $this->request->session();
                    $userName = $sess->read("Auth.User.username");
                    if ($userName){
                        echo "Hi ".$userName;
                    }
                    ?>
                </div>
            </div>

        </div>
        <!--/.nav-collapse -->
    </div>
</div>
<div class="container clearfix">
    <div class="text-center" style="margin-top: 100px">
        <?= $this->fetch('content') ?>
    </div>
</div>
</body>
</html>
