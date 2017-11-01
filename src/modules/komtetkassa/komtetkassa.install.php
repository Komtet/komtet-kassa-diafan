<?php

if (!defined('DIAFAN'))
{
    include dirname(__file__) . '/../../includes/404.php';
}

class Komtetkassa_install extends Install
{
    /**
     * Название модуля
     */
    public $title = "КОМТЕТ Касса";

    public $tables = array(
        array(
            "name" => "komtet_kassa_report",
            "fields" => array(
                array(
                    "name" => "id",
                    "type" => "INT(11) UNSIGNED NOT NULL AUTO_INCREMENT"
                ),
                array(
                    "name" => "order_id",
                    "type" => "INT(11) UNSIGNED NOT NULL"
                ),
                array(
                    "name" => "status",
                    "type" => "TINYINT(1) UNSIGNED NOT NULL"
                    // 0 - success
                    // 1 - failed
                ),
                array(
                    "name" => "error",
                    "type" => "TEXT NOT NULL DEFAULT ''"
                ),
                array(
                    "name" => "created",
                    "type" => "INT(10) UNSIGNED NOT NULL"
                )
            ),
            "keys" => array(
                "PRIMARY KEY(id)"
            )
        )
    );

    /**
     * Строка в таблице modules
     */
    public $modules = array(
        array(
            "name" => "komtetkassa",
            // Используется в админке
            "admin" => true,
            // Используется на сайте
            "site" => true,
            // Подключается к странице сайта
            "site_page" => false
        )
    );

    /**
     * Меню админки
     */
    public $admin = array(
        array(
            "name" => "КОМТЕТ Касса",
            "rewrite" => "komtetkassa",
            "group_id" => "4",
            "sort" => 10,
            "act" => true,
            "children" => array(
                array(
                    "name" => "Настройки",
                    "rewrite" => "komtetkassa/config"
                )
            )
        )
    );

    /**
     * Настройки
     */
    public $config = array(
        array(
            'name' => 'server_url',
            'value' => 'https://kassa.komtet.ru'
        ),
        array(
            'name' => 'enabled',
            'value' => '1'
        ),
        array(
            'name' => 'should_print',
            'value' => '1'
        ),
        array(
            'name' => 'tax_system',
            'value' => '0'
        )
    );
}
