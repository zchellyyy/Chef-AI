<?php
echo "<pre>";
echo "CWD: " . getcwd() . "\n\n";
echo "Listing:\n";
foreach (glob(__DIR__.'/*') as $f) echo basename($f) . (is_dir($f)?"/":"") . "\n";
echo "\nExists src/Exception.php? " . (file_exists(__DIR__.'/src/Exception.php')?'YES':'NO') . "\n";
