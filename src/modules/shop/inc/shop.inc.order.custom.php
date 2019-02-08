<?php

class Shop_inc_order extends Diafan {
  after public function set_status ($order, $status) {
    $this->diafan->_komtetkassa->print_check($order['id']);
  }
}