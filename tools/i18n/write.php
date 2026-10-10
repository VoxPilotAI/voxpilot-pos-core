<?php
// Writes lang/<locale>/<namespace path>/<group>.php from <locale>.json (TastyIgniter override paths).
$locale = $argv[1];
$data = json_decode(file_get_contents("/tmp/i18n/$locale.json"), true);
$root = '/var/www/html/lang';
foreach ($data as $id => $flat) {
    [$ns, $group] = explode('|', $id);
    $tree = [];
    foreach ($flat as $key => $text) {
        $ref = &$tree;
        foreach (explode('.', $key) as $part) { $ref = &$ref[$part]; }
        $ref = $text;
        unset($ref);
    }
    $dir = $ns === '*' ? "$root/$locale" : "$root/$locale/".str_replace('.', '/', $ns);
    @mkdir($dir, 0775, true);
    $header = "<?php\n\n// VoxPilot POS translation of TastyIgniter's English strings ($ns::$group). Regenerate with\n// the i18n script rather than editing by hand when TastyIgniter adds strings.\n\nreturn ";
    file_put_contents("$dir/$group.php", $header.var_export($tree, true).";\n");
}
echo "$locale ok\n";
