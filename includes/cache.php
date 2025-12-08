<?php
/**
 * Simple File-Based Caching System
 * Provides fast caching for Firestore queries and expensive operations
 */

/**
 * Get item from cache
 * 
 * @param string $key Cache key
 * @param int|null $maxAge Maximum age in seconds (null to use stored TTL)
 * @return mixed|null Cached value or null if not found/expired
 */
function cache_get(string $key, ?int $maxAge = null) {
    if (!CACHE_ENABLED) {
        return null;
    }
    
    $cacheFile = cache_get_file_path($key);
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $data = @file_get_contents($cacheFile);
    if ($data === false) {
        return null;
    }
    
    $cached = @unserialize($data);
    if ($cached === false || !is_array($cached)) {
        return null;
    }
    
    // Check expiration
    $age = time() - ($cached['time'] ?? 0);
    $ttl = $maxAge ?? $cached['ttl'] ?? CACHE_DEFAULT_TTL;
    
    if ($age > $ttl) {
        @unlink($cacheFile);
        return null;
    }
    
    return $cached['data'] ?? null;
}

/**
 * Set item in cache
 * 
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds
 * @return bool Success status
 */
function cache_set(string $key, $value, int $ttl = CACHE_DEFAULT_TTL): bool {
    if (!CACHE_ENABLED) {
        return false;
    }
    
    $cacheFile = cache_get_file_path($key);
    $cacheDir = dirname($cacheFile);
    
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $data = serialize([
        'time' => time(),
        'ttl' => $ttl,
        'data' => $value
    ]);
    
    return @file_put_contents($cacheFile, $data, LOCK_EX) !== false;
}

/**
 * Delete item from cache
 * 
 * @param string $key Cache key
 * @return bool Success status
 */
function cache_delete(string $key): bool {
    $cacheFile = cache_get_file_path($key);
    
    if (file_exists($cacheFile)) {
        return @unlink($cacheFile);
    }
    
    return true;
}

/**
 * Clear all cache or cache matching pattern
 * 
 * @param string|null $pattern Optional pattern to match (e.g., 'reports_*')
 * @return int Number of files deleted
 */
function cache_clear(?string $pattern = null): int {
    if (!is_dir(CACHE_DIR)) {
        return 0;
    }
    
    $count = 0;
    $files = glob(CACHE_DIR . '/*.cache');
    
    if ($pattern) {
        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        $files = array_filter($files, function($file) use ($pattern) {
            return preg_match('/' . $pattern . '/', basename($file));
        });
    }
    
    foreach ($files as $file) {
        if (@unlink($file)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Remember callback result in cache
 * 
 * @param string $key Cache key
 * @param callable $callback Function to call if cache miss
 * @param int $ttl Time to live in seconds
 * @return mixed Cached or fresh value
 */
function cache_remember(string $key, callable $callback, int $ttl = CACHE_DEFAULT_TTL) {
    $value = cache_get($key);
    
    if ($value !== null) {
        return $value;
    }
    
    $value = $callback();
    cache_set($key, $value, $ttl);
    
    return $value;
}

/**
 * Get cache file path for key
 * 
 * @param string $key Cache key
 * @return string File path
 */
function cache_get_file_path(string $key): string {
    $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    $hash = md5($key);
    return CACHE_DIR . '/' . $safeKey . '_' . $hash . '.cache';
}

/**
 * Clean expired cache files
 * 
 * @return int Number of files deleted
 */
function cache_cleanup_expired(): int {
    if (!is_dir(CACHE_DIR)) {
        return 0;
    }
    
    $count = 0;
    $files = glob(CACHE_DIR . '/*.cache');
    
    foreach ($files as $file) {
        $data = @file_get_contents($file);
        if ($data === false) {
            continue;
        }
        
        $cached = @unserialize($data);
        if ($cached === false || !is_array($cached)) {
            @unlink($file);
            $count++;
            continue;
        }
        
        $age = time() - ($cached['time'] ?? 0);
        $ttl = $cached['ttl'] ?? CACHE_DEFAULT_TTL;
        
        if ($age > $ttl) {
            @unlink($file);
            $count++;
        }
    }
    
    return $count;
}

/**
 * Get cache statistics
 * 
 * @return array Cache stats
 */
function cache_stats(): array {
    if (!is_dir(CACHE_DIR)) {
        return ['total' => 0, 'size' => 0];
    }
    
    $files = glob(CACHE_DIR . '/*.cache');
    $totalSize = 0;
    
    foreach ($files as $file) {
        $totalSize += filesize($file);
    }
    
    return [
        'total' => count($files),
        'size' => $totalSize,
        'size_mb' => round($totalSize / 1024 / 1024, 2)
    ];
}
