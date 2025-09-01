<?php
/**
 * Настройки бэкенда «Komtetkassa»
 *
 * @package    DIAFAN.CMS
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2019 OOO «Диафан» (http://www.diafan.ru/)
 */

if ( ! defined('DIAFAN'))
{
	$path = __FILE__;
	while(! file_exists($path.'/includes/404.php'))
	{
		$parent = dirname($path);
		if($parent == $path) exit;
		$path = $parent;
	}
	include $path.'/includes/404.php';
}

use \Komtet\KassaSdk\v1\TaxSystem;
Custom::inc('modules/cashregister/backend/komtetkassa/index.php');

class Cashregister_komtetkassa_admin extends Diafan
{
    /**
     * @var array настройки кассы
     */
	public $config = array(
		'name' => 'КОМТЕТ Касса',
		'params' => array(
            'shop_id' => array(
                'type' => 'text',
                'name' => 'ID магазина',
                'help' => 'Выдаётся при регистрации магазина'
            ),
            'secret_key' => array(
                'type' => 'text',
                'name' => 'Секретный ключ',
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
                    TaxSystem::COMMON => 'ОСН',
					TaxSystem::SIMPLIFIED_IN => 'УСН Доход',
                    TaxSystem::SIMPLIFIED_IN_OUT => 'УСН Доход - Расход',
					TaxSystem::UST => 'ЕСХН',
					TaxSystem::PATENT => 'Патент',
                ),
            ),
            'should_print' => array(
                'type' => 'checkbox',
                'name' => 'Печатать чеки',
                'help' => 'Включить/выключить печать чеков'
            ),
            'internet' => array(
                'type' => 'checkbox',
                'name' => 'Признак расчета в сети «Интернет»',
                'help' => 'Признак применения ККТ при осуществлении расчета в безналичном порядке в сети «Интернет»'
            ),
        )
    );
}