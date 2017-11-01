<?php

if (!defined('DIAFAN'))
{
    include dirname(__file__) . '/../../../includes/404.php';
}

class Komtetkassa_admin extends Frame_admin
{
    public $table = 'komtet_kassa_report';

     public $variables_list = array(
        'created' => array(
            'name' => 'Дата',
            'type' => 'datetime',
            'sql' => true
        ),
        'order_id' => array(
            'type' => 'numtext',
            'name' => 'Заказ',
            'sql' => true
        ),
        'status' => array(
            'type' => 'numtext',
            'name' => 'Статус',
            'sql' => true
        ),
        'error' => array(
            'type' => 'text',
            'name' => 'Описание ошибки',
            'sql' => true
        ),
    );

    public function show()
    {
        $this->diafan->list_row();
    }

    public function list_variable_status($row) {
        if ($row['status'] == 1) {
            $icon = 'exclamation-triangle';
            $title = 'Failed';
            $color = '#ff4444';
        } else {
            $icon = 'check-square-o';
            $title = 'Success';
            $color = '#007700';
        }
        return sprintf('<div title="%s"><i class="fa fa-%s" style="color: %s"></i></div>', $title, $icon, $color);
    }

    public function list_variable_order_id($row) {
        return sprintf(
            '<div><a href="%sshop/order/edit%s/">Заказ № %s</a></div>',
            BASE_PATH_HREF,
            $row['order_id'],
            $row['order_id']
        );
    }
}
