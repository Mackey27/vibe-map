<?php

require_once __DIR__ . '/db.php';

vibemap_logout_user();
header('Location: login.php');
exit;
