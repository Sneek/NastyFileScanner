<?php

use Sneek\Scanner;

require_once 'vendor/autoload.php';

$config = require 'config.php';

$scanner = new Scanner(new Mandrill($config['api_key']));

$scanner->run($config['dir'], $config['to'], $config['subject']);