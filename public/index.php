<?php
require_once dirname(__DIR__) . '/src/app.php';
redirect(user_id() ? '/account.php' : '/login.php');
