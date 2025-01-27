<?php
$patterns = [
    'title' => '/<title>(.*?)<\/title>/i',
    'emails' => '/[\'\"]mailto:(.*?)\'\"]/',
    'phones' => '/[\'\"]tel:(.*?)\'\"]/',
    'links' => '/[\'\"]http(.*?)\'\"]/',
    'numbers' => '/^(\d{10,12})$/',
    'meta' => '/<meta.*?>/i',
    'inn' => '/ИНН|инн|Идентификация/i',
    'institute' => '/проектный институт/i'
];
