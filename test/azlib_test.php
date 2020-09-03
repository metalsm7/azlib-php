<?php
require_once(__DIR__.'/../src/AZLib/azdata.php');
use AZLib\AZData;
$data = AZData::create()->add('k1', 234234);
echo $data->to_json_string()."\n";