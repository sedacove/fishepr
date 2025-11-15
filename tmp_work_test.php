<?php
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["user_login"] = "debug";
$_SESSION["user_type"] = "admin";
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["REQUEST_URI"] = "/fisherp/api/work.php";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SCRIPT_NAME"] = "/fisherp/api/work.php";
$_SERVER["QUERY_STRING"] = "";
require 'api/work.php';
?>
