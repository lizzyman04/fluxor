<?php
/**
 * @var int    $statusCode
 * @var string $class
 * @var string $message
 * @var string $file
 * @var int    $line
 * @var string $trace
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $statusCode ?> — <?= $class ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0f0f0f;color:#e2e2e2;padding:2rem}
h1{font-size:1.1rem;font-weight:600;color:#f87171;word-break:break-word;margin:.5rem 0}
.badge{display:inline-block;background:#3f1313;color:#f87171;border:1px solid #7f2020;font-size:.75rem;padding:.15rem .5rem;border-radius:4px;font-family:monospace}
.msg{font-size:1rem;margin:.75rem 0 1.25rem;color:#cbd5e1;line-height:1.5}
.loc{font-family:monospace;font-size:.8rem;color:#64748b;margin-bottom:1.5rem}
.loc span{color:#94a3b8}
h2{font-size:.7rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:.5rem}
pre{background:#18181b;border:1px solid #27272a;border-radius:6px;padding:1rem;font-size:.78rem;line-height:1.6;overflow-x:auto;color:#a1a1aa;white-space:pre}
</style>
</head>
<body>
<span class="badge"><?= $statusCode ?></span>
<h1><?= $class ?></h1>
<p class="msg"><?= $message ?></p>
<p class="loc"><span><?= $file ?></span> line <?= $line ?></p>
<h2>Stack Trace</h2>
<pre><?= $trace ?></pre>
</body>
</html>
