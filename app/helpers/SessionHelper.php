<?php
/**
 * Session Helper
 * Safe session management to prevent "session already started" warnings
 * 
 * Place in: C:\xampp\htdocs\wrightcommerce\app\helpers\SessionHelper.php
 * 
 * Then include at top of index.php BEFORE any controllers are loaded
 */

class SessionHelper {
    /**
     * Safely start session if not already started
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Get session value
     */
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Set session value
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Check if session key exists
     */
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     */
    public static function remove($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Destroy entire session
     */
    public static function destroy() {
        self::start();
        $_SESSION = [];
        session_destroy();
    }
    
    /**
     * Get all session data
     */
    public static function all() {
        self::start();
        return $_SESSION;
    }
}