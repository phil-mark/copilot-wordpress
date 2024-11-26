<?php
/*
Plugin Name: Custom API
Description: A plugin to add custom API endpoints.
Version: 1.0
Author: Your Name
*/

add_action('rest_api_init', function () {
    register_rest_route('custom-api/v1', '/data/', array(
        'methods' => 'GET',
        'callback' => 'custom_api_get_data',
    ));
	register_rest_route('custom-api/ctlt', '/getsignedurl/', array(
        'methods' => 'GET',
        'callback' => 'sign_url',
    ));
});

function custom_api_get_data(WP_REST_Request $request) {
    $data = array(
        'message' => 'Hello, this is your custom API!',
        'success' => true,
    );
    
    return new WP_REST_Response($data, 200);
}

// Main Functionality
function sign_url(WP_REST_Request $request) {
    $repo_id = REPO_ID;
    $sharing_key = SHARING_KEY;
    $key_id = KEY_ID;
    $secret = SECRET;
    $exp = EXP;
    $encryption_key = ENCRYPTION_KEY;
    $nonce = generateNonce();
    $id = "copilot_" . $repo_id . "_" . $sharing_key;

    $data = [
        'repo_id' => $repo_id,
        'sharing_key' => $sharing_key,
        'key_id' => $key_id,
        'secret' => $secret,
        'exp' => $exp,
        'id' => $id,
        'nonce' => $nonce,
        'encryption_key' => $encryption_key
    ];

    $signed_url = generateSignedUrl($data);
	
    header('Content-Type: application/json');
    echo json_encode(['signed_url' => $signed_url]);
}

function generateSignedUrl($obj) {
    // base URL
    $base_url = COPILOT_BASE_URL;

    // Encrypt
    $encryption_key = $obj['encryption_key'];
    $encrypted_repo_id = encrypt($obj['repo_id'], $encryption_key);
    $encrypted_sharing_key = encrypt($obj['sharing_key'], $encryption_key);
    $encrypted_id = encrypt($obj['id'], $encryption_key);

    // Encode
    $encoded_repo_id = base64UrlEncode($encrypted_repo_id);
    $encoded_sharing_key = base64UrlEncode($encrypted_sharing_key);
    $encoded_id = base64UrlEncode($encrypted_id);

    // Sign data
    $data = sprintf('%s,%s,%s,%s,%s,%s', $obj['repo_id'], $obj['sharing_key'], $obj['key_id'], $obj['exp'], $obj['nonce'], $obj['id']);
    $sig = signData($data, $obj['secret']);

    // Generate and return signed URL
    return sprintf('%s?repo_id=%s&sharing_key=%s&key_id=%s&exp=%s&nonce=%s&id=%s&sig=%s',
        $base_url, $encoded_repo_id, $encoded_sharing_key, $obj['key_id'], $obj['exp'], $obj['nonce'], $encoded_id, $sig);
}

// Ensure these functions are available in your PHP code
function encrypt($plainText, $secretKey) {
    $base64Key = base64_decode($secretKey);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plainText, 'aes-256-cbc', $base64Key, OPENSSL_RAW_DATA, $iv);
    $encryptedWithIv = $iv . $encrypted;
    return base64_encode($encryptedWithIv);
}

function signData($data, $key) {
    $keyByte = base64_decode($key);
    $hash = hash_hmac('sha256', $data, $keyByte, true);
    return base64UrlEncode($hash);
}

function base64UrlEncode($input) {
    $base64 = base64_encode($input);
    $base64Url = strtr($base64, '+/', '-_');
    return rtrim($base64Url, '=');
}

function generateNonce() {
    return bin2hex(random_bytes(16));
}