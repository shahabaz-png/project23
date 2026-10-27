<?php
require_once __DIR__ . '/functions.php';
redirect(current_user() ? 'dashboard.php' : 'login.php');
