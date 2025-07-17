<?php
require_once '../classes/Database.php';
require_once '../classes/AdminAuth.php';

$db = new Database();
$auth = new AdminAuth($db);
$auth->logout();

header('Location: login.php');
exit();
?>
