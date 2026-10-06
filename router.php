<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('#^/data/(?:main/[0-9]+\.(?:jpg|png)|posts/[^/]+\.(?:jpg|jpeg|png|pdf))$#i', $path)) return false;
if (preg_match('#^/(data|fixtures)/#',$path)) { http_response_code(403); exit; }
return false;
