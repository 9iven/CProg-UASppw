<?php
require 'config/db.php';
$query = "ALTER TABLE users ADD COLUMN display_name VARCHAR(100) NULL AFTER profile_picture";
if (mysqli_query($conn, $query)) {
    echo "Success";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
