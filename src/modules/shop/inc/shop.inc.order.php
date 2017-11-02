<?php
/**
 * Подключение модуля «Магазин» для работы с заказами
 *
 * @package    DIAFAN.CMS
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2017 OOO «Диафан» (http://www.diafan.ru/)
 */
if ( ! defined('DIAFAN'))
{
	$path = __FILE__; $i = 0;
	while(! file_exists($path.'/includes/404.php'))
	{
		if($i == 10) exit; $i++;
		$path = dirname($path);
	}
	include $path.'/includes/404.php';
}

class Shop_inc_order extends Diafan
{
	/**
	 * Получает все данные о товарах, дополнительных услугах, доставке и скидках в заказе
	 *
	 * @param integer $order_id номер заказа
	 * @return array
	 */
	public function get($order_id)
	{
		$result = DB::query_fetch_array("SELECT * FROM {shop_order} WHERE id=%d AND trash='0'", $order_id);

		$result["currency"] = $this->diafan->configmodules("currency", "shop");

		$result["count"] = 0;
		$result["discount"] = false;
		$result["summ_goods"] = 0;

		// корзина
		$result["rows"] = array();

		$rows = DB::query_fetch_all(
			"SELECT g.id, g.count_goods AS `count`, g.good_id, g.price, g.discount_id, s.[name], s.article, s.[measure_unit],  s.site_id, s.cat_id FROM {shop_order_goods} AS g"
			." INNER JOIN {shop} AS s ON g.good_id=s.id"
			." WHERE g.order_id=%d ORDER by g.id ASC",
			$order_id
		);
		$cat_ids = array();
		foreach ($rows as $row)
		{
			if(! empty($row["cat_id"]))
			{
				$this->diafan->_route->prepare($row["site_id"], $row["cat_id"], "shop", "cat");
				if(! in_array($row["cat_id"], $cat_ids))
				{
					$cat_ids[] = $row["cat_id"];
				}
			}
			$this->diafan->_route->prepare($row["site_id"], $row["good_id"], "shop");
			$this->diafan->_images->prepare($row["good_id"], "shop");
			$ids[] = $row["id"];
		}
		if($cat_ids)
		{
			$cats = DB::query_fetch_key_value("SELECT [name], id FROM {shop_category} WHERE trash='0' AND id IN (%s)", implode(",", $cat_ids), "id", "name");
		}
		if($rows)
		{
			$good_params = DB::query_fetch_key_array("SELECT * FROM {shop_order_goods_param} WHERE order_goods_id IN (%s)", implode(",", $ids), "order_goods_id");
			$param_name_ids = array();
			$param_value_ids = array();
			$discount_ids = array();
			foreach ($good_params as $i => $g_rows)
			{
				foreach($g_rows as $g_row)
				{
					if(! in_array($g_row["param_id"], $param_name_ids))
					{
						$param_name_ids[] = $g_row["param_id"];
					}
					if(! in_array($g_row["value"], $param_value_ids))
					{
						$param_value_ids[] = $g_row["value"];
					}
				}
			}
			if($param_name_ids)
			{
				$param_names  = DB::query_fetch_key_value("SELECT id, [name] FROM {shop_param} WHERE id IN (%s)", implode(",", $param_name_ids), "id", "name");
			}
			if($param_value_ids)
			{
				$param_values = DB::query_fetch_key_value("SELECT id, [name] FROM {shop_param_select} WHERE id IN (%s)", implode(",", $param_value_ids), "id", "name");
			}
		}
		foreach ($rows as $i => $row)
		{
			$params = array();
			if(! empty($good_params[$row["id"]]))
			{
				foreach ($good_params[$row["id"]] as $row_p)
				{
					$params[$row_p["param_id"]] = $row_p["value"];

					if(! $row_p["value"])
						continue;

					$rows[$i]["param"][$param_names[$row_p["param_id"]]] = $param_values[$row_p["value"]];
				}
			}
			$row_price = $this->diafan->_shop->price_get($row["good_id"], $params, false);
			if($row_price)
			{
				$price_ids[] = $row_price["price_id"];
				$row_prices[$row["id"]] = $row_price;
			}
			if($row["discount_id"])
			{
				if(! in_array($row["discount_id"], $discount_ids))
				{
					$discount_ids[] = $row["discount_id"];
				}
			}
		}
		if(! empty($price_ids))
		{
			$price_image_rels = DB::query_fetch_key_value("SELECT price_id, image_id FROM {shop_price_image_rel} WHERE price_id IN (%s)", implode(",", $price_ids), "price_id", "image_id");
		}
		if(! empty($discount_ids))
		{
			$discounts = DB::query_fetch_key("SELECT id, discount, deduction FROM {shop_discount} WHERE id IN (%s)", implode(",", $discount_ids), "id");
		}
		if(! empty($ids))
		{
			$additional_costs = DB::query_fetch_key_array("SELECT a.id, a.[name], s.summ, s.order_goods_id FROM {shop_additional_cost} AS a
			INNER JOIN {shop_order_additional_cost} AS s ON s.additional_cost_id=a.id AND s.order_id=%d
			WHERE a.trash='0' AND a.shop_rel='1'
			ORDER BY a.sort ASC", $order_id, "order_goods_id");
		}
		foreach ($rows as $row)
		{
			$row_price = ! empty($row_prices[$row["id"]]) ? $row_prices[$row["id"]] : false;
			$row["img"] = false;
			$img = $this->diafan->_images->get('medium', $row["good_id"], 'shop', 'element', $row["site_id"], $row["name"]);
			if ($img)
			{
				if($row_price && ! empty($price_image_rels[$row_price["price_id"]]))
				{
					foreach ($img as $i)
					{
						if($i["id"] == $price_image_rels[$row_price["price_id"]])
						{
							$row["img"] = $i;
						}
					}
				}
				if(empty($row["img"]))
				{
					$row["img"] = $img[0];
				}
			}
			if(! empty($row["cat_id"]) && ! empty($cats[$row["cat_id"]]))
			{
				$row["cat"] = array(
					'link' => $this->diafan->_route->link($row["site_id"], $row["cat_id"], "shop", "cat"),
					'name' => $cats[$row["cat_id"]],
				);
			}
			$row["link"] = $this->diafan->_route->link($row["site_id"], $row["good_id"], "shop");

			$row["additional_cost"] = array();
			if(! empty($additional_costs[$row["id"]]))
			{
				foreach($additional_costs[$row["id"]] as $a)
				{
					$a["format_summ"] = $this->diafan->_shop->price_format($a["summ"]);
					$row["price"] += $a["summ"] / $row["count"];
					$row["additional_cost"][] = $a;
				}
			}

			$result["summ_goods"] += $row["price"] * $row["count"];
			$result["count"] += $row["count"];

			$row["summ"] = $this->diafan->_shop->price_format($row["count"] * $row["price"]);
			$row["discount"] = 0;
			if($row["discount_id"] && ! empty($discounts[$row["discount_id"]]))
			{
				$discount = $discounts[$row["discount_id"]];
				$result["discount"] = true;
				if(! empty($discount["deduction"]))
				{
					$row["discount_summ"] = $discount["deduction"];
					$row["discount"] = $discount["deduction"].' '.$this->diafan->configmodules("currency", "shop");
				}
				else
				{
					$row["discount_summ"] = $this->diafan->_shop->price_format($row["price"] / (100 - $discount["discount"]) * $discount["discount"]);
					$row["discount"] = $discount["discount"].' %';
				}
			}
			elseif(! empty($row_price["old_price"]))
			{
				$result["discount"] = true;
				$row["discount_summ"] = $row_price["old_price"] - $row["price"];
				$row["discount"] = $this->diafan->_shop->price_format($row["discount_summ"]).' '.$this->diafan->configmodules("currency", "shop");
			}
            $row["old_price"] = $row_price ? $this->diafan->_shop->price_format($row_price["old_price"]) : '';
			$row["price"] = $this->diafan->_shop->price_format($row["price"]);
			if($row["measure_unit"])
			{
				$result["measure_unit"] = true;
			}
			$result["rows"][] = $row;
		}

		if($result["discount_summ"])
		{
			$result["old_summ_goods"] = $this->diafan->_shop->price_format($result["summ_goods"]);
			$result["summ_goods"] = $result["summ_goods"] - $result["discount_summ"];
			$result["discount_summ"] = $this->diafan->_shop->price_format($result["discount_summ"]);
			$result["discount"] = true;
		}

		// дополнительно
		$result["additional_cost"] = DB::query_fetch_all("SELECT a.id, a.[name], a.price, a.percent, a.[text], a.amount, a.required, o.summ FROM {shop_additional_cost} AS a INNER JOIN {shop_order_additional_cost} AS o ON o.additional_cost_id=a.id WHERE a.trash='0' AND o.order_id=%d AND a.shop_rel='0' ORDER by sort ASC", $order_id);
		foreach ($result["additional_cost"] as $i => $row)
		{
			$row["summ"] = $row['price'];
			if($row['percent'])
			{
				$row["summ"] = $result["summ_goods"] * $row['percent'] / 100;
			}
			if (! empty($row['amount']))
			{
				if ($row['amount'] < $result["summ_goods"])
				{
					$row["summ"] = 0;
				}
			}
			$row[$i]["price"] = $this->diafan->_shop->price_format($row["price"]);
			$row[$i]["summ"] = $this->diafan->_shop->price_format($row["summ"]);
		}

		if($result["delivery_id"])
		{
			$result["delivery"] = array(
				"name" => DB::query_result("SELECT [name] FROM {shop_delivery} WHERE id=%d", $result["delivery_id"]),
				"summ" => $this->diafan->_shop->price_format($result["delivery_summ"])
			);
		}

		if($this->diafan->configmodules('tax', 'shop'))
		{
			$result["tax"] = $this->diafan->_shop->price_format($result["summ"] * $this->diafan->configmodules('tax', 'shop') / (100 + $this->diafan->configmodules('tax', 'shop')));
			$result["tax_name"] = $this->diafan->configmodules('tax_name', 'shop');
		}
		$result["summ"] = $this->diafan->_shop->price_format($result["summ"]);
		$result["summ_goods"] = ! empty($result["summ_goods"]) ? $this->diafan->_shop->price_format($result["summ_goods"]) : 0;
		return $result;
	}

	/**
	 * Получает все данные из формы оформления заказа
	 *
	 * @param integer $order_id номер заказа
	 * @return array
	 */
	public function get_param($order_id)
	{
		$result = array();

		$values = DB::query_fetch_key_array("SELECT * FROM {shop_order_param_element} WHERE element_id=%d", $order_id, "param_id");

		$select = DB::query_fetch_key_array("SELECT id, param_id, [name], value FROM {shop_order_param_select} WHERE trash='0'", "param_id");

		$params = DB::query_fetch_all("SELECT id, [name], type, required, [text], config, info FROM {shop_order_param} WHERE (show_in_form='1' OR show_in_form_one_click='1') AND trash='0' ORDER BY sort ASC");

		foreach ($params as $param)
		{
			if (empty($values[$param["id"]]) && ! in_array($param["type"], array("checkbox", 'attachments', 'images')))
			{
				continue;
			}
			if($param["type"] == "checkbox")
			{
				if(! empty($select[$param["id"]]))
				{
					foreach($select[$param["id"]] as $s)
					{
						$select_values[$param["id"]][$s["value"]] = $s["name"];
					}
				}
			}
			if($param["type"] == "select" || $param["type"] == "multiple")
			{
				if(! empty($select[$param["id"]]))
				{
					foreach($select[$param["id"]] as $s)
					{
						$select_array[$s["id"]] = $s["name"];
					}
				}
			}

			// добавляем файлы
			switch($param["type"])
			{
				case "images":
					$value = $this->diafan->_images->get('large', $order_id, "shop_order", 'element', 0, '', $param["id"]);
					if(! $value)
						continue 2;

					$param["value"] = $value;
					break;

				case "attachments":
					$config = unserialize($param["config"]);
					if($config["attachments_access_admin"])
						continue 2;

					$value = $this->diafan->_attachments->get($order_id, "shop_order", $param["id"]);
					if(! $value)
						continue 2;

					$param["value"] = $value;
					$param["use_animation"] = ! empty($config["use_animation"]) ? true : false;
					break;

				case "text":
				case "textarea":
				case "email":
				case "phone":
					$param["value"] = $values[$param["id"]][0]["value"];
					break;

				case "date":
					$param["value"] = $this->diafan->formate_from_date($values[$param["id"]][0]["value"]);
					break;

				case "datetime":
					$param["value"] = $this->diafan->formate_from_datetime($values[$param["id"]][0]["value"]);
					break;

				case "checkbox":
					$value = ! empty($values[$param["id"]][0]["value"]) ? 1 : 0;
					$param["value"] = empty($select_values[$param["id"]][$value]) ? $select_values[$param["id"]][$value] : '';
					break;

				case "select":
				case "multiple":
					foreach($values[$param["id"]] as $v)
					{
						if(! empty($select_array[$v["value"]]))
						{
							$param["value"][] = $select_array[$v["value"]];
						}
					}
					break;
			}
			$result[] = $param;
		}
		return $result;
	}

	/**
	 * Оплата заказ (смена статуса на «В обработке»)
	 *
	 * @param integer $order_id номер заказа
	 * @return void
	 */
	public function pay($order_id)
	{
		$status = DB::query_fetch_array("SELECT * FROM {shop_order_status} WHERE status='1' LIMIT 1");
		$order = DB::query_fetch_array("SELECT * FROM {shop_order} WHERE id=%d LIMIT 1", $order_id);
		$this->set_status($order, $status);
	}

	/**
	 * Установка статуса
	 *
	 * @param array $order информация о заказе
	 * @param array $status информация о статусе заказа
	 * @return void
	 */
	public function set_status($order, $status)
	{
		//отправить ссылки на купленные файлы, если они есть
		if ($status["status"] == '1' && $this->diafan->configmodules("use_non_material_goods", "shop"))
		{
			$shop_ids = DB::query_fetch_value("SELECT * FROM {shop_order_goods} WHERE order_id=%d", $order["id"], "good_id");
			//выполнить необходимые операции, с нематериальными товарами
			$this->shop_file_sale($order["id"], $shop_ids);
		}

		$count_minus = $order["count_minus"];
		if ($status["count_minus"] && ! $order["count_minus"] && $this->diafan->configmodules("use_count_goods", "shop"))
		{
			$count_minus = 1;
		}
		if (! $status["count_minus"] && $order["count_minus"] && $this->diafan->configmodules("use_count_goods", "shop"))
		{
			$count_minus = 0;
		}


        DB::query("UPDATE {shop_order} SET `status`='%d', status_id=%d, count_minus='%d' WHERE id=%d", $status["status"], $status["id"], $count_minus, $order["id"]);
        $this->diafan->_komtetkassa->print_check($order['id']);

		if($status["send_mail"])
		{
			Custom::inc('includes/mail.php');
			$email = ($this->diafan->configmodules("emailconf", 'shop')
					   && $this->diafan->configmodules("email", 'shop')
					   ? $this->diafan->configmodules("email", 'shop') : '' );

			$user_mail = DB::query_result(
					"SELECT value FROM {shop_order_param_element} AS e"
					." INNER JOIN {shop_order_param} AS p ON p.id=e.param_id AND p.type='email'"
					." WHERE e.element_id=%d", $order["id"]
				);
			if($user_mail)
			{
				$lang_id = $order["lang_id"];
				if(! $lang_id)
				{
					$lang_id = $this->diafan->_languages->site;
				}
				$subject = str_replace(array('%title', '%url'), array(TITLE, BASE_URL), $this->diafan->configmodules('subject_change_status', 'shop', 0, $lang_id));

				$message = str_replace(array('%title', '%url', '%order', '%status'), array (TITLE, BASE_URL, $order["id"], $status["name".$lang_id]), $this->diafan->configmodules('message_change_status', 'shop', 0, $lang_id));

				send_mail($user_mail, $subject, $message,  $email);
			}
		}

		if ($status["count_minus"] && ! $order["count_minus"] && $this->diafan->configmodules("use_count_goods", "shop"))
		{
			$rows = DB::query_fetch_all("SELECT * FROM {shop_order_goods} WHERE order_id=%d", $order["id"]);
			foreach ($rows as $row)
			{
				if ($row["count_goods"])
				{
					$params = DB::query_fetch_key_value("SELECT * FROM {shop_order_goods_param} WHERE order_goods_id=%d", $row["id"], "param_id", "value");
					$row_price = $this->diafan->_shop->price_get($row["good_id"], $params, false);
					$count = $row_price['count_goods'] > $row["count_goods"] ? $row_price['count_goods'] - $row["count_goods"] : 0;
					// уменьшаем количество товаров на складе
					DB::query("UPDATE {shop_price} SET count_goods=%f WHERE price_id=%d", $count, $row_price["price_id"]);
				}
			}
		}

		if (! $status["count_minus"] && $order["count_minus"] && $this->diafan->configmodules("use_count_goods", "shop"))
		{
			$rows = DB::query_fetch_all("SELECT * FROM {shop_order_goods} WHERE order_id=%d", $order["id"]);
			foreach ($rows as $row)
			{
				if ($row["count_goods"])
				{
					$params = DB::query_fetch_key_value("SELECT * FROM {shop_order_goods_param} WHERE order_goods_id=%d", $row["id"], "param_id", "value");
					$row_price = $this->diafan->_shop->price_get($row["good_id"], $params, false);
					// увеличиваем количество товаров на складе
					DB::query("UPDATE {shop_price} SET count_goods=%f WHERE price_id=%d", $row_price['count_goods'] + $row["count_goods"], $row_price["price_id"]);
					if(! $row_price['count_goods'])
					{
						$price_params = DB::query_fetch_key_value("SELECT param_id, param_value FROM {shop_price_param} WHERE price_id=%d", $row_price["price_id"], "param_id", "param_value");
						$this->diafan->_shop->price_send_mail_waitlist($row["good_id"], $price_params);
					}
				}
			}
		}
	}

	/**
	 * Возврат информаци о плательщике
	 *
	 * @param integer $order_id ID заказа
	 * @return array
	 */
	public function details($order_id)
	{
		$params = DB::query_fetch_key_value("SELECT param_id, value FROM {shop_order_param_element} WHERE element_id=%d", $order_id, "param_id", "value");

		$rows = DB::query_fetch_all("SELECT id, info, type FROM {shop_order_param} WHERE trash='0'");
		foreach ($rows as $row)
		{
			if(empty($params[$row["id"]]))
				continue;

			$result[$row["info"]] = (! empty($result[$row["info"]]) ? $result[$row["info"]].' ' : '').$params[$row["id"]];
		}

		$order_summ = 0;

		$rows = DB::query_fetch_all("SELECT * FROM {shop_order_goods} where order_id=%d", $order_id);
		foreach ($rows as $row)
		{
			$depend = '';
			$params = array();
			$rows_p = DB::query_fetch_all("SELECT * FROM {shop_order_goods_param} WHERE order_goods_id=%d", $row["id"]);
			foreach ($rows_p as $row_p)
			{
				$params[$row_p["param_id"]] = $row_p["value"];

				if(! $row_p["value"])
					continue;
				$param_name = DB::query_result("SELECT [name] FROM {shop_param} WHERE id=%d LIMIT 1", $row_p["param_id"]);
				$param_value = DB::query_result("SELECT s.[name] FROM {shop_param_select} as s WHERE s.id=%d AND s.param_id=%d LIMIT 1", $row_p["value"], $row_p["param_id"]);
				$depend .= ($depend ? ', ' : ' ').$param_name.': '.$param_value;
			}
			$row_shop = DB::query_fetch_array("SELECT [name], article FROM {shop} WHERE id=%d LIMIT 1", $row["good_id"]);
			$row_good["name"] = $row_shop["name"].$depend;
			$row_good["article"] = $row_shop["article"];
			$order_summ += $row["price"] * $row["count_goods"];
			$row_good["summ"] = number_format($row["price"] * $row["count_goods"], 2, '.', '');
			$row_good["price"] = number_format($row["price"], 2, '.', '');
			$row_good["count"] = $row["count_goods"];
			$result["goods"][] = $row_good;
		}

		$rows = DB::query_fetch_all("SELECT a.[name], s.summ FROM {shop_additional_cost} AS a INNER JOIN {shop_order_additional_cost} AS s ON s.additional_cost_id=a.id AND s.order_id=%d WHERE a.trash='0'", $order_id);
		foreach ($rows as $row)
		{
			$order_summ += $row["summ"];
			$row["summ"] = number_format($row["summ"], 2, '.', '');
			$result["additional"][] = $row;
		}

		$row = DB::query_fetch_array("SELECT * FROM {shop_order} WHERE id=%d", $order_id);
		if($row["delivery_id"])
		{
			$order_summ += $row["delivery_summ"];
			$result["delivery"] = array(
				'name' => DB::query_result("SELECT [name] FROM {shop_delivery} WHERE id=%d", $row["delivery_id"]),
				'summ' => number_format($row["delivery_summ"], 2, '.', ''),
			);
		}
		if($order_summ != $row["summ"])
		{
			$result["discount"] = number_format($order_summ - $row["summ"], 2, '.', '');
		}
		return $result;
	}

	/**
	 * Выполнение необходимых операций с нематериальными товарами
	 *
	 * @param integer $order_id номер заказа
	 * @param array $shop_ids массив всех товаров в заказе (не обязательно нематериальные)
	 *
	 * @return boolean true
	 */
	private function shop_file_sale($order_id, $shop_ids = array())
	{
		$codes = array(); //коды для подтверждения покупки товара
		//время действие ссылки на  купленные  нематериальные товары - текущее время +1 день
		$timestamp_finish = mktime(date('H'), date('i'), 0, date('m'), date('d') + 1, date('Y'));
		$date_finish = date('Y-m-d H:i', $timestamp_finish);

		//id нематериальных товаров
		$new_shop_ids = DB::query_fetch_value("SELECT id FROM {shop} WHERE id IN (%s) AND is_file='1' AND trash='0'", implode(',', $shop_ids), "id");

		if (empty($new_shop_ids))//если нет нематериальных товаров
		{
			return false;
		}

		foreach ($new_shop_ids as $shop_id)
		{
			$code = md5($date_finish.$shop_id.'file_sale');

			DB::query("INSERT INTO {shop_files_codes} (shop_id, code, date_finish) VALUES(%d, '%s', '%s')", $shop_id, $code, $date_finish);

			$codes[$shop_id] = $code;
		}

		$files_list = $this->get_files_list($new_shop_ids, $codes); //список файлов  со ссылками
		$this->send_mail($order_id, $files_list);

		return true;
	}

	/**
	 * Формирование таблицы со ссылками на куленные товары
	 *
	 * @param array $shop_ids массив всех товаров в заказе (не обязательно нематериальные)
	 * @param array $codes массив кодов для скачивания
	 * @return string
	 */
	private function get_files_list($shop_ids, $codes)
	{
		$text = '<table>
			<tr>
				<th>'.$this->diafan->_('Наименование товара', false).'</th>
				<th>'.$this->diafan->_('Ссылка на товар', false).'</th>
			</tr>';


		$rows = DB::query_fetch_all("SELECT id, [name], site_id FROM {shop} WHERE id IN (%s)", implode(',', $shop_ids));
		foreach ($rows as $row)
		{
			$good_rewrite = $this->diafan->_route->link($row["site_id"], $row["id"], "shop");
			$shop_rewrite = $this->diafan->_route->link($row["site_id"]).'?action=file&code=';

			$text .= '
				<tr>
					<td> <a href="'.BASE_PATH.$good_rewrite.'">'.$row["name"].'</a></td>
					<td>'.BASE_PATH.$shop_rewrite.$codes[$row["id"]].'</td>
				</tr>';
		}
		$text .= '</table>';

		return $text;
	}

	/**
	 * Отправляет письмо пользователю, купившему нематериальные товары
	 *
	 * @param integer $order_id номер заказа
	 * @param string $files_list таблицы со ссылками на куленные товары
	 * @return boolean
	 */
	private function send_mail($order_id, $files_list)
	{
		if (! $order_id || ! $files_list)
		{
			return false;
		}

		if (! $mail = $this->get_email($order_id))
		{
			return false;
		}

		Custom::inc('includes/mail.php');

		$subject = str_replace(
				array('%title', '%url', '%id'), array(TITLE, BASE_URL, $order_id), $this->diafan->configmodules('subject_file_sale_message', 'shop')
		);

		$message = str_replace(
			array('%title', '%url', '%id', '%files'), array(
				TITLE,
				BASE_URL,
				$order_id,
				$files_list
			), $this->diafan->configmodules('file_sale_message', 'shop')
		);

		send_mail(
				$mail, $subject, $message, $this->diafan->configmodules("emailconf", 'shop') ?
						$this->diafan->configmodules("email", 'shop') : ''
		);
		return true;
	}

	/**
	 * Получает e-mail пользователя, оформившего заказ
	 *
	 * @param  integer $order_id
	 * @return string
	 */
	private function get_email($order_id)
	{
		$mail = DB::query_result("SELECT e.value FROM {shop_order_param_element} AS e INNER JOIN
			{shop_order_param} AS p ON e.param_id=p.id AND p.trash='0' AND e.trash='0'
			WHERE p.type='email' AND e.element_id=%d", $order_id);

		if (! $mail && $user_id = DB::query_result("SELECT user_id FROM {shop_order} WHERE id=%d AND trash='0' LIMIT 1", $order_id))
		{
			$mail = DB::query_result("SELECT mail FROM {users} WHERE id=%d  AND trash='0' LIMIT 1", $user_id);
		}

		return $mail;
	}
}
