CodeIgniter, mysqli

### Install
> packagist : https://packagist.org/packages/metalsm7/azlib-php
```bash
$ composer require metalsm7/azlib-php
```

```php
$query = <<<QUERY
SELECT
  u.idx, u.name, u.type, u.reg_date
FROM
  user as u
WHERE
  u.type=@type
ORDER BY
  u.reg_date DESC
LIMIT @offset, @count;
QUERY;
//
$list = AZLib\AZSql::create($this->db)
  // ->set_prepared(true)
  ->set_query($query)
  // ->add_parameter('@type', 'normal')
  // ->add_parameter('@offset', 10)
  // ->add_parameter('@count', 10)
  ->set_parameters(array('@type' => 'normal', '@offset' => 10, '@count' => 10))
  // ->set_parameters(AZLib\AZData::create()->add('@type', 'normal')->add('@offset', 10)->add('@count', 10))
  ->get_list();
echo "list:".$list->to_json_string();
//
$bql = AZLib\AZSql\BQuery::create('user')
  ->set('k1', 'v1')
  ->set('k2', 'v2')
  ->set('k3', 3)
  ->set('k11', 'k11+1', AZLib\AZSql\VALUETYPE::QUERY)
  ->set('reg_date', 'NOW()', AZLib\AZSql\VALUETYPE::QUERY)
  ->where('k31', "CONCAT(k31, 'test')", AZLib\AZSql\VALUETYPE::QUERY)
  ->where('k4', 'tr')
  ->where('k5', 123, AZLib\AZSql\WHERETYPE::GREATER_THAN_OR_EQUAL)
  ->where('k6', [1, 3, 12], AZLib\AZSql\WHERETYPE::IN)
  ->where('k7', ['2020-09-11', '2020-09-18'], AZLib\AZSql\WHERETYPE::BETWEEN);
echo "query:".json_encode($bql->set_prepared(false)->compile(AZLib\AZSql\CREATE_QUERY_TYPE::UPDATE));
//
try {
  $bql = AZLib\AZSql\Basic::create('user', $this->db)
    ->set_prepared(false)
    ->set('k1', 'v1')
    ->set('k2', 'v2')
    ->set('k3', 3)
    ->set('k11', 'k11+1', AZLib\AZSql\VALUETYPE::QUERY)
    ->set('reg_date', 'NOW()', AZLib\AZSql\VALUETYPE::QUERY);
  $bql->do_insert(true);
}
catch (\Throwable  $ex) {
  echo "Throwable:".$ex->getCode()." / ".$ex->getMessage()."<br />";
}
finally {
  echo "catch finally<br />";
}
```
