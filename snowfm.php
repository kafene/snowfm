<?php

/**
 * Snow File Manager
 * http://github.com/unu/snowfm
 */

class SnowFM{
  var $baseDir, $tpl;

  function __construct($baseDir){
    $this->baseDir = $baseDir;
    $this->tpl = new StdClass();
  }

  function render($_tpl){
    extract((array) $this->tpl);
    ob_start();
    eval('?>' . preg_replace(array(
        '_{\@([^}]+)}_',
        '_{\?([^}]+)}_',
        '_{\!([^}]+)}_',
        '_{\/([^}]+)}_',
        '_{\&([^}]+)}_',
        '_{([a-zA-Z0-9]+)}_',
        '_{([a-zA-Z0-9]+)(=[^}]+)}_',
        '_{-?([^\s}][^}]*)}_',
        '_\?>$_m'
      ), array(
        '<?php $_save_\1=get_defined_vars();foreach((isset($\1)&&is_array($\1)'
            . '?$\1:array())as$_item){ if(is_array($_item))extract($_item)?>',
        '<?php if(isset($\1)&&!!$\1){ ?>',
        '<?php if(!isset($\1)||!$\1){ ?>',
        '<?php }if(isset($_save_\1)&&is_array($_save_\1))extract($_save_\1)?>',
        '<?php echo isset($\1)?$\1:null?>',
        '<?php echo isset($\1)?htmlspecialchars(\$\1,ENT_QUOTES):null?>',
        '<?php $\1=$this->\1\2?>',
        '<?php \1?>',
        "?>\n"
      ), $_tpl));
    return ob_get_clean();
  }

  function listFiles($dir = null){
    $files = array();
    foreach (scandir($this->baseDir . '/' . $dir) as $fileName){
      if (in_array($fileName, array('.', '..')))
        continue;
      $filePath = "{$this->baseDir}/$dir/$fileName";
      if (is_dir($filePath))
        $fileType = 'folder';
      elseif (preg_match(';\.(jpe?g|png|gif|bmp)$;', $fileName))
        $fileType = 'image';
      elseif (preg_match(';\.(zip|tar|gz|tgz|bz2|7z|rar|iso)$;', $fileName))
        $fileType = 'archive';
      else
        $fileType = 'file';
      $files[] = array(
        'fileLink' => urlencode("$dir/$fileName"),
        'fileName' => $fileName,
        'isFile' => is_file($filePath),
        'isDir' => is_dir($filePath),
        'fileType' => $fileType,
      );
    }
    $dirs = $files2 = array();
    foreach ($files as $n => $file){
      if ($file['isDir'])
        $dirs[] = $file;
      else
        $files2[] = $file;
    }
    return array_merge($dirs, $files2);
  }

  function listParts($dir){
    $splits = explode('/', $dir);
    $splits = array_merge(array(basename($this->baseDir)), $splits);
    $parts = array();
    foreach ($splits as $n => $part){
      $fullPath = isset($fullPath) ? "$fullPath/$part" : '';
      $parts[] = array(
        'partName' => $part,
        'partLink' => urlencode($fullPath),
        'partIsLast' => ($n == count($splits) - 1),
      );
    }
    return $parts;
  }

  function downloadDir($path){
    ini_set('max_execution_time', 300);
    $tmp = tempnam(sys_get_temp_dir(), 'dir');
    self::zipDir($tmp, $this->baseDir . '/' . $path);
    $name = $path ? $path : $this->baseDir;
    $name = basename(realpath($name)) . '.zip';
    $name = preg_replace(';[^a-zA-Z0-9_\-\.];', '_', $name);
    header("Content-Type: application/zip");
    header("Accept-Ranges: bytes");
    header("Content-Length: " . filesize($tmp));
    header("Content-Disposition: attachment; filename=" . $name);
    readfile($tmp);
    unlink($tmp);
    exit();
  }
  function downloadDirStream($path){
    $name = $path ? $path : $this->baseDir;
    $name = basename(realpath($name)) . '.tar.gz';
    $name = preg_replace(';[^a-zA-Z0-9_\-\.];', '_', $name);
    header('Content-Type: application/x-gzip');
    #header('Content-Type: application/octet-stream');
    header('Content-disposition: attachment; filename="' . $name . '"');
    chdir(dirname($this->baseDir . '/' . $path));
    $fp = popen('tar cf - ' . basename($path) . ' | gzip -c', 'r');
    #$fp = popen('zip -r - ' . $this->baseDir . '/' . $path, 'r');
    $bufsize = 8192;
    $buff = '';
    while( !feof($fp) ) {
       $buff = fread($fp, $bufsize);
       echo $buff;
    }
    pclose($fp);
    exit();
  }

  static function zipDir($zipFile, $directory){
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE);
    $base = dirname(realpath($directory)) . '/';
    $it = new RecursiveDirectoryIterator(realpath($directory));
    $it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $node => $info){
      if ($info->isDir()){
        if (!in_array(basename($node), array('.', '..')))
          $zip->addEmptyDir(str_replace($base, '', $node));
      }
      elseif ($info->isFile())
        $zip->addFile($node, str_replace($base, '', $node));
    }
    $zip->close();
  }

  function run(){
    $get = (object) $_GET;
    $path = null;
    if (isset($get->dl)){
      $path = preg_replace(';/\.\./;', '', '/' . trim($get->dl, '/') . '/');
      $path = trim($path, '/');
      if (PHP_OS == 'Linux')
        return $this->downloadDirStream($path);
      else
        return $this->downloadDir($path);
    }
    if (isset($get->open)){
      $path = preg_replace(';/\.\./;', '', '/' . trim($get->open, '/') . '/');
      $path = trim($path, '/');
    }
    if (is_dir($this->baseDir . '/' . $path))
      return $this->renderListing($path);
    if (is_file($this->baseDir . '/' . $path))
      return header('Location: ' . $path);
  }

  function renderListing($path){
    $path = preg_replace(';/\.\./;', '', '/' . trim($path, '/') . '/');
    $path = trim($path, '/');
    $tpl = $this->tpl;
    $tpl->title = 'hello';
    $tpl->files = $this->listFiles($path);
    $tpl->parts = $this->listParts($path);
    if (!empty($path)){
      $tpl->name = basename($path);
      if (strstr($path, '/'))
        $tpl->upDir = urlencode(preg_replace(';/?[^/]*$;', null, $path));
      else
        $tpl->upDirTop = true;
    }
    else{
      $tpl->name = basename($this->baseDir);
    }
    $tpl->basePath = './' . basename(__FILE__);
    $tpl->linkOpen = $tpl->basePath . '?open=';
    $tpl->linkDl = $tpl->basePath . '?dl=';
    $tpl->dirLink = urlencode($path);
    echo $this->render($this->viewListing());
  }

  function viewListing(){
    ob_start();
    ?>
<html>
<head>
  <meta charset="utf-8">
  <title>{name}/</title>
  <link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAACZUlEQVRYw+2XPWhUQRDHf2r8QM8iEISTpIigKCoWxlJtFNJokRRqEbGTpFBUQvCKYCXpBAu1UUNArhFMxCMcxE8QJCBiiGgRYtQkiAHFFIoa72xmYRh39727HIiQgeXt2/nPzuzMvJl9sET/mJZVIXMUOAjsBDbIHnPAOHAfyAMLtTZ0PXADKMsoqblvLQ801Er5yYASN58B3pl1x+terPJbRtlr4CLwTNx+weBPeQwsLFa5HnXAYYNbCbwBvok3fOEZrtbtJUmoc0CTB7dNvJADpj2K9chVknA6lvUBXAvQA4wAzxOUu5FNY4DO9vYIbh3wUXCf5HkVOBv5SobSGODAryKYjOBOyHsbcAl4BMwDvyJeyCQVGQc8EsFtAh4DY8B7YA2wQni7RP5z4NPsihlwXQFDtB14a9z8A5iS+azyQBfQaTx7J2bAqNrUl/WrIpVQj8tqfl5kX8r7RMyAKSXY6eE3pcj0khzkpvQHRwXhz+sNlweaUxm44kmYzSkb3B7ggHyiOrn/aoDWgDkDOmP4D+SEeDa21AhsUe8bjQ4v9av4umerwWSBXuCaVLcPgTD8NB3Rrd+NGdARaLFbIzK9gVw4pjD71PrpmAF1no0WJPtDtFowVk4bXVSHqU9KorwnDEk06vHaDuHtVuvFNKW4wWzUkULmt6kNh5RHvypec9qO2K2MmAXWJuB7lPLbSvmkUt5X6Z2goIz4DuxPwD9UVa/FnPxptbeiYROOJ3IjDtFeiXO5Fsod5TwdrSy1/Z6MF4HO10eNKAsMVnAtL6ZNuEp/TDLA8YQfkwHgy9I/339DfwD0LTbc4nvjqQAAAABJRU5ErkJggg==">
  <style>
    body{
      margin: 2em auto;
      max-width: 40em;
      font-size: 12pt;
      font-family: "Droid Sans", "Arial", sans-serif;
    }
    a{
      text-decoration: none;
      color: #444;
    }
    a:hover{
      background-color: #222;
      color: white;
    }
    h1{
      margin: 0;
    }
    li a{
      display: block;
      padding: .5em .5em;
      margin-left: 40px;
    }
    ul{
      margin: 0;
      padding: 0;
    }
    li{
      list-style: none;
    }
    .parts{
      padding: .5em;
      font-size: 80%;
      color: #888;
    }
    .parts a{
      color: #aaa;
      text-decoration: underline;
    }
    .parts a:hover{
      text-decoration: underline;
      background-color: transparent;
      color: #444;
    }
    li{
      background: transparent url() 10px center no-repeat;
      overflow: visible;
      background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAABNklEQVRIx93VPy8EYRDH8c+dk4v4FxIKpUKjoNSpKFVCo9J5AXqFzjuQSGgkOr1GtHpa9bkIOcK54zTPJZt1u7e3dw2/ZLPZnXnm+8w8k2f46yrEvkdwiCW0uqwt4QynvQDHcBOCf6Q89eDTxHa3XcT1Hd5bIUg8yxbmcIRxnIT/F1kzuM5QnnlUw2bamex2ciz2eXaFSJxjbA4KUMREDDiE/VC21DPIogcsRDZYQ6VTZ+YFNAKkq0oZ7KsoJ9jroa0beQGTOMdsgr2CxdBRuQDPWE/xawaf3CUqYyfeGRHVcBBAuQDDWMZ0gv0p+OTO4AUbHa6L6LXx3g9gCreYSbBXsYLHvIBX7KW06WfIMncGDVz1M3DSAGsJ13UnvfUCaAe8HMCE/AX4wj1GI4Onl+B3IcY/0g+qNkDNR3620wAAAABJRU5ErkJggg==");
    }
    .folder{
      background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAA7ElEQVRIx+3VPUrFQBSG4cdrvIVY2oiNhaWVdxmuwVUoYuFK1A3oFkQEa4sLtoKthY2I/5jYTCQMcydiIljkhQPzA/Od72TmhIEW5hrjIprXfKL8rUDRGO9iA1WUwDN28NjVzXk4PBVXWOrqoMQH9nARsl/EESaY4hhPPyj7HU7jjTO8YitaX8ZJxl0qrlMOauaj+T22sY91jGdkXmETB3jJCaR4x22IHG/h1n0z6vnaj1oX/LXiIPAvBMoO55W5VlE/8xWszeisOSqsxkkXkZsxDvusTFPgEg8dS1RX4Sb1w1no8aNXob0MtPMFHtA2/YGTg9sAAAAASUVORK5CYII=");
    }
    .up{
      background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAA0klEQVRIx+3UsU4CQRSF4U/A2BlKCuIj+ABQakLLW/ledCR0xtZWGxKkEIzA2FySyWZ3Y1y2QW5yi8nM/OfMyczw3+oR07bgY3ziCw9twN+RopenFBljE+BDdIqTTE4Fz8EpE2sUVx5LEZ6axlWMpQr+p7hG2JZA6gQSvstO0i2MB3gK92+4xU2Nmau4us+x/h4zrKs29NAP8BCLGvfHfsFd7OnjugjMa4dVNt7/ItJ9OC513Wn7b7kIXATaF+g0ZfRq5naY4yNebNVX8Rprz7R+AABIV+ylmrbuAAAAAElFTkSuQmCC");
    }
    .image{
      background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAABOElEQVRIx+3VTysFYRQG8N+9uLJQFte/T2HD7pYkayk7ZWdtYaeUtY2UDeUD2NiRKDZWim+hxGVB8icam3Nrmq4xLsXCU6d3Ou95n2fOnPec4R+/jVLquYb+H+K9xXHWuYfnsJcW7RlPOGmQtqcEOsLOcBnZJU0y/QgJejGCSrOAw1Cf+ManqeEBpw1HuUlQV6zdOMA1hgsKdGUd5ZzggcimioXMXhWdRRTzBC6xg3OsZ85sYqWIQHvO3j1momD36MMVpjEVMQfYbTUDUfQ7zOECY9hO3Zot9HxHQJBvRLZHKXLRmMt51/gzgUmsZkiTIGyQzmOolRpUsYQb1HPi2rCG0aICT7HWv3D/G3j8TKCCRcwWHA/ZUTGY7Y+0wCveot1LLY6KJOy12bgejzf4CdSx//83/Bt4BxnYRbmW1i0hAAAAAElFTkSuQmCC");
    }
    .archive{
      background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAABH0lEQVRIx+3Vvy4EURQG8N+uTdBRSUioPAAbpYhCoSOi9gQST6DRqUQnCr3KAygkShKdRoJyCyEoFovVnGKymZ3Z2aWR/ZJTzNzvnu/8ufdc+shBKWNtHMto5Pio4Ay3RcUv0ezQbrLU07CNKs5xiK+MCmxgCXvY6iTyaTzjBfMd8GfxhFfM5JEHcBRpHxQo527sOc6oCpgL4kMXB+Yu9i5mke6DtNCFQDURXCmtyTuYwjUmsF5QoIKr6MM+NlsJpxFBA3W8hdXxgc8WS+M1wsdFWgbNxL/WRp1E6uX4/sYI1tpc1mbePUjiHatt1h4xmle3PAyiFlEnUY4s9CoAY90Ou/JfT9P/JTD0i36H0x6cFUwmz3APj1gtBl8f+fgBWd5IZa8hlWAAAAAASUVORK5CYII=");
    }
    .down{
      width: 24px;
      height: 24px;
      display: inline-block;
      text-indent: -20em;
      overflow: hidden;
      background: transparent url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAABGUlEQVRIx+3VMUrEQBTG8Z+ui6DCaidWFmKlroKFVmItlt7CxhvYWnkUT+ANBEEstPIAbmEUBHHd2EwgDNkkmxSi+GBImDff939J5mX47TFVY80FdjEq0D7gtG0R10jHjJsq8XQNwKhhrjagVfwDfh4wM2Z+Ez28YbFEv4B+uL7jti54Gc8B8FnSB0O8IsHqpE+3FiBp2O+xeTb3go2mr3APgwJIdp/goO13WsdHzjgP6jcx7GIWnQgyyBknkXknaLpV5nPh73mFkyi3HyAJDqPcUdBcYr4M0AtbLcV5QX47gOI4C5pHLFX1QRqdFfk1dwVzw1zDjuo2GhxjpWJNBtiapJOzynfCaHVCxoAv3Ieq0gbmT0H7h+IbSwdO8YiGcawAAAAASUVORK5CYII=") no-repeat;
    }
    .down:hover{
      background-color: #ccc;
    }
  </style>
</head>
<body>
  <h1><a class="down" href="{linkDl}{dirLink}">down</a> {name}</h1>
  <div class="parts">
    {@parts}
      {!partIsLast}
        <a href="{linkOpen}{partLink}">{partName}</a> /
      {/partIsLast}
      {?partIsLast}
        <span>{partName}</span>
      {/partIsLast}
    {/parts}
  </div>
  <ul>
    {?upDir}
      <li class="up"><a href="{linkOpen}{upDir}">..</a></li>
    {/upDir}
    {?upDirTop}
      <li class="up"><a href="{basePath}">..</a></li>
    {/upDirTop}
    {@files}
      <li class="{fileType}"><a href="{linkOpen}{fileLink}">{fileName}</a></li>
    {/files}
  </ul>
</body>
</html><?php
    return ob_get_clean();
  }

}

$fm = new SnowFM(__DIR__);
$fm->run();
