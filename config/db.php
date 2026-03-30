<?php
    $conn=new mysqli("localhost","root","","mars_haven");
    if($conn->connect_error){
        die("Connection failed: " . $conn->connect_error);
    }
?>