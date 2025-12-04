# konawiki3 test

下記のコードを実行して、テストを行います。

```sh
just test
```

## テストの書き方

`hoge.test.php` や `fuga.test.php` というファイルを作成します。
そして、下記のようなファイルを作成します。

```php
<?php
require_once __DIR__ . '/test_common.inc.php';

test_eq(__LINE__, 1+1, 2, "test");
test_eq(__LINE__, 1+2, 3, "test");
```

