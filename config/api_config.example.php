<?php
// API Keys Configuration
// Please replace the placeholders with your actual API keys.
// Rename this file to api_config.php

// Gemini API Keys (Required for AI Chat and Embeddings)
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY');

// Multiple Keys for Rotation
define('GEMINI_API_KEYS', [
    'YOUR_GEMINI_API_KEY_1',
    'YOUR_GEMINI_API_KEY_2'
]);

// OpenAI API Key (Optional)
define('OPENAI_API_KEY', '');

// Google Custom Search API Key (Optional)
define('GOOGLE_CSE_KEY', '');

// Google Custom Search Engine ID (Optional)
define('GOOGLE_CSE_CX', '');

// Cloudflare Workers AI Configuration
define('CF_ACCOUNT_ID', 'YOUR_CF_ACCOUNT_ID');
define('CF_API_KEY', 'YOUR_CF_API_KEY');
define('CF_MODEL', '@cf/meta/llama-3.1-8b-instruct');
?>