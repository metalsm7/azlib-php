<?php
require_once(__DIR__.'/../src/AZLib/azdata.php');
require_once(__DIR__.'/../src/AZLib/azsql.php');
use AZLib\AZData;
use AZLib\AZSql;
$data = AZData::create()->add('k1', 234234);
echo $data->to_json_string()."\n";
$query = <<<QUERY
SELECT
  u.user_key, u.u_nick, u.u_level,
  esgi.point_1, esgi.point_2, esgi.total_gold_cnt,
  IFNULL(acti.runtime, 0) as runtime,
  (
    SELECT v.vod_key
    FROM vod AS v
    WHERE v.user_key = u.user_key
      AND v.v_type = 1
      AND v.v_state = 1
    LIMIT 1
  ) AS live_vod_key
FROM
  (
    SELECT
      esgi.season,
      esgi.bj_user_key,
      SUM(IF(esgi.gold_su = 15, 1, 0)) AS point_1,
      SUM(IF(esgi.gold_su = 1001, 1, 0)) AS point_2,
      SUM(esgi.gold_su) AS total_gold_cnt
    FROM
      event_spec_gold_info AS esgi
    WHERE
      esgi.season = @season
    GROUP BY
      esgi.bj_user_key, esgi.gold_su
  ) as esgi
  INNER JOIN user AS u
    ON u.user_key = esgi.bj_user_key
      AND u.u_state = 1
  INNER JOIN user_info_site AS uis
    ON uis.user_key=u.user_key
      AND uis.site_key = @site_key
  LEFT JOIN (
    SELECT acti.season, acti.site_key, acti.bj_user_key, MAX(acti.runtime) as runtime
    FROM event_accrue_time_info AS acti
    WHERE
      acti.season = @season
      AND acti.site_key=@site_key
    GROUP BY
      acti.season, acti.site_key, acti.bj_user_key
  ) AS acti
    ON acti.bj_user_key=u.user_key
ORDER BY
  esgi.total_gold_cnt DESC,
  acti.runtime DESC
LIMIT 10;
QUERY;
$params = AZData::create()->add('@season', 1)->add('@site_key', 3);
$sql = new AZSql();
$sql
  ->set_prepared(true)
  ->set_query($query)
  ->set_parameters($params);
echo 'query:'.$sql->get_compiled_query()."\r\n";


try {
  $bql = AZLib\AZSql\BQuery::create('event_spec_gold_info')
    ->set_prepared(true)
    ->set('k1', 'v1')
    ->set('k2', 'v2')
    ->set('k3', 3)
    ->set('k11', 'k11+1', AZLib\AZSql\VALUETYPE::QUERY)
    ->set('reg_date', 'NOW()', AZLib\AZSql\VALUETYPE::QUERY);
  $res = $bql->compile(AZLib\AZSql\CREATE_QUERY_TYPE::INSERT);
  echo "res:".json_encode($res)."\r\n";
}
catch (\Throwable  $ex) {
  echo "Throwable:".$ex->getCode()." / ".$ex->getMessage()."\r\n";
}
finally {
  echo "catch finally\r\n";
}