<?php
return [
    'default_provider' => 'cohere',
    'default_model'    => 'command-a-03-2025',
    'default_prompt'   => 'You are a helpful assistant.',

    // ENV FALLBACK (OPTIONAL)
    'openai_key' => getenv('OPENAI_API_KEY') ?: null,
    'groq_key'   => getenv('GROQ_API_KEY') ?: null,
    'gemini_key' => getenv('GEMINI_API_KEY') ?: null,
    'cohere_key' => getenv('COHERE_API_KEY') ?: null,

    'openai_endpoint' => 'https://api.openai.com/v1/chat/completions',
    'groq_endpoint'   => 'https://api.groq.com/openai/v1/chat/completions',
    'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
    'cohere_endpoint' => 'https://api.cohere.com/v1/chat',
];