## PHP Mysqli Helper

### Install
> packagist : https://packagist.org/packages/metalsm7/azlib-php
```bash
$ composer require metalsm7/azlib-php
```

### AZData

### AZSql
#### Database 처리 헬퍼 (mysql 지원)
- AZData, AZList 객체로 결과 바인딩
- Prepared Statement 지원
- Stored Procedure 지원

#### 기본 사용법
```php
// 초기화
$sql = AZLib\AZSql::create($db); // $sql = new AZLib\AZSql($db);

// 기본
$col = $sql->set_query('SELECT id FROM users LIMIT 1')->get(); // 단일 결과 #1/2
$col = $sql->get('SELECT id FROM users LIMIT 1'); // 단일 결과 #2/2
$row = $sql->get_data('SELECT id, name from users LIMIT 1'); // 단행 결과
$rows = $sql->get_data('SELECT id, name from users LIMIT 3'); // 다행 결과

// 동적 및 prepared statement 적용
$query = 'SELECT id, name FROM users WHERE type=@type AND region=@region LIMIT @offset, @length';
$rows = $sql
  //->set_prepared(true) // prepared statement 적용하는 경우 true로 지정(기본값: false)
  ->set_query($query)
  /*
  ->add_parameter('@type', 'student') // set_parameters 대신 add_parameter 사용의 경우
  ->add_parameter('@region', 15)
  ->add_parameter('@offset', 0)
  ->add_parameter('@length', 10)
  */
  ->set_parameters(
    AZLib\AZData::create()
      ->add('@type', 'student')
      ->add('@region', 15)
      ->add('@offset', 0)
      ->add('@length', 10)
  )
  ->get_list();

// 반환값을 가지는 stored procedure 의 경우
$list = $sql
  //->set_prepared(true) // prepared statement 적용하는 경우 true로 지정(기본값: false)
  ->set_stored_procedure(true) // stored procedure 사용의 경우
  ->set_query('CALL proc_test(@arg1, @arg2, @out1, @out2)') // 인수값 arg1, arg2 와 반환값 out1, out2
  ->add_parameter('@arg1', 'input1') // 전달값 입력
  ->add_parameter('@arg2', 'input2')
  ->add_return_parameter('@out1') // 반환값 입력, 반환값으로 등록하지 않는 자료는 전달받지 못함
  ->add_return_parameter('@out2')
  ->get_multi(); // 프로시져 내 쿼리들에 대한 결과값 저장용, AZList[] 형식 반환
echo "out1:".$sql->get_return_parameter('@out1').PHP_EOL; // @out1 으로 지정된 반환값 확인
echo "out2:".$sql->get_return_parameter('@out2').PHP_EOL; // @out2 으로 지정된 반환값 확인
```
#### 단순화 처리 사용법
```php
// 초기화
$bql = AZLib\AZSql\Basic::create('users', $db); // $bql = new AZLib\AZSql\Basic('user', $db);

// insert
$bql
  //->set_prepared(true) // prepared statement 적용하는 경우 true로 지정(기본값: false)
  ->set('id', 'userid1')
  ->set('name', 'username1')
  ->set('type', 'teacher')
  ->set('region', 15)
  ->do_insert(true); // true 사용하는 경우 id값 반환, 그 외의 경우 영향을 받는 rows 갯수 반환
  // set_prepared(false)인 경우 -> INSERT INTO users (is, name, type, region) VALUES ('userid1', 'username1', 'teacher', 15)
  // set_prepared(true)인 경우 -> INSERT INTO users (is, name, type, region) VALUES (?, ?, ?, ?)

// update
$bql
  //->set_prepared(true) // prepared statement 적용하는 경우 true로 지정(기본값: false)
  ->set('type', 'teacher')
  ->where('name', 'username1')
  ->where('region', 15)
  ->do_update(); // 영향을 받는 rows 갯수 반환
  // set_prepared(false)인 경우 -> UPDATE users SET type='teacher' WHERE name='username1' AND region=15
  // set_prepared(true)인 경우 -> UPDATE users SET type=? WHERE name=? AND region=?

// delete
$bql
  //->set_prepared(true) // prepared statement 적용하는 경우 true로 지정(기본값: false)
  ->where('name', 'username2')
  ->where('region', 11)
  ->do_delete(); // 영향을 받는 rows 갯수 반환
  // set_prepared(false)인 경우 -> DELETE FROM users WHERE name='username2' AND region=11
  // set_prepared(true)인 경우 -> DELETE FROM users WHERE name=? AND region=?
```
