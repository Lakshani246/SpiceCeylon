<?php
// config/settings.php
// Centralized settings management for the entire website

class Settings {
    private static $cache = [];
    private static $conn = null;
    
    // Initialize database connection
    private static function init() {
        if (self::$conn === null) {
            include_once __DIR__ . '/db.php';
            global $conn;
            self::$conn = $conn;
        }
    }
    
    // Get a setting value
    public static function get($key, $default = '') {
        self::init();
        
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Load from database
        $stmt = self::$conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            self::$cache[$key] = $row['setting_value'];
            return $row['setting_value'];
        }
        
        return $default;
    }
    
    // Get all settings at once (for performance)
    public static function getAll() {
        self::init();
        
        if (!empty(self::$cache)) {
            return self::$cache;
        }
        
        $result = self::$conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $result->fetch_assoc()) {
            self::$cache[$row['setting_key']] = $row['setting_value'];
        }
        
        return self::$cache;
    }
    
    // Get all active shipping zones
    public static function getShippingZones() {
        self::init();
        
        $result = self::$conn->query("SELECT * FROM shipping_zones WHERE is_active = 1 ORDER BY shipping_fee");
        $zones = [];
        while ($row = $result->fetch_assoc()) {
            $zones[] = $row;
        }
        return $zones;
    }
    
    // Get all enabled payment methods
    public static function getPaymentMethods() {
        self::init();
        
        $result = self::$conn->query("SELECT * FROM payment_methods WHERE is_enabled = 1 ORDER BY sort_order");
        $methods = [];
        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }
        return $methods;
    }
    
    // Calculate shipping fee based on country and subtotal
    public static function calculateShipping($country, $subtotal) {
        $free_shipping_min = (float)self::get('free_shipping_min', 5000);
        
        // Free shipping if applicable
        if ($free_shipping_min > 0 && $subtotal >= $free_shipping_min) {
            return 0;
        }
        
        // Check if it's Sri Lanka (local shipping)
        if (stripos($country, 'sri lanka') !== false || stripos($country, 'lanka') !== false) {
            return (float)self::get('local_shipping_fee', 500);
        }
        
        // International shipping
        return (float)self::get('international_shipping_fee', 2500);
    }
    
    // Get currency symbol
    public static function getCurrencySymbol() {
        $currency = self::get('currency', 'LKR');
        $symbols = [
            'LKR' => 'Rs.',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹'
        ];
        return $symbols[$currency] ?? 'Rs.';
    }
    
    // Check if site is in maintenance mode
    public static function isMaintenanceMode() {
        return self::get('maintenance_mode', '0') === '1';
    }
}
?>