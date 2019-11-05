<?php

/* Group Name: Синхронизация
 * User Level: 3
 * Plugin Name: MoedeloSync
 * Plugin URI: http://isellsoft.com
 * Version: 1.0
 * Description: MoedeloSync
 * Author: baycik 2019
 * Author URI: http://isellsoft.com
 */


class MoedeloSync extends Catalog {
    public $settings = [];
    
    
    
    public function syncBegin(){
        //$rows_done = $this->productManage();
        $rows_done = $this->syncCompanies();
        /*if($rows_done < $limit){
            
        }*/
    }
    
    public $syncProducts = [];
    public function syncProducts(){
        $modes = ['POST'];
        $limit = 10;
        foreach($modes as $mode){
            $product_list = $this->productGetList($mode, $limit);
            if(!empty($product_list)){
                $rows_done = $this->productManage($product_list, $mode);
            }
        }
    }
    
    public $syncCompanies = [];
    public function syncCompanies(){
        $modes = ['POST'];
        $limit = 10;
        return;
        foreach($modes as $mode){
            $product_list = $this->productGetList($mode, $limit);
            if(!empty($product_list)){
                $rows_done = $this->productManage($product_list, $mode);
            }
        }
    }
    
    private function productManage($product_list, $mode){
        $rows_done = 0;
        foreach($product_list as $key => $product){
            $remote_id = $product->remote_id;
            $product_object = [
                "NomenclatureId" => $product->nomenclature_id,
                "Name" => $product->product_name,
                "Article" => $product->product_code,
                "UnitOfMeasurement" => $product->product_unit,
                "Nds" => $product->product_vat_rate,
                "SalePrice" => $product->sell_price,
                "Type" => $product->product_type,
                "NdsPositionType" => $product->vat_position,
                "Producer" => $product->analyse_brand
            ];
            if($mode === 'POST'){
                $uploaded_product = $this->apiExecute('good', $mode, $product_object);
                $remote_id = json_decode($uploaded_product)->Id;
                $this->productInsert($remote_id,$product);
            } else if($mode === 'PUT'){
                $this->apiExecute('good', $mode, $product_object, $remote_id);
                $this->productUpdate($product->entry_id, $product);
            }else if($mode === 'DELETE'){
                $uploaded_product = $this->apiExecute('good', $mode, null, $remote_id);
                $this->productDelete($product_object->entry_id);
            }
            $rows_done += 1;
        }
        return $rows_done;
    }
   
    
    private function productGetList($mode, $limit){
        $nomenclature_id = '11780959';
        $vat_rate = '20';
        $where = '';
        $insert = '';
        $update = '';
        $delete = '';
        $delete2 = '';
        
        if($mode === 'POST'){
            $insert = 'LEFT JOIN sync_entries sye ON (hashed.product_id = sye.local_id)';
            $where .= 'WHERE sye.entry_id IS NULL';
        } else if($mode === 'PUT'){
            $update = 'LEFT JOIN sync_entries sye ON (hashed.product_id = sye.local_id)';
            $where .= 'WHERE hashed.row_hash != sye.remote_hash';
        }else if($mode === 'DELETE'){
            $delete = 'sync_entries sye LEFT JOIN';
            $delete2 = 'ON (hashed.product_id = sye.local_id)';
            $where .= 'WHERE hashed.product_id IS NULL';
        }
        echo $sql = "
            SELECT 
                hashed.*, sye.*
            FROM
                $delete
                (SELECT 
                    product_id,
                        MD5(CONCAT(nomenclature_id, 
                            product_name, 
                            t.product_code, 
                            product_unit, 
                            product_vat_rate, 
                            sell_price, 
                            product_type, 
                            vat_position, 
                            analyse_brand)) row_hash,
                        nomenclature_id,
                        product_name,
                        t.product_code,
                        product_unit,
                        product_vat_rate,
                        sell_price,
                        product_type,
                        vat_position,
                        analyse_brand
                FROM
                    (SELECT 
                    pl.*,
                        $nomenclature_id AS nomenclature_id,
                        ru AS product_name,
                        ROUND(pp.sell, 5) sell_price,
                        $vat_rate as product_vat_rate,
                        0 AS min_price,
                        0 AS product_type,
                        2 AS vat_position
                    FROM
                        stock_entries se
                    JOIN prod_list pl ON pl.product_code = se.product_code
                    LEFT JOIN price_list pp ON pp.product_code = se.product_code
                        AND pp.label = ''  GROUP BY product_id ) t
                JOIN document_entries de ON de.product_code = t.product_code
                JOIN document_list dl ON de.doc_id = dl.doc_id
                    AND dl.cstamp < NOW() - INTERVAL 1 YEAR
                GROUP BY product_id) hashed
                    $insert
                    $delete2
                    $where
                LIMIT $limit

            ";
        return $this->get_list($sql);
        
    }
    
    private function productInsert($uploaded_id,$product){
        $sql = "
            INSERT INTO 
                sync_entries 
            SET
                sync_destination = 'moedelo', 
                entry_action = 'insert',
                local_id = $product->product_id, 
                local_hash = '$product->row_hash', 
                local_tstamp = NULL, 
                remote_id = $uploaded_id, 
                remote_hash = '$product->row_hash', 
                remote_tstamp = NULL
            ";
        return $this->query($sql);
    }
    
    private function productUpdate($entry_id,$product){
        $sql = "
            UPDATE 
                sync_entries 
            SET
                entry_action = 'update',
                local_hash = '$product->row_hash', 
                remote_hash = '$product->row_hash'
            WHERE entry_id = $entry_id        
            ";
        return $this->query($sql);
    }
    
    private function productDelete($entry_id){
        $sql = "
            DELETE FROM 
                sync_entries 
            WHERE entry_id = $entry_id
            ";
        return $this->query($sql);
    }
    
    private function apiExecute($function, $method, $data = false, $remote_id = false){
        $data_string = '';
        $url = "https://restapi.moedelo.org/stock/api/v1/".$function;
        
        $curl = curl_init(); 
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
           'md-api-key: d04fe7e6-36e1-46cf-b480-d418edbc102d',
           'Content-Type: application/json',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        
        if($method == 'POST'){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } else if($method == 'PUT'){
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            if(!empty($remote_id)){
                $url .= '/'.$remote_id;
            }
        } else if($method == 'DELETE'){
            if(!empty($remote_id)){
                $url .= '/'.$remote_id;
            }
        } else if($method == 'GET'){
            if(!empty($data)){
                $options_array = [];
                foreach($data as $option_name => $option_value){
                    $options_array[] = $option_name.'='.urlencode($option_value);
                }
                $data_string = implode('&',$options_array);
            }
            $url .= '?'.$data_string;
        }
        
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
       
        $result = curl_exec($curl);
        if(!$result){die(curl_error($curl));}
        curl_close($curl);
        return $result;
    }
    

    public $updateSettings = ['settings' => 'json'];

    public function updateSettings($settings) {
        $this->settings = $settings;
        $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE);
        $sql = "
            UPDATE
                plugin_list
            SET 
                plugin_settings = '$encoded'
            WHERE plugin_system_name = 'CSVExporter'    
            ";
        $this->query($sql);
        return $this->getSettings();
    }

    public function getSettings() {
        $sql = "
            SELECT
                plugin_settings
            FROM 
                plugin_list
            WHERE plugin_system_name = 'CSVExporter'    
            ";
        $row = $this->get_row($sql);
        return json_decode($row->plugin_settings);
    }


    private function getCategories($category_id) {
        $branches = $this->treeGetSub('stock_tree', $category_id);
        return $branches;
    }

}
