<?php

require __DIR__ . '/../app/bootstrap.php';

$user = current_user();
redirect($user ? 'dashboard.php' : 'login.php');

