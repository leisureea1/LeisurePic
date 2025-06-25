<?php
return array (
  'site_name' => 'Leisurepic',
  'max_upload_size' => 5242880,
  'allowed_types' => 
  array (
    0 => 'jpg',
    1 => 'jpeg',
    2 => 'png',
    3 => 'gif',
    4 => 'bmp',
    5 => 'webp',
    6 => 'mp4',
    7 => 'pdf',
  ),
  'min_width' => '100',
  'min_height' => '100',
  'watermark_text' => 'Leisure',
  'watermark_image' => 'public/image/water.png',
  'enable_watermark' => false,
  'enable_compress' => true,
  'enable_format_convert' => true,
  'default_format' => 'webp',
  'ip_blacklist' => 
  array (
  ),
  'ip_whitelist' => 
  array (
  ),
  'upload_limit_per_day' => '100',
  'max_upload_concurrency' => '3',
  'site_domain' => '',
  'upload_dir' => 'public/uploads',
  'bark_key' => '',
  'bark_server' => 'https://api.day.app',
  'enable_antiporn' => true,
);
