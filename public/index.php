<?php

require __DIR__ . '/../app/bootstrap.php';

$user = current_user();
redirect($user ? user_home_path($user) : 'login.php');
