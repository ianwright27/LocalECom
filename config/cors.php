<?php
/**
 * CORS Configuration - MINIMAL VERSION
 * All CORS headers are now handled in index.php
 * This file only starts the session
 */

// Just start session if needed - NO CORS HEADERS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// That's it - index.php will handle ALL CORS