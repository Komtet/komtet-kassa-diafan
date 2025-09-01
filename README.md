# Модуль КОМТЕТ Кассы для DIAFAN.CMS

## Запуск проекта

- Скачать проект

```sh
git clone --recursive git@github.com:Komtet/komtet-kassa-diafan.git
```

- Добавьте в /etc/hosts 127.0.0.1 diafan-kassa.localhost

```sh
127.0.0.1       diafan-kassa.localhost
```

- Создайте символьную ссылку на diafan_nginx.cfg в sites-enabled nginx

```sh
sudo ln -s ~/[путь_до_проекта]/komtet-kassa-diafan/diafan_nginx.cfg /etc/nginx/sites-enabled/diafan_nginx.cfg
```

- Проверьте конфигурацию nginx и перезапустите

```sh
sudo nginx -t
sudo nginx -s reload
```

- Создайте в корне проекта директорию  `php`
- Скопируйте содержимое архива DIAFAN.CMS.zip (скачать можно в [личном кабинете](https://user.diafan.ru/)) в директорию `php`
- Запустите сборку контейнера
```sh
make build
```
- Запустить проект

```sh
make start_web_7_4
```
- Проект будет доступен по адресу: http://diafan-kassa.localhost

## Установка DIAFAN.CMS

- Создание базы данных

```sh
Host: mysql
База данных: test_db
Пользователь: devuser
Пароль: devpass
```
- Выполнить установку DIAFAN.CMS. В процессе установки пропускаем "Заполнить сайт демо-контентом" (ломается)

## Установка/Обновление плагина Комтет Кассы для фискализации чеков

- Переходим в `Интернет магазин` -> `Оплата` -> `Он-лайн касса` -> `Настройки модуля`
- В разделе `Сервис онлайн-кассы` -> `Добавить онлайн-кассу` -> `Установить`
- Возвращаемся в настройки модуля онлайн касс в разделе оплаты
- В разделе `Сервис онлайн-кассы` появится `Komtetkassa`
- Заполняем поля данными из личного кабинета

- Обновить плагин Комтет Кассы
```sh
make update
```
