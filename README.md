# Модуль КОМТЕТ Кассы для DIAFAN.CMS

Поддерживаемые версии: 6.0.

## Установка

Скачать [архив](https://github.com/Komtet/komtet-kassa-diafan/releases).

Содержимое директории `src` распаковать в директорию, где расположен ваш сайт.

В `modules/shop/inc/shop.inc.order.php` в методе `set_status($order, $status)` после строки:
```php
DB::query("UPDATE {shop_order} SET `status`='%d', status_id=%d, count_minus='%d' WHERE id=%d", $status["status"], $status["id"], $count_minus, $order["id"]);
```

добавить:

```php
$this->diafan->_komtetkassa->print_check($order['id']);
```

В админке включить модуль "КОМТЕТ Касса", после чего в левом меню появится пункт "КОМТЕТ Касса".
Перейти к настройкам модуля и заполнить необходимые поля.
