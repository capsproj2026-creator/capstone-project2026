<?php
$host = "localhost";
$username = "root";
$password_db = "";
$dbname = "capstone";

$conn = mysqli_connect($host, $username, $password_db, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>