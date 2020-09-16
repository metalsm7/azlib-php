<?php
/**
 * @file
 */
require_once __DIR__.'/../src/AZLib/azdata.php';
require_once __DIR__.'/../src/AZLib/azlist.php';
require_once __DIR__.'/../src/AZLib/azsql.php';
use AZLib\{AZData, AZList, AZSql};

$query = <<<QUERY
SELECT
  @season as season, @site_key as site_key
LIMIT @limit
QUERY;
$list = null;
try {
    $mem = memory_get_usage();
    $sql = AZSql::create($db)
        ->set_prepared(true)
        ->set_query($query)
        ->add_parameter('@season', '6')
        ->add_parameter('@site_key', 3)
        ->add_parameter('@limit', 10);
    echo 'query:'.$sql->get_compiled_query().PHP_EOL;
    echo 'params:'.$sql->get_parameters()->to_json_string().PHP_EOL;
    $list = $sql->get_list();
    echo "list:".$list->to_json_string().PHP_EOL;
    $list = null;
    //
    $sql->set_parameters(AZData::create()->add('@season', '1')->add('@site_key', 3)->add('@limit', 2));
    $list = $sql->get_list();
    echo "list:".$list->to_json_string().PHP_EOL;
    $list = null;
    //
    $data = $sql->get_data();
    echo 'data:'.$data->to_json_string().PHP_EOL;
    $data = null;
    //
    $col = $sql->get();
    echo 'col:'.$col.PHP_EOL;
    $col = null;
    //
    /*
    echo "mem:".(memory_get_usage() - $mem).PHP_EOL;
    $mem = memory_get_usage();
    $sql = null;
    echo "mem:".(memory_get_usage() - $mem).PHP_EOL;
    */

    //
    $sql
        ->clear()
        ->set_prepared(false)
        ->set_stored_procedure(true)
        ->set_query('CALL pro_test_output(@in_1, @in_2, @out)')
        ->set_parameters(AZData::create()->add('@in_1', 10)->add('@in_2', 20))
        ->add_return_parameter('@out');
    $multi = $sql->get_multi();
    echo 'multi.list:'.json_encode($multi).PHP_EOL;
    foreach ($multi as $row) {
        echo 'multi.row:'.$row->to_json_string().PHP_EOL;
    }
    echo 'multi.out:'.$sql->get_return_parameter('@out').PHP_EOL;
}
catch (\Throwable $ex) {
    echo "Throwable:".$ex->getCode()." / ".$ex->getMessage().PHP_EOL;
}
catch (\Exception $ex) {
    echo "Exception:".$ex->getCode()." / ".$ex->getMessage().PHP_EOL;
}

try {
    $bql = AZSql\Basic::create('event_spec_gold_info', $db)
        ->set_prepared(true)
        ->set('k1', 'v1')
        ->set('k2', 'v2')
        ->set('k3', 3)
        ->set('k11', 'k11+1', AZSql\VALUETYPE::QUERY)
        ->set('reg_date', 'NOW()', AZSql\VALUETYPE::QUERY);
    // $res = $bql->compile(AZLib\AZSql\CREATE_QUERY_TYPE::INSERT);
    $res = $bql->do_insert(true);
    echo "res:".json_encode($res).PHP_EOL;
}
catch (\Throwable  $ex) {
    echo "Throwable:".$ex->getCode()." / ".$ex->getMessage().PHP_EOL;
}
finally {
    echo "catch finally".PHP_EOL;
}