<?php

if (!defined('DIAFAN'))
{
    include __DIR__ . '/../../../includes/404.php';
}

Custom::inc('plugins/komtetkassa/index.php');

use \Komtet\KassaSdk\TaxSystem;

class Komtetkassa_admin_config extends Frame_admin
{
    public $variables = array(
        'config' => array(
            'server_url' => array(
                'type' => 'text',
                'name' => 'URL сервера API'
            ),
            'secret_key' => array(
                'type' => 'text',
                'name' => 'Секретный ключ',
                'help' => 'Выдаётся при регистрации магазина'
            ),
            'shop_id' => array(
                'type' => 'text',
                'name' => 'ID магазина',
                'help' => 'Выдаётся при регистрации магазина'
            ),
            'queue_id' => array(
                'type' => 'text',
                'name' => 'ID очереди',
                'help' => 'Выдаётся при создании очереди'
            ),
            'tax_system' => array(
                'type' => 'select',
                'name' => 'СНО',
                'help' => 'Система налогооблажения',
                'select' => array(
                    array(
                        'id' => TaxSystem::COMMON,
                        'name' => 'ОСН',
                    ),
                    array(
                        'id' => TaxSystem::SIMPLIFIED_IN,
                        'name' => 'УСН Доход',
                    ),
                    array(
                        'id' => TaxSystem::SIMPLIFIED_IN_OUT,
                        'name' => 'УСН Доход - Расход',
                    ),
                    array(
                        'id' => TaxSystem::UTOII,
                        'name' => 'ЕНВД',
                    ),
                    array(
                        'id' => TaxSystem::UST,
                        'name' => 'ЕСН',
                    ),
                    array(
                        'id' => TaxSystem::PATENT,
                        'name' => 'Патент',
                    ),
                )
            ),
            'should_print' => array(
                'type' => 'checkbox',
                'name' => 'Печатать чеки',
                'help' => 'Включить/выключить печать чеков'
            ),
            'enabled' => array(
                'type' => 'checkbox',
                'name' => 'Включено',
                'help' => 'Включить/выключить интеграцию с кассой'
            )
        )
    );

    public $config = array(
        'config'
    );
}
