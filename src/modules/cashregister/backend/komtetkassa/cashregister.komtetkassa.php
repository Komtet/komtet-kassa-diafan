<?php
/**
 * Бэкенд «Komtetkassa»
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

Custom::inc('modules/cashregister/backend/komtetkassa/index.php');

use Komtet\KassaSdk\v1\CalculationSubject;
use Komtet\KassaSdk\v1\Check;
use Komtet\KassaSdk\v1\Client;
use Komtet\KassaSdk\v1\Payment;
use Komtet\KassaSdk\v1\Position;
use Komtet\KassaSdk\v1\QueueManager;
use Komtet\KassaSdk\v1\TaxSystem;
use Komtet\KassaSdk\v1\Vat;
use Komtet\KassaSdk\Exception\SdkException;

class Cashregister_komtetkassa extends Diafan
{

    /**
     * Получение переменных из конфигурации модуля
     * @param string $name
     * @return string|bool
     */
    public function __get($name)
	{
        return $this->diafan->configmodules('komtetkassa_'.$name, 'cashregister');
    }

    /**
     * Создание переменных в конфигурации модуля
     * @param string $name
     * @param mixed $value
     * @return string
     */
    public function __set($name, $value)
	{
        return $this->diafan->configmodules('komtetkassa_'.$name, 'cashregister', false, false, $value);
    }

    /**
     * Чек «Полная оплата»
	 *
     * @param array $info данные о заказе
     * @return sting  Уникальный идентификатор чека
     */
    public function sell($info)
	{
        return $this->print_check($info, Check::INTENT_SELL);
    }

    /**
     * Чек «Предоплата 100%»
	 *
     * @param array $info данные о заказе
     * @return sting  Уникальный идентификатор чека
     */
    public function presell($info)
	{
        return $this->print_check($info, Check::INTENT_SELL, 'pre_payment_full');
    }

    /**
     * Чек «Возврат прихода»
	 *
     * @param array $info данные о заказе
     * @return sting Уникальный идентификатор чека
     */
    public function refund($info)
	{
        return $this->print_check($info, Check::INTENT_SELL_RETURN);
    }

    /**
     * Запрос для чеков
     * @param array $info данные о заказе
     * @param string $intent
     * @param string $calculation_method способ расчёта
     * @return sting Уникальный идентификатор чека
     * @throws KomtetkassaException
     */
    public function print_check($info, $intent, $calculation_method = 'full_payment')
    {
        if (! $this->shop_id)
        {
            throw new KomtetkassaException('Ошибка: Unable to print check: Shop ID is not specified.');
        }
        if (! $this->secret_key)
        {
            throw new KomtetkassaException('Ошибка: Unable to print check: Secret key is not specified.');
        }
        if (! $this->queue_id)
        {
            throw new KomtetkassaException('Ошибка: Unable to print check: Queue ID is not specified.');
        }

        // Проверяем, был ли сделан чек предоплаты по заказу
        $isPrePaymentFull = (bool) DB::query_result(
            "SELECT id FROM {shop_cashregister} WHERE order_id=%d AND type='%s' LIMIT 1",
            $info['id'],
            'presell'
        );

        // Конфигурация SDK
        $client = new Client($this->shop_id, $this->secret_key);
        $manager = new QueueManager($client);
        $manager->registerQueue('default', $this->queue_id);
        $manager->setDefaultQueue('default');

        if ($info["email"]) {
            $userContact = $info["email"];
        } else {
            $phone = preg_replace('/\D/', '', $info["phone"]);

            if (strlen($phone) == 11 && $phone[0] == '8') {
                $phone[0] = '7';
            }

            if (strlen($phone) == 11 && $phone[0] == '7') {
                $userContact = '+' . $phone;
            } else {
                $userContact = $phone;
            }
        }

        // Формирование чека
        $check = new Check($info["cashregister_id"], $userContact, $intent, (int) $this->tax_system);
        $check->setShouldPrint((bool) $this->should_print);

        if ($this->internet) {
            $check->setInternet(true);
        }

        $vatRate = $this->diafan->configmodules('tax', 'shop');
        // В чеках аванса и предоплаты для ставок НДС 5%, 7%, 10% и 20% необходимо использовать
        // расчетную ставку 5/105%, 7/107%, 10/110% и 20/120%. Письмо ФНС России от 03.07.2018 N ЕД-4-20/12717
        if ($calculation_method === 'pre_payment_full') {
            switch ($vatRate) {
                case 5:
                    $vatRate = Vat::RATE_105;
                    break;
                case 7:
                    $vatRate = Vat::RATE_107;
                    break;
                case 10:
                    $vatRate = Vat::RATE_110;
                    break;
                case 20:
                    $vatRate = Vat::RATE_120;
                    break;
                case 22:
                    $vatRate = Vat::RATE_122;
                    break;
            }
        }

        $vat = $vatRate ? new Vat($vatRate) : new Vat(Vat::RATE_NO);

        foreach ($info['rows'] as $row)
        {
            $position = new Position(
                html_entity_decode($row['name']),
                (float) $row['price'],
                (float) $row['count'],
                (float) $row['summ'],
                $vat
            );

            // Устанавливаем способ расчета и предмет расчета
            $position->setCalculationMethod($calculation_method);

            if ($calculation_method === 'pre_payment_full') {
                $position->setCalculationSubject(CalculationSubject::PAYMENT);
            } else {
                $position->setCalculationSubject(!empty($row["is_delivery"])
                    ? CalculationSubject::SERVICE
                    : CalculationSubject::PRODUCT
                );
            }

            $check->addPosition($position);
        }

		$payment_type = Payment::TYPE_CASH;
		if($payment_id = DB::query_result("SELECT payment_id FROM {payment_history} WHERE module_name='cart' AND element_id=%d", $info['id']))
		{
			if($payment = DB::query_result("SELECT payment FROM {payment} WHERE id=%d", $payment_id))
			{
				$payment_type = Payment::TYPE_CARD;
			}
		}

        // Если ранее был чек предоплаты, то меняем способ оплаты на "Платеж"
        if ($isPrePaymentFull && $calculation_method === 'full_payment' && $intent !== Check::INTENT_SELL_RETURN) {
            $payment_type = Payment::TYPE_PREPAYMENT;
        }

        $check->addPayment(new Payment($payment_type, (float) $info['summ']));

        // Добавляем чек в очередь
        try
        {
            $manager->putCheck($check);
        }
        catch (SdkException $e)
        {
            throw new KomtetkassaException($e->getMessage());
        }
    }
}

class KomtetkassaException extends Exception {}
