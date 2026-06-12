<?php
/**
 * Shared Utility Functions - CProg Tracker
 * 
 * This file contains utility helper functions used across various scripts 
 * in this application to perform tasks like making HTTP requests.
 * 
 */

if (!function_exists('http_get_request')) {
    /**
     * Perform an HTTP GET request using cURL.
     * Centralized to remove redundant curl_init blocks across files.
     * 
     * @param string $url The target URL to fetch data from
     * @param int $timeout Maximum time in seconds to wait for connection
     * @return array Array containing HTTP status code and response body
     */
    function http_get_request($url, $timeout = 10) {
        // Initialize a cURL session
        $ch = curl_init($url);
        
        // Return the response as a string instead of outputting it directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Turn off SSL peer verification for local development compat (e.g. XAMPP on Windows)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Allow redirect tracking automatically if the website forwards pages
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Define connection timeout to prevent hanging the local server
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        
        // Set a standard browser User Agent so websites don't reject our crawler
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        // Execute the request and grab results
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Close cURL session resources
        curl_close($ch);
        
        return [
            'code' => $http_code,
            'body' => $response
        ];
    }
}
?>
