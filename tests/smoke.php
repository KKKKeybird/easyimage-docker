<?php
// Runs inside a disposable container, with synthetic credentials and image data.
// Never run this fixture against a live application's configuration volumes.
declare(strict_types=1);
$root = '/app/web';
$configFile = $root.'/config/config.php';
$apiFile = $root.'/config/api_key.php';
$originalConfig = file_get_contents($configFile);
$originalApi = file_get_contents($apiFile);
require $configFile;
$token = bin2hex(random_bytes(24));
$created = [];
$fixture = tempnam('/tmp', 'easyimage-test-').'.png';
$image = imagecreatetruecolor(320, 240);
$blue = imagecolorallocate($image, 20, 80, 150);
imagefill($image, 0, 0, $blue);
imagepng($image, $fixture);
imagedestroy($image);
function check(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException($description);
    echo 'PASS '.$description."\n";
}
function saveConfig(array $updates): void {
    global $config, $configFile;
    $config = array_replace($config, $updates);
    file_put_contents($configFile, "<?php\n\$config=".var_export($config,true).";\n");
}
function http(string $path, ?array $post = null): array {
    $ch=curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
    if ($post!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
    $body=curl_exec($ch);
    if ($body===false) throw new RuntimeException(curl_error($ch));
    $status=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status,$body];
}
function upload(): array {
    global $token,$fixture,$created;
    [$status,$body]=http('/api/index.php',['token'=>$token,'image'=>new CURLFile($fixture,'image/png','fixture-'.bin2hex(random_bytes(5)).'.png')]);
    $json=json_decode($body,true);
    if ($status!==200 || !is_array($json) || ($json['result']??'')!=='success') {
        throw new RuntimeException('API upload failed: HTTP '.$status.' '.substr($body,0,700));
    }
    $path=parse_url($json['url'],PHP_URL_PATH);
    $created[]=$path;
    return [$json,$path];
}
try {
    check(version_compare(PHP_VERSION,'8.4.27','>='),'patched PHP version');
    foreach (['gd','mbstring','zip','fileinfo','iconv','openssl','exif'] as $extension) check(extension_loaded($extension),'extension '.$extension);
    for($attempt=0;$attempt<30;$attempt++) {
        try { [$status,$body]=http('/healthz'); if($status===200 && trim($body)==='ok') break; } catch(Throwable $e) {}
        sleep(1);
    }
    check(isset($status) && $status===200 && trim($body)==='ok','Nginx and PHP-FPM health');
    file_put_contents($apiFile,"<?php\n\$tokenList=".var_export([$token=>['id'=>999999,'expired'=>time()+3600,'add_time'=>time()]],true).";\n");
    saveConfig(['domain'=>'http://127.0.0.1','imgurl'=>'http://127.0.0.1','apiStatus'=>1,'check_ip'=>0,'chunks'=>0,'token_path_status'=>0,'hide'=>0,'hide_path'=>0,'md5_black'=>0,'upload_logs'=>0,'ip_upload_counts'=>0,'ftp_status'=>0,'checkImg'=>0,'imgConvert'=>'','watermark'=>0,'compress'=>0,'thumbnail'=>0,'show_user_hash_del'=>1,'image_recycl'=>0]);
    file_put_contents($root.'/config/install.lock','fixture');
    [$status,$body]=http('/'); check($status===200 && str_contains($body,'<html'),'homepage renders');
    [$json,$path]=upload();
    [$status,$body]=http($path);check($status===200 && getimagesizefromstring($body)[0]===320,'upload and original image read');
    $plain=$body;
    [$status,$body]=http('/api/index.php',['token'=>'invalid','image'=>new CURLFile($fixture,'image/png','invalid.png')]);
    check((json_decode($body,true)['code']??null)===202,'invalid API token rejected');
    [$status,$body]=http(parse_url($json['del'],PHP_URL_PATH).'?'.parse_url($json['del'],PHP_URL_QUERY));
    check((json_decode($body,true)['code']??null)===200,'delete-link request');
    [$status]=http($path);check($status===404,'deleted image no longer served');
    saveConfig(['compress'=>1,'compress_ratio'=>50]);
    [$json,$path]=upload();
    for($i=0;$i<30;$i++) {
        [$status,$body]=http($path);$size=@getimagesizefromstring($body);
        if($size && $size[0]===160 && $size[1]===120) break;
        usleep(100000);
    }
    check($status===200 && $size[0]===160 && $size[1]===120,'asynchronous image compression');
    saveConfig(['compress'=>0,'watermark'=>1,'waterText'=>'CI WATERMARK','textSize'=>24,'waterPosition'=>5]);
    [$json,$path]=upload();
    for($i=0;$i<30;$i++) {[$status,$body]=http($path);if($body!==$plain)break;usleep(100000);}
    check($status===200 && $body!==$plain && getimagesizefromstring($body)[0]===320,'text watermark changes image');
    saveConfig(['watermark'=>2,'waterPosition'=>5]);
    [$json,$path]=upload();
    for($i=0;$i<30;$i++) {[$status,$body]=http($path);if($body!==$plain)break;usleep(100000);}
    check($status===200 && $body!==$plain,'image watermark changes image');
    foreach(['/config/config.php','/config/api_key.php','/.git/config','/admin/logs/access.log'] as $path) {
        [$status]=http($path);check($status===404,'private path blocked '.$path);
    }
    file_put_contents($root.'/i/ci-do-not-execute.php','<?php echo "unsafe";');
    [$status]=http('/i/ci-do-not-execute.php');check($status===404,'uploaded PHP execution blocked');
    unlink($root.'/i/ci-do-not-execute.php');
    echo "SMOKE_TEST_OK\n";
} finally {
    file_put_contents($configFile,$originalConfig);
    file_put_contents($apiFile,$originalApi);
    foreach($created as $path) if(is_file($root.$path)) unlink($root.$path);
    if(is_file($fixture)) unlink($fixture);
}
