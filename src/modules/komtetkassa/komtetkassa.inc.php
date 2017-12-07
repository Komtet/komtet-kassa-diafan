<?php

if (!defined('DIAFAN'))
{
    include dirname(__file__) . '/../../includes/404.php';
}

Custom::inc('plugins/komtetkassa/index.php');

use Komtet\KassaSdk\Check;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\Payment;
use Komtet\KassaSdk\Position;
use Komtet\KassaSdk\QueueManager;
use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Vat;

class Komtetkassa_inc extends Diafan
{
    public function print_check($orderID)
    {
        if (!$this->diafan->configmodules('enabled', 'komtetkassa')) {
            // Интеграция с кассой была выключена в настройках модуля
            return;
        }

        $order = $this->getOrder($orderID);

        /*
        $order['status'] хранит действие для установленного статуса
        0 - поступление заказа
        1 - оплата
        2 - отмена заказа
        3 - выполнение заказа
        4 - ничего не делать
        */
        if ($order['status'] == '1') {
            $intent = Check::INTENT_SELL;
        } else if ($order['status'] == '2') {
            $intent = Check::INTENT_SELL_RETURN;
        } else {
            return;
        }

        // Получаем настройки модуля для взаимодействия с API кассы
        $serverUrl = $this->diafan->configmodules('server_url', 'komtetkassa');
        $shopID = $this->diafan->configmodules('shop_id', 'komtetkassa');
        if (empty($shopID)) {
            error_log('Unable to print check: Shop ID is not specified');
            return;
        }
        $secretKey = $this->diafan->configmodules('secret_key', 'komtetkassa');
        if (empty($secretKey)) {
            error_log('Unable to print check: Secret key is not specified');
            return;
        }
        $queueID = $this->diafan->configmodules('queue_id', 'komtetkassa');
        if (empty($queueID)) {
            error_log('Unable to print check: Queue ID is not specified');
            return;
        }
        $taxSystem = $this->diafan->configmodules('tax_system', 'komtetkassa');
        if ($taxSystem === false) {
            error_log('Unable to print check: Tax system is not specified');
            return;
        }
        $shouldPrint = (bool) $this->diafan->configmodules('should_print', 'komtetkassa');

        // Конфигурация SDK
        $client = new Client($shopID, $secretKey);
        if ($serverUrl) {
            $client->setHost($serverUrl);
        }
        $manager = new QueueManager($client);
        $manager->registerQueue('default', $queueID);
        $manager->setDefaultQueue('default');

        // Формирование чека
        $email = $this->getOrderEmail($orderID);
        $check = new Check($orderID, $email, $intent, (int) $taxSystem);
        $check->setShouldPrint($shouldPrint);
        foreach ($this->getOrderPositions($order) as $position) {
            $check->addPosition($position);
        }
        $payment = DB::query_result("
            SELECT payment FROM {payment}
            JOIN {payment_history} ON {payment_history}.payment_id = {payment}.id
            WHERE {payment_history}.module_name='cart'
            AND {payment_history}.element_id=%d
        ", $orderID);
        $paymentType = $payment ? Payment::TYPE_CARD : Payment::TYPE_CASH;
        $check->addPayment(new Payment($paymentType, (float) $order['summ']));

        // Добавляем чек в очередь
        try {
            $manager->putCheck($check);
        } catch (SdkException $e) {
            $this->createReport($orderID, false, $e->getMessage());
        }
    }

    public function createReport($orderID, $success, $errorMessage = '')
    {
        DB::query(sprintf("
            INSERT INTO {komtet_kassa_report} SET
            order_id=%d,
            status=%d,
            error='%s',
            created=%d
        ", $orderID, $success ? 0 : 1, $errorMessage, time()));
    }

    /**
     * Возвращает данные заказа
     *
     * @param int $orderID Идентификатор заказа
     *
     * @return array
     */
    private function getOrder($orderID)
    {
        return DB::query_fetch_array("SELECT * FROM {shop_order} WHERE id=%d AND trash='0'", $orderID);
    }

    /**
     * Возвращает E-Mail пользователя, совершившего заказ
     *
     * @param int $orderID Идентификатор заказа
     *
     * @return mixed
     */
    private function getOrderEmail($orderID)
    {
        $result = DB::query_result("
            SELECT value FROM {shop_order_param_element} AS e
            INNER JOIN {shop_order_param} AS p ON p.id=e.param_id AND p.type='email'
            WHERE e.element_id=%d ", $orderID);
        return $result ? $result : '';
    }

    private function getOrderPositions($order)
    {
        $result = array();

        $summ_goods = 0;

        $rows = DB::query_fetch_all("
            SELECT g.id, g.count_goods AS `count`, g.good_id, g.price, g.discount_id, s.[name]
            FROM {shop_order_goods} AS g
            INNER JOIN {shop} AS s ON g.good_id=s.id
            WHERE g.order_id=%d ORDER by g.id ASC
        ", $order['id']);

        $ids = array();
        foreach ($rows as $row) {
            $ids[] = $row["id"];
        }

        $good_params = DB::query_fetch_key_array("SELECT * FROM {shop_order_goods_param} WHERE order_goods_id IN (%s)", implode(",", $ids), "order_goods_id");
        $discount_ids = array();
        foreach ($rows as $i => $row) {
            $params = array();
            if (!empty($good_params[$row["id"]])) {
                foreach ($good_params[$row["id"]] as $row_p) {
                    $params[$row_p["param_id"]] = $row_p["value"];
                }
            }
            $row_price = $this->diafan->_shop->price_get($row["good_id"], $params, false);
            if ($row_price) {
                $row_prices[$row["id"]] = $row_price;
            }
            if ($row["discount_id"] && !in_array($row["discount_id"], $discount_ids)) {
                $discount_ids[] = $row["discount_id"];
            }
        }

        if (!empty($discount_ids)) {
            $discounts = DB::query_fetch_key("SELECT id, discount, deduction FROM {shop_discount} WHERE id IN (%s)", implode(",", $discount_ids), "id");
        } else {
            $discounts = array();
        }

        $additional_costs = DB::query_fetch_key_array("
            SELECT a.id, a.[name], s.summ, s.order_goods_id FROM {shop_additional_cost} AS a
            INNER JOIN {shop_order_additional_cost} AS s ON s.additional_cost_id=a.id AND s.order_id=%d
            WHERE a.trash='0' AND a.shop_rel='1'
            ORDER BY a.sort ASC
        ", $order['id'], "order_goods_id");

        $vatRate = $this->diafan->configmodules('tax', 'shop');
        if ($vatRate) {
            $vat = new Vat($vatRate);
        } else {
            $vat = new Vat(Vat::RATE_NO);
        }

        if ($order['discount_summ']) {
            $total_rows = count($rows);
            $order_discount_part = floatval($order['discount_summ']) / $total_rows;
        } else {
            $order_discount_part = 0;
        }

        foreach ($rows as $row) {
            $row_price = !empty($row_prices[$row["id"]]) ? $row_prices[$row["id"]] : false;
            if (!empty($additional_costs[$row["id"]])) {
                foreach ($additional_costs[$row["id"]] as $a) {
                    $row["price"] += $a["summ"] / $row["count"];
                }
            }

            $row["summ"] = $row["count"] * $row["price"];

            $summ_goods += $row["summ"];

            if ($row["discount_id"] && !empty($discounts[$row["discount_id"]])) {
                $discount = $discounts[$row["discount_id"]];
                if (!empty($discount["deduction"])) {
                    $row["discount"] = $discount["deduction"];
                } else {
                    $row["discount"] = $row["price"] / (100 - $discount["discount"]) * $discount["discount"];
                }
            } elseif (!empty($row_price["old_price"])) {
                $row["discount"] = $row_price["old_price"] - $row["price"];
            } else {
                $row["discount"] = 0;
            }

            $row['discount'] += $order_discount_part;

            $result[] = new Position(
                html_entity_decode($row['name']),
                (float) $row['price'],
                (float) $row['count'],
                (float) $row['summ'],
                (float) $row['discount'],
                $vat
            );
        }

        if ($order["discount_summ"]) {
            $summ_goods = $summ_goods - $order["discount_summ"];
        }

        $additional_costs = DB::query_fetch_all("
            SELECT
                a.id, a.[name], a.price, a.percent, a.[text], a.amount, a.required, o.summ
            FROM {shop_additional_cost} AS a
            INNER JOIN {shop_order_additional_cost} AS o ON o.additional_cost_id=a.id
            WHERE a.trash='0' AND o.order_id=%d AND a.shop_rel='0' ORDER by sort ASC
        ", $order['id']);

        foreach ($additional_costs as $row) {
            if ($row['summ']) {
                $row['price'] = $row['summ'];
            } else {
                if (!empty($row['amount']) && $row['amount'] < $summ_goods) {
                    // Услуга предоставляется бесплатно для суммы > $row['amount']
                    $row['summ'] = 0;
                } elseif ($row['percent']) {
                    $row['summ'] = $summ_goods * $row['percent'] / 100;
                    $row['price'] = $row['summ'];
                } else {
                    $row['summ'] = $row['price'];
                }
            }
            $result[] = new Position($row['name'], (float) $row['price'], 1, (float) $row['summ'], 0, $vat);
        }

        if ($order['delivery_summ']) {
            $delivery_summ = (float) $order['delivery_summ'];
            $result[] = new Position('Доставка', $delivery_summ, 1, $delivery_summ, 0, $vat);
        }

        return $result;
    }
}
