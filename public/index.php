<?php

require __DIR__ . '/../app/bootstrap.php';

$user = current_user();
redirect($user ? (can_access_process_area($user) ? 'dashboard.php' : 'audits.php') : 'login.php');
