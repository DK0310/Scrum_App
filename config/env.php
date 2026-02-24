<?php
/**
 * Environment Configuration Loader
 * Đọc biến từ file .env
 */

class EnvLoader {
    private static $envPath = __DIR__ . '/../.env';
    private static $vars = [];
    private static $loaded = false;

    /**
     * Load .env file
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }

        if (!file_exists(self::$envPath)) {
            throw new Exception(".env file not found at " . self::$envPath);
        }

        $lines = file(self::$envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (
                    (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)
                ) {
                    $value = substr($value, 1, -1);
                }

                self::$vars[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return self::$vars[$key] ?? getenv($key) ?? $default;
    }

    /**
     * Check if key exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$vars[$key]) || getenv($key) !== false;
    }
}

// Auto-load on require
EnvLoader::load();
