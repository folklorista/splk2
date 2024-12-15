<?php

require 'vendor/autoload.php';

use OpenApi\Generator;

$openapi = Generator::scan(['./src']);
file_put_contents('./swagger.json', $openapi->toJson());
echo "Swagger dokumentace byla vygenerována do swagger.json\n";
