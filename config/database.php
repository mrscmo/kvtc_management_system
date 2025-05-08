<?php
// Database configuration
$servername = "localhost";
$username = "root";  // Change to your database username
$password = "";      // Change to your database password
$dbname = "vocational_training_center";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");
?>