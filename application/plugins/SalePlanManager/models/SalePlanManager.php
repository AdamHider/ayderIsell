<?php
/* User Level: 2
 * Group Name: �������
 * Plugin Name: SalePlanManager
 * Version: 2019-01-05
 * Description: ������ ������ � ������� ����������
 * Author: baycik 2019
 * Author URI: 
 * Trigger After: DocumentItems
 */
class SalePlanManager extends Catalog{
    public $listFetch=[];
    public function afterDocumentItemsListFetch( string $hello ){
        echo 'por favor';
        return $this->Hub->previous_return;
    }
}