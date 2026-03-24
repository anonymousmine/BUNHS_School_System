<?php
/**
 * cache_helper.php - Dashboard Caching System
 * Implements intelligent caching for dashboard performance
 */

class DashboardCache {
    private static $cacheTime = 300; // 5 minutes cache time
    
    /**
     * Get cached data or compute and cache it
     */
    public static function remember($key, $callback, $ttl = null) {
        $ttl = $ttl ?? self::$cacheTime;
        
        // Try to get from cache
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Compute and cache the data
        $data = $callback();
        self::put($key, $data, $ttl);
        
        return $data;
    }
    
    /**
     * Store data in cache
     */
    public static function put($key, $data, $ttl = null) {
        $ttl = $ttl ?? self::$cacheTime;
        $expiry = time() + $ttl;
        
        $cacheData = [
            'data' => $data,
            'expiry' => $expiry,
            'timestamp' => time()
        ];
        
        // Try APCu first (fastest)
        if (function_exists('apcu_store')) {
            return apcu_store("dashboard_{$key}", $cacheData, $ttl);
        }
        
        // Fallback to file cache
        return self::putFile($key, $cacheData);
    }
    
    /**
     * Get data from cache
     */
    public static function get($key) {
        // Try APCu first
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch("dashboard_{$key}");
            if ($cached && $cached['expiry'] > time()) {
                return $cached['data'];
            }
            return null;
        }
        
        // Fallback to file cache
        return self::getFile($key);
    }
    
    /**
     * Check if cache exists and is valid
     */
    public static function has($key) {
        return self::get($key) !== null;
    }
    
    /**
     * Clear specific cache key
     */
    public static function forget($key) {
        if (function_exists('apcu_delete')) {
            return apcu_delete("dashboard_{$key}");
        }
        
        return self::deleteFile($key);
    }
    
    /**
     * Clear all dashboard cache
     */
    public static function clear() {
        if (function_exists('apcu_delete')) {
            $iterator = new APCUIterator('/^dashboard_/');
            foreach ($iterator as $item) {
                apcu_delete($item['key']);
            }
        }
        
        // Clear file cache
        $cacheDir = __DIR__ . '/../cache/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . 'dashboard_cache_*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Store data in file cache
     */
    private static function putFile($key, $data) {
        $cacheDir = __DIR__ . '/../cache/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $filename = $cacheDir . "dashboard_cache_" . md5($key) . ".json";
        $json = json_encode($data);
        
        return file_put_contents($filename, $json, LOCK_EX) !== false;
    }
    
    /**
     * Get data from file cache
     */
    private static function getFile($key) {
        $cacheDir = __DIR__ . '/../cache/';
        $filename = $cacheDir . "dashboard_cache_" . md5($key) . ".json";
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $json = file_get_contents($filename);
        if ($json === false) {
            return null;
        }
        
        $data = json_decode($json, true);
        if ($data === null || $data['expiry'] <= time()) {
            // Cache expired, delete it
            unlink($filename);
            return null;
        }
        
        return $data['data'];
    }
    
    /**
     * Delete file cache
     */
    private static function deleteFile($key) {
        $cacheDir = __DIR__ . '/../cache/';
        $filename = $cacheDir . "dashboard_cache_" . md5($key) . ".json";
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Get cache statistics
     */
    public static function stats() {
        $stats = [
            'method' => function_exists('apcu_fetch') ? 'APCu' : 'File',
            'enabled' => true,
            'cache_dir' => __DIR__ . '/../cache/'
        ];
        
        if (function_exists('apcu_cache_info')) {
            $apcuInfo = apcu_cache_info();
            $stats['apcu'] = [
                'memory_usage' => $apcuInfo['mem_size'],
                'memory_available' => $apcuInfo['mem_avail'],
                'hits' => $apcuInfo['nhits'],
                'misses' => $apcuInfo['nmisses'],
                'hit_rate' => $apcuInfo['nhits'] + $apcuInfo['nmisses'] > 0 
                    ? round(($apcuInfo['nhits'] / ($apcuInfo['nhits'] + $apcuInfo['nmisses'])) * 100, 2) 
                    : 0
            ];
        }
        
        return $stats;
    }
}
?>
