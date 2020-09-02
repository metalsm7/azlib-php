<?php
require_once(__DIR__.'/../src/azdata.php');
use metalsm7\AZLib\AZData;
$data = AZData::create()->add('k1', 234234);
echo $data->to_json_string()."\n";