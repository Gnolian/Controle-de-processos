<?php

require __DIR__ . '/../app/bootstrap.php';

$user = current_user();
redirect($user && can_access_audits($user) ? 'audits.php' : 'login.php');
