<?php

require dirname(__DIR__).'/src/RelayInstallerRuntime.php';

$runtime = new RelayInstallerRuntime(dirname(__DIR__));
$runtime->handle();
