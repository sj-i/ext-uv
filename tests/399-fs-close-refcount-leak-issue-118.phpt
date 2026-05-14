--TEST--
uv_fs_open + uv_fs_close in a loop should not leak refcount (issue 118)
--FILE--
<?php
$loop = uv_default_loop();
$path = tempnam(sys_get_temp_dir(), 'uvtest_');

// Warm up to settle allocator
for ($i = 0; $i < 100; $i++) {
    $done = false;
    uv_fs_open($loop, $path, UV::O_WRONLY, 0, function ($fh) use (&$done, $loop) {
        uv_fs_close($loop, $fh, function () use (&$done) { $done = true; });
    });
    while (!$done) uv_run($loop, UV::RUN_ONCE);
}

$baseline = memory_get_usage();

// Bug version: leaks ~448B per iter
for ($i = 0; $i < 1000; $i++) {
    $done = false;
    uv_fs_open($loop, $path, UV::O_WRONLY, 0, function ($fh) use (&$done, $loop) {
        uv_fs_close($loop, $fh, function () use (&$done) { $done = true; });
    });
    while (!$done) uv_run($loop, UV::RUN_ONCE);
}

$delta = memory_get_usage() - $baseline;
echo $delta < 4096 ? "OK\n" : "LEAK ($delta bytes over 1000 iters)\n";
unlink($path);
?>
--EXPECT--
OK
