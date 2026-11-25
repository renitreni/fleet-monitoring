<?php

return [
    'timezone' => env('BLOG_TIMEZONE', 'Asia/Manila'),
    'image_disk' => env('BLOG_IMAGE_DISK', 'public'),
    'ai' => [
        'api_key' => env('NVIDIA_API_KEY'),
        'base_url' => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
        'model' => env('NVIDIA_MODEL', 'nvidia/nemotron-3.5-super-vl-120b-a12b'),
        'timeout' => env('NVIDIA_TIMEOUT', 180),
        'attempts' => env('NVIDIA_ATTEMPTS', 3),
        'retry_delay' => env('NVIDIA_RETRY_DELAY', 5),
        'posts_per_day' => env('BLOG_AI_POSTS_PER_DAY', 10),
        'generation_time' => env('BLOG_AI_GENERATION_TIME', '06:00'),
        'first_publish_time' => env('BLOG_AI_FIRST_PUBLISH_TIME', '08:00'),
        'notification_email' => env('BLOG_AI_NOTIFICATION_EMAIL'),
        'byline' => env('BLOG_AI_BYLINE', 'Motologic Editorial Team'),
    ],
];
