<?php
require_once '../config/session.php';

// Clear user session
clearUserSession();

// Redirect to home page
header('Location: ../index.php?logout=success');
exit();
?>
