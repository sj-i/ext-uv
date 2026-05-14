--TEST--
uv_fs_sendfile in a loop should not leak (issue 118)
--FILE--
<?php
$loop = uv_default_loop();
$src = tempnam(sys_get_temp_dir(), 'uvsrc_');
file_put_contents($src, str_repeat('x', 256));
$dst = tempnam(sys_get_temp_dir(), 'uvdst_');

// helper to do one open/sendfile/close cycle synchronously
$one = function () use ($loop, $src, $dst) {
    $in = $out = null;
    $d = false;
    uv_fs_open($loop, $src, UV::O_RDONLY, 0, function ($h) use (&$in, &$d) {
        $in = $h; $d = true;
    });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
    $d = false;
    uv_fs_open($loop, $dst, UV::O_WRONLY | UV::O_CREAT, 0644,
        function ($h) use (&$out, &$d) { $out = $h; $d = true; });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
    $d = false;
    uv_fs_sendfile($loop, $out, $in, 0, 256,
        function () use (&$d) { $d = true; });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
    $d = false;
    uv_fs_close($loop, $in, function () use (&$d) { $d = true; });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
    $d = false;
    uv_fs_close($loop, $out, function () use (&$d) { $d = true; });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
};

for ($i = 0; $i < 50; $i++) $one();
$base = memory_get_usage();
for ($i = 0; $i < 500; $i++) $one();
$delta = memory_get_usage() - $base;
echo $delta < 4096 ? "OK\n" : "LEAK ($delta)\n";

unlink($src); unlink($dst);
?>
--EXPECT--
OK
