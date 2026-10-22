<?php
header('Content-Type: text/plain');
if (version_compare(PHP_VERSION, '8.4.27', '<')) {
    http_response_code(503);
    exit('unsupported PHP');
}
echo "ok\n";
