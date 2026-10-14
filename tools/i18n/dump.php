<?php
// Dumps every English TastyIgniter/Laravel lang group as JSON: {ns|group: {flat.key: text}}.
$root = '/var/www/html';
$sources = ['igniter' => "$root/vendor/tastyigniter/core/resources/lang/en", '*' => "$root/lang/en", 'igniter.orange' => "$root/vendor/tastyigniter/ti-theme-orange/resources/lang/en"];
foreach (glob("$root/vendor/tastyigniter/ti-ext-*/resources/lang/en") as $dir) {
    $ext = basename(dirname(dirname(dirname($dir))));
    $sources['igniter.'.substr($ext, 7)] = $dir;
}
$flat = function ($arr, $prefix = '') use (&$flat) {
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string) $k : "$prefix.$k";
        if (is_array($v)) $out += $flat($v, $key); elseif (is_string($v)) $out[$key] = $v;
    }
    return $out;
};
$all = [];
foreach ($sources as $ns => $dir) {
    foreach (glob("$dir/*.php") as $file) {
        $all[$ns.'|'.basename($file, '.php')] = $flat(require $file);
    }
}
echo json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
