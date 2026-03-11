<?php
/**
 * Supabase Storage Helper — DriveNow
 * Handles file upload/download/delete via Supabase Storage REST API
 * 
 * Bucket: "DriveNow"
 * Folder structure:
 *   vehicles/{vehicle_id}/{uuid}.ext
 *   avatars/{user_id}.ext
 *   hero-slides/{uuid}.ext
 *   community/{post_id}.ext
 */

require_once __DIR__ . '/../config/env.php';

class SupabaseStorage {
    private string $projectUrl;
    private string $serviceKey;
    private string $bucket;

    public function __construct() {
        $this->projectUrl = EnvLoader::get('SUPABASE_URL');
        $this->serviceKey = EnvLoader::get('SUPABASE_SERVICE_KEY');
        $this->bucket     = 'DriveNow';

        if (!$this->projectUrl || !$this->serviceKey) {
            throw new Exception('SUPABASE_URL and SUPABASE_SERVICE_KEY must be set in .env');
        }
    }

    /**
     * Upload a file to Supabase Storage
     * @param string $path  Storage path inside bucket (e.g. "vehicles/uuid/img.jpg")
     * @param string $fileContent  Raw binary content
     * @param string $mimeType  MIME type (e.g. "image/jpeg")
     * @param bool $upsert  Whether to overwrite existing file
     * @return array  ['success' => bool, 'path' => string, 'public_url' => string]
     */
    public function upload(string $path, string $fileContent, string $mimeType, bool $upsert = true): array {
        $url = $this->projectUrl . '/storage/v1/object/' . $this->bucket . '/' . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fileContent,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->serviceKey,
                'Content-Type: ' . $mimeType,
                'x-upsert: ' . ($upsert ? 'true' : 'false'),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL error: ' . $error];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success'    => true,
                'path'       => $path,
                'public_url' => $this->getPublicUrl($path),
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? $data['error'] ?? 'Upload failed (HTTP ' . $httpCode . ')',
        ];
    }

    /**
     * Delete a file from Supabase Storage
     * @param string $path  Storage path inside bucket
     * @return array  ['success' => bool]
     */
    public function delete(string $path): array {
        $url = $this->projectUrl . '/storage/v1/object/' . $this->bucket;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_POSTFIELDS     => json_encode(['prefixes' => [$path]]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->serviceKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL error: ' . $error];
        }

        return ['success' => ($httpCode >= 200 && $httpCode < 300)];
    }

    /**
     * Delete multiple files from Supabase Storage
     * @param array $paths  Array of storage paths
     * @return array  ['success' => bool]
     */
    public function deleteMultiple(array $paths): array {
        $url = $this->projectUrl . '/storage/v1/object/' . $this->bucket;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_POSTFIELDS     => json_encode(['prefixes' => $paths]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->serviceKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['success' => ($httpCode >= 200 && $httpCode < 300)];
    }

    /**
     * Get the public URL for a file in the bucket
     * @param string $path  Storage path inside bucket
     * @return string  Full public URL
     */
    public function getPublicUrl(string $path): string {
        return $this->projectUrl . '/storage/v1/object/public/' . $this->bucket . '/' . $path;
    }

    /**
     * Generate a unique filename preserving extension
     * @param string $originalName  Original file name
     * @return string  UUID-based filename
     */
    public static function uniqueName(string $originalName): string {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $ext = strtolower($ext) ?: 'jpg';
        return bin2hex(random_bytes(16)) . '.' . $ext;
    }
}
