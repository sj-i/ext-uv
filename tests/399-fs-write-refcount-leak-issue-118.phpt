--TEST--
uv_fs_write does not leak resource refcount (issue 118)
--SKIPIF--
<?php
if (!extension_loaded('uv')) die("skip uv ext not loaded");
?>
--FILE--
<?php
$loop = uv_default_loop();
$path = tempnam(sys_get_temp_dir(), 'uvtest_');
$one = function () use ($loop, $path) {
    $fh = null; $d = false;
    uv_fs_open($loop, $path, UV::O_WRONLY, 0,
        function ($h) use (&$fh, &$d) { $fh = $h; $d = true; });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
    $d = false;
    uv_fs_write($loop, $fh, "hello\n", 0,
        function () use (&$d) { $d = true; });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
    $d = false;
    uv_fs_close($loop, $fh, function () use (&$d) { $d = true; });
    while (!$d) uv_run($loop, UV::RUN_ONCE);
};
/* warm up to settle the allocator */
for ($i = 0; $i < 100; $i++) $one();
$base = memory_get_usage();
for ($i = 0; $i < 1000; $i++) $one();
$delta = memory_get_usage() - $base;
unlink($path);
echo $delta < 4096 ? "OK\n" : "LEAK ($delta bytes)\n";
?>
--EXPECT--
OK
