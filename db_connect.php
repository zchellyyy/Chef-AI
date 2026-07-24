<?php
$host = "localhost";
$username = "u914306750_keen";
$password = "CapstonePassed_2025";
$dbname = "u914306750_chefai";

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>


