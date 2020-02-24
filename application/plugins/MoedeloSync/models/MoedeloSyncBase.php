<?php

class MoedeloSyncBase extends Catalog{
    protected $acomp_id=2;
    protected $local_tzone='+03:00';
    protected $remote_tzone='+00:00';
    protected $sync_since="";
    protected $sync_time_window=365;
    
    private $gateway_url=null;
    private $gateway_md_apikey=null;
    
    function init() {
        session_write_close();
        set_time_limit(600);
        $this->Hub->MoedeloSync->getSettings();
        $this->sync_since=$this->Hub->MoedeloSync->settings->sync_since;
    }
    
    function toTimezone($isotstamp,$zone='local'){
        $from_tzone=$this->remote_tzone;
        $to_tzone=$this->local_tzone;
        if( $zone=='remote' ){
            $to_tzone=$this->remote_tzone;
            $from_tzone=$this->local_tzone;
        }
        $given = new DateTime("$isotstamp $from_tzone");
        $given->setTimezone( new DateTimeZone($to_tzone) );
        return $given->format("Y-m-d\TH:i:s");
    }
    
    public function setGateway( $url ){
        $this->gateway_url=$url;
    }
    public function setApiKey( $key ){
        $this->gateway_md_apikey=$key;
    }
    
    
    protected function apiExecute( string $function, string $method, $data = null, int $remote_id = null){
        $url = "{$this->Hub->MoedeloSync->settings->gateway_url}$function/$remote_id";
        
        $curl = curl_init(); 
        switch( $method ){
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            case 'GET':
                $query=$data?http_build_query($data):'';
                $url .= "?$query";
                break;
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["md-api-key: {$this->Hub->MoedeloSync->settings->gateway_md_apikey}","Content-Type: application/json"]);

        $result = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if( curl_error($curl) ){
            $this->log("{$this->doc_config->sync_destination} API Execute error: ".curl_error($curl));
            die(curl_error($curl));
        }
        curl_close($curl);
        return (object)[
            'httpcode'=>$httpcode,
            'response'=>json_decode($result)
            ];
    }
    
/*    protected function apiSend( $sync_destination, $remote_function, $document_list, $mode ){
        $rows_done=0;
        foreach($document_list as $document){
            if($mode === 'REMOTE_INSERT'){
                $response = $this->apiExecute($remote_function, 'POST', (array) $document);
                if( isset($response->response) && isset($response->response->Id) ){
                    $this->logInsert($sync_destination,$document->local_id,$document->current_hash,$response->response->Id);
                    $rows_done++;
                } else {
                    $error=$this->getValidationErrors($response);
                    $this->log("{$sync_destination} INSERT is unsuccessfull (HTTP CODE:$response->httpcode '$error') Number:#{$document->Number}");
                }
            } else 
            if($mode === 'REMOTE_UPDATE'){
                $response = $this->apiExecute($remote_function, 'PUT', (array) $document, $document->remote_id);
                if( $response->httpcode==200 ){
                    $this->logUpdate($document->entry_id, $document->current_hash);
                    $rows_done++;
                } else {
                    $error=$this->getValidationErrors($response);
                    $this->log("{$sync_destination} UPDATE is unsuccessfull (HTTP CODE:$response->httpcode '$error') Number:#{$document->Number}");
                }
            } else 
            if($mode === 'REMOTE_DELETE'){
                $response = $this->apiExecute($remote_function, 'REMOTE_DELETE', null, $document->remote_id);
                $this->logDelete($document->entry_id);
                $rows_done++;
                if( $response->httpcode!=204 ) {
                    $error=$this->getValidationErrors($response);
                    $this->log("{$sync_destination} DELETE is unsuccessfull (HTTP CODE:$response->httpcode '$error') Number:#{$document->Number}");
                }
            }
        }
        return $rows_done;
    }
    
    
    protected function apiInsert($sync_destination,$remote_function,$document){
        $response = $this->apiExecute($remote_function, 'POST', (array) $document);
        if( isset($response->response) && isset($response->response->Id) ){
            $this->logInsert($sync_destination,$document->local_id,$document->current_hash,$response->response->Id);
            $rows_done++;
        } else {
            $error=$this->getValidationErrors($response);
            $this->log("{$sync_destination} INSERT is unsuccessfull (HTTP CODE:$response->httpcode '$error') Number:#{$document->Number}");
        }
    }*/
    
    
    protected function logInsert( $sync_destination, $local_id, $local_hash, $remote_id ){
        $sql = "
            INSERT INTO 
                plugin_sync_entries 
            SET
                sync_destination = '$sync_destination',
                local_id = '$local_id', 
                local_hash = '$local_hash', 
                local_tstamp = NOW(), 
                remote_id = '$remote_id'";
        return $this->query($sql);
    }
    
    protected function logUpdate($entry_id,$local_hash){
        $sql = "
            UPDATE 
                plugin_sync_entries 
            SET
                remote_hash = '$local_hash',
                local_hash = '$local_hash',
                local_tstamp=NOW()
            WHERE entry_id = '$entry_id'";
        return $this->query($sql);
    }
    
    protected function logDelete($entry_id){
        $sql = "
            DELETE FROM 
                plugin_sync_entries 
            WHERE entry_id = '$entry_id'";
        return $this->query($sql);
    }
    
    public function log( $message ){
        parent::log($message);
        echo "$message\n";
    }
    
    protected function getValidationErrors( $response ){
        $error_text=$response->response->Message??'';
        if( isset($response->response->ValidationErrors) ){
            foreach( $response->response->ValidationErrors as $key1=>$errors ){
                foreach($errors as $key2=>$err){
                    $error_text.=" $key1 $key2 : $err;";
                }
            }
        }
        return $error_text;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     * Links remote and local entities on same sync_destination and hash
     */
    private function linkByHash(){
        $create_link_list="
            CREATE TEMPORARY TABLE tmp_linked_enries AS 
            (SELECT 
                 pse_local.sync_destination,
                 pse_local.entry_id local_entry_id,
                 pse_local.local_id,
                 pse_local.local_hash,
                 pse_local.local_tstamp,
                 pse_remote.entry_id remote_entry_id,
                 pse_remote.remote_id,
                 pse_remote.remote_hash,
                 pse_remote.remote_tstamp
             FROM 
                 plugin_sync_entries pse_remote
                     JOIN
                 plugin_sync_entries pse_local ON pse_remote.remote_hash=pse_local.local_hash AND pse_local.sync_destination=pse_remote.sync_destination
             WHERE
                 (pse_remote.local_id IS NULL OR pse_local.local_id IS NULL)
                 AND pse_local.sync_destination='{$this->doc_config->sync_destination}'
             GROUP BY local_entry_id);";
        $clear_unlinked_entries="
            DELETE pse FROM 
                plugin_sync_entries pse
                    JOIN
                tmp_linked_enries ON (entry_id=local_entry_id OR entry_id=remote_entry_id)";
        $fill_linked_entries="
            INSERT INTO plugin_sync_entries 
                (sync_destination,local_id,local_hash,local_tstamp,remote_id,remote_hash,remote_tstamp)
            SELECT 
                sync_destination,local_id,local_hash,local_tstamp,remote_id,remote_hash,remote_tstamp
            FROM
                tmp_linked_enries;";
        $this->query($create_link_list);
        $this->query($clear_unlinked_entries);
        $this->query($fill_linked_entries);
    }
    /**
     * Executes needed sync operations
     */
    public function replicate(){
        $this->linkByHash();
        $sql_action_list="
            SELECT
                entry_id,
                local_id,
                remote_id,
                IF( local_id IS NULL, 'localInsert',
                IF( remote_id IS NULL, 'remoteInsert',
                IF( local_deleted=1, 'remoteDelete',
                IF( remote_deleted=1, 'localDelete',
                IF( COALESCE(local_hash,'')<>COALESCE(remote_hash,''),
                    IF( local_tstamp=remote_tstamp, 'remoteInspect',
                    IF( local_tstamp<remote_tstamp, 'localUpdate', 'remoteUpdate')),
                'SKIP'))))) sync_action
            FROM
                plugin_sync_entries doc_pse
            WHERE 
                sync_destination='{$this->doc_config->sync_destination}'
            ";
        $action_list=$this->get_list($sql_action_list);
        //print_r($action_list);
        foreach( $action_list as $action ){
            if( $action->sync_action!='SKIP' && method_exists( $this, $action->sync_action) ){
                $this->{$action->sync_action}($action->local_id,$action->remote_id,$action->entry_id);
            }
        }
        return true;
    }
    protected function remoteCheckout( bool $is_full=false ){
        $sync_destination=$this->doc_config->sync_destination;
        $remote_function=$this->doc_config->remote_function;
        $is_finished=false;
        if( $is_full ){
            $afterDate=null;
        } else {
            $afterDate_local=$this->sync_since;
            if( isset($this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}->checkoutLastFinished) ){
                $afterDate_local=$this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}->checkoutLastFinished;
            }
            $afterDate= $this->toTimezone($afterDate_local, 'remote');
        }        
        $result=$this->remoteCheckoutGetList( $sync_destination, $remote_function, $afterDate );
        $nextPageNo=$result->pageNo+1;
        
        $this->query("START TRANSACTION");
        if( $is_full && $result->pageNo==1 ){
            $this->query("UPDATE plugin_sync_entries SET remote_deleted=1 WHERE sync_destination='$sync_destination'");
        }
        foreach( $result->list as $item ){
            $remote_hash=$this->remoteHashCalculate( $item );
            $sql="INSERT INTO
                    plugin_sync_entries
                SET
                    sync_destination='$sync_destination',
                    remote_id='$item->Id',
                    remote_hash='$remote_hash',
                    remote_deleted=0
                ON DUPLICATE KEY UPDATE
                    remote_hash='$remote_hash',
                    remote_deleted=0
                ";
            $this->query($sql);
        }
        if( $result->pageIsLast ){//last page
            $is_finished=true;
            $nextPageNo=1;
        }
        
        if( !isset($this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}) ){
            $this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}=(object)[];
        }
        $this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}->checkoutPage=$nextPageNo;
        $this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}->checkoutLastFinished=date('Y-m-d H:i:s');
        $this->Hub->MoedeloSync->updateSettings();
        
        $this->query("COMMIT");
        return $is_finished;
    }
    /**
     * 
     * @param string $sync_destination
     * @param string $remote_function
     * @param timestamp $afterDate
     * @return responseobject
     *  
     */
    protected function remoteCheckoutGetList( $sync_destination, $remote_function, $afterDate ){
        $pageNo=1;
        if( isset($this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}->checkoutPage) ){
            $pageNo=$this->Hub->MoedeloSync->plugin_data->{$this->doc_config->sync_destination}->checkoutPage;
        }
        $pageSize=500;
        $request=[
            'pageNo'=>$pageNo,
            'pageSize'=>$pageSize,
            'afterDate'=>$afterDate,
            'beforeDate'=>null,
            'name'=>null
        ];
        $response=$this->apiExecute( $remote_function, 'GET', $request);
        if( $response->httpcode!=200 || 0 ){
            print_r($request);print_r($response);
        }
        $list=[];
        if( isset($response->response->ResourceList) ){
            $list=$response->response->ResourceList;
        }
        return (object) [
            'pageNo'=>$pageNo,
            'pageIsLast'=>count($list)<$pageSize?1:0,
            'list'=>$list
        ];
    }
    public function remoteInsert( $local_id, $remote_id, $entry_id ){
        $entity=$this->localGet( $local_id );
        $response = $this->apiExecute($this->doc_config->remote_function, 'POST', $entity);
        if( $response->httpcode==201 ){
            $remote_hash=$this->remoteHashCalculate($response->response);
            $this->query("UPDATE 
                        plugin_sync_entries
                    SET
                        remote_id='{$response->response->Id}',
                        remote_hash='$remote_hash',
                        remote_tstamp=local_tstamp
                    WHERE
                        entry_id='$entry_id'");
        } else {
            $error=$this->getValidationErrors($response);
            $this->log("{$this->doc_config->sync_destination} INSERT is unsuccessfull (HTTP CODE:$response->httpcode '$error') {$entity->ErrorTitle}");
            return false;
        }
        return true;
    }
    public function remoteUpdate( $local_id, $remote_id, $entry_id ){
        $entity=$this->localGet( $local_id );
        $response = $this->apiExecute($this->doc_config->remote_function, 'PUT', $entity, $remote_id);
        
        
        
        
        print_r($entity);
        
        
        
        if( $response->httpcode==200 ){
            $remote_hash=$this->remoteHashCalculate($entity);
            $this->query("UPDATE 
                        plugin_sync_entries
                    SET
                        remote_hash='$remote_hash',
                        remote_tstamp=local_tstamp
                    WHERE
                        entry_id='$entry_id'");
        } else {
            $error=$this->getValidationErrors($response);
            $this->log("{$this->doc_config->sync_destination} UPDATE is unsuccessfull (HTTP CODE:$response->httpcode '$error') {$entity->ErrorTitle}");
            return false;
        }
        return true;
    }
    public function remoteDelete( $local_id, $remote_id, $entry_id ){
        $response = $this->apiExecute($this->doc_config->remote_function, 'DELETE', null, $remote_id);
        if( $response->httpcode==204 || $response->httpcode==404  ){
            $this->query("DELETE 
                    FROM
                        plugin_sync_entries
                    WHERE
                        entry_id='$entry_id'");
        } else {
            $error=$this->getValidationErrors($response);
            $this->log("{$this->doc_config->sync_destination} DELETE is unsuccessfull (HTTP CODE:$response->httpcode '$error')");
            $entity=$this->remoteGet( $remote_id );
            print_r($entity);
            return false;
        }
        return true;
    }
    public function remoteGet( $remote_id ){
        $response=$this->apiExecute($this->doc_config->remote_function, 'GET', null, $remote_id);
        if( $response->httpcode==200 ){
            return $response->response;
        } else {
            $error=$this->getValidationErrors($response);
            $this->log("{$this->doc_config->sync_destination} GET is unsuccessfull (HTTP CODE:$response->httpcode '$error')");
            return false;
        }
    }
    protected function checkUserPermission( $right ){
        $user_data=$this->Hub->svar('user');
        if( isset($user_data->user_permissions) && strpos($user_data->user_permissions, $right)!==false ){
            return true;
        }
        return false;
    }
}