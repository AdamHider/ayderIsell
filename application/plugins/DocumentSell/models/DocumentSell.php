<?php
/* User Level: 1
 * Group Name: Документ
 * Plugin Name: Расходный документ
 * Version: 2017-01-01
 * Description: Документ продажи товара
 * Author: baycik 2017
 * Author URI: isellsoft.com
 * Trigger before: DocumentSell
 * 
 * Description of DocumentSell
 * This class handles all of sell documents
 * @author Baycik
 */
class DocumentSell extends DocumentBase{
    private $errtype='ok';
    private $errmsg='';
    public function index(){
	echo 'hello';
    }
    public $extensionGet=[];
    public function extensionGet(){
	return [
	    'script'=>$this->load->view('sell_script.js',[],true),
	    'head'=>$this->load->view('head.html',[],true),
	    'body'=>$this->load->view('body.html',[],true),
	    'foot'=>$this->load->view('foot.html',[],true),
	    'views'=>$this->load->view('views.html',[],true)
	];
    }
    
    public function documentAdd( $doc_type=null ){
	$doc_type='sell';
	return parent::documentAdd($doc_type);
    }
    
    public $documentDelete=['doc_id'=>'int'];
    public function documentDelete( $doc_id ){
        return parent::documentDelete($doc_id);
    }
    
    public $documentGet=['doc_id'=>'int','parts_to_load'=>'json'];
    public function documentGet($doc_id,$parts_to_load){
	$this->documentSelect($doc_id);
	$doc_type=$this->doc('doc_type');
	if( $doc_type!='sell' && $doc_type!=1 ){
	    $this->Hub->msg("wrong_doc_type");
	    return false;
	}
	$document=[];
	if( in_array("head",$parts_to_load) ){
	    $document["head"]=$this->headGet($doc_id);
	}
	if( in_array("body",$parts_to_load) ){
	    $document["body"]=$this->bodyGet($doc_id);
	}
	if( in_array("foot",$parts_to_load) ){
	    $document["foot"]=$this->footGet($doc_id);
	}
	if( in_array("views",$parts_to_load) ){
	    $document["views"]=$this->viewsGet($doc_id);
	}
	return $document;
    }
    protected function bodyGet($doc_id){
	$this->entriesTmpCreate( $doc_id );
	return $this->get_list("SELECT * FROM tmp_doc_entries");
    }
    private function viewsGet($doc_id){
        $DocumentView = $this->Hub->load_model("DocumentView");
        return $DocumentView->viewListFetch($doc_id);
    }
    /*
     * Entries section 
     */

    private function entryPriceNormalize( $price ){
	$doc_vat_ratio=1+$this->doc('vat_rate')/100;
	$curr_correction=$this->documentCurrencyCorrectionGet();
	return round($price,2)/$doc_vat_ratio/$curr_correction;	
    }
    public $entryAdd=['doc_id'=>'int','product_code'=>'string','product_quantity'=>'int'];
    public function entryAdd($doc_id,$product_code,$product_quantity){
	$this->documentSelect($doc_id);
	$pcomp_id=$this->doc('passive_company_id');
	$doc_ratio=$this->doc('doc_ratio');
	
	$this->db_transaction_start();
	$this->query("INSERT INTO document_entries SET doc_id=$doc_id,product_code='$product_code',invoice_price=COALESCE(GET_PRICE('$product_code',$pcomp_id,'$doc_ratio'),0)",false);
	$error = $this->db->error();
	if($error['code']==1452){
	    $this->Hub->msg("product_code_unknown");
	    return false;
	} else 
	if($error['code']==1062){
	    $this->Hub->msg("already_exists");
	    return false;
	} else 
	if($error['code']!=0){
	    header("X-isell-type:error");
	    show_error($error['message'].' '.$this->db->last_query(), 500);
	}
	$doc_entry_id=$this->db->insert_id();
	$update_ok=$this->entryUpdate($doc_id,$doc_entry_id,'product_quantity',$product_quantity);
	
	if( !$update_ok ){
	    return false;
	}
	$this->db_transaction_commit();
    }
    public $entryUpdate=['doc_id'=>'int','doc_entry_id'=>'int','field'=>'(product_price_total|product_quantity|product_sum_total|party_label)','value'=>'string'];
    public function entryUpdate($doc_id,$doc_entry_id,$field,$value){
	$this->documentSelect($doc_id);
	$entry_updated=[];
	$this->db_transaction_start();	
	if( $field=='product_sum_total' ){
	    $entry_data=$this->entryGet($doc_entry_id);
	    $product_price_vatless=$this->entryPriceNormalize($value);
	    $entry_updated['invoice_price']=$product_price_vatless/$entry_data->product_quantity;	    
	} else
	if( $field=='product_price_total' ){
	    $product_price_vatless=$this->entryPriceNormalize($value);
	    $entry_updated['invoice_price']=$product_price_vatless;
	} else
	if( $field=='party_label' ){
	    $entry_updated['party_label']=$value;
	} else
	if( $field=='product_quantity' ){//IF document is already commited then commit entry. If commit is failed then abort update
	    if( $value<=0 ){//quantity must be more than zero
		$this->Hub->msg('quantity_wrong');
		return false;
	    }
	    if( $this->doc('is_commited') ){
		$commit_ok=$this->entryCommit($doc_entry_id,$value);
		if( !$commit_ok){
		    return false;
		}
	    } else {
		$entry_updated['self_price']=0;
		$entry_updated['party_label']='';		
	    }
	    //$this->Hub->msg("entry_calculated $entry_calculated");
	    $entry_updated['product_quantity']=$value;
	}
	$update_ok=$this->update("document_entries",$entry_updated,['doc_entry_id'=>$doc_entry_id]);
	if( !$update_ok ){
	    return false;
	}
	$this->db_transaction_commit();
	return true;
    }
    public $entryDelete=['doc_id'=>'int','doc_entry_ids'=>'json'];
    public function entryDelete($doc_id,$doc_entry_ids){
	return parent::entryDelete($doc_id, $doc_entry_ids);
    }    
    /*
     * COMMIT SECTION
     */
    private function stockLeftoverGet($product_code){
	return $this->get_value("SELECT product_quantity FROM stock_entries WHERE product_code='$product_code'");
    }
    private function stockLeftoverSet($product_code,$leftover,$self_price,$party_label){
	return $this->update('stock_entries',['product_quantity'=>$leftover,'self_price'=>$self_price,'party_label'=>$party_label],['product_code'=>$product_code]);
    }
    private function entryGet($doc_entry_id){
	$sql="SELECT * FROM document_entries WHERE doc_entry_id='$doc_entry_id'";
	return $this->get_row($sql);
    }
    protected function entryUncommit($doc_entry_id){
	return $this->entryCommit($doc_entry_id, 0);
    }
    protected function entryCommit($doc_entry_id,$new_product_quantity=NULL){
	$this->documentSetLevel(2);
	$entry_data=$this->entryGet($doc_entry_id);
	if( !$entry_data ){
	    $this->Hub->msg("entry_deleted_before");
	    return false;	    
	}
	$stock_lefover=$this->stockLeftoverGet($entry_data->product_code);
	$substract_quantity=$entry_data->product_quantity;
	if( $new_product_quantity!==NULL ){
	    $substract_quantity=$new_product_quantity;
	    $stock_lefover=$stock_lefover+$entry_data->product_quantity;
	}
	if( $substract_quantity>$stock_lefover ){
	    $this->Hub->msg("not_enough");
	    $this->Hub->msg($substract_quantity-$stock_lefover);
	    return false;
	}
	$this->entryOriginsFind($entry_data->product_code,$stock_lefover);
	$entry_calculated=$this->entryOriginsCalc($substract_quantity);
	$this->update("document_entries",$entry_calculated,['doc_entry_id'=>$doc_entry_id]);
	
	$new_leftover=$stock_lefover-$substract_quantity;
	$new_leftover_calculated=$this->entryOriginsCalc($new_leftover,'DESC');
	$this->stockLeftoverSet($entry_data->product_code,$new_leftover,$new_leftover_calculated->self_price,$new_leftover_calculated->party_label);
	return true;
    }
    /*
     * Find entries from buy documents wich are original (correspond) to commited sell entry. 
     * Orders by date entries from newest to oldest
     */
    private function entryOriginsFind($product_code,$stock_leftover){
        //FROGOT ABOUT FINAL DATE OF DOCUMENT UP TO THAT ENTRIES MUST BE SEARCHED???
        
	$this->query("SET @total_buyed:=0,@pcode='$product_code',@stock_leftover:='$stock_leftover';");
	$this->query("DROP TEMPORARY TABLE IF EXISTS tmp_original_entries;");#TEMPORARY
	$this->query("CREATE TEMPORARY TABLE tmp_original_entries AS 
			SELECT 
			    *,
			    LEAST(@stock_leftover - @total_buyed,product_quantity) party_quantity,
			    @total_buyed:=@total_buyed + product_quantity tb
			FROM
			    (SELECT 
				cstamp,
				party_label,
				self_price,
				product_quantity
			    FROM
				document_entries
				    JOIN
				document_list USING (doc_id)
			    WHERE
				product_code = @pcode
				    AND (doc_type = '2' OR doc_type = 'buy')
				    AND is_reclamation = 0
				    AND is_commited = 1
                                    AND notcount=0
			    ORDER BY cstamp DESC) t
			WHERE
			    @total_buyed <= @stock_leftover");
    }
    /*
     * Finds party_label from buy document origin entries and calculated avg self price.
     * Before orders entries from oldest to newest
     */
    private function entryOriginsCalc($product_quantity,$sort_order='ASC'){
	$this->query("SET @sold_quantity:=$product_quantity,@total_sold:=0,@first_party_label:='';");
	$sql="SELECT 
		@first_party_label party_label, ROUND(SUM(self_sum) / @sold_quantity,2) self_price
	    FROM
		(SELECT 
		    LEAST(@sold_quantity - @total_sold, party_quantity) * self_price self_sum,
			@total_sold:=@total_sold + party_quantity ts,
			@first_party_label:=IF(@first_party_label<>'', @first_party_label, party_label) first_party_label
		FROM
		    (SELECT 
		    *
		FROM
		    tmp_original_entries
		ORDER BY cstamp $sort_order) t
		WHERE
		    @sold_quantity > @total_sold) t2;";
	return $this->get_row($sql);
    }
    protected function getProductSellSelfPrice($product_code, $invoice_qty,$fdate) {
        return $this->Base->get_row("SELECT LEFTOVER_CALC('$product_code','$fdate','$invoice_qty','all')",0);

//	$this->Base->LoadClass('StockOld');
//	$stock_self = $this->Base->StockOld->getEntrySelfPrice($product_code);
//	if ($stock_self > 0)
//	    return $stock_self;
//	/*
//	 * IF self price is not set
//	 * qty=0 or something else set
//	 * selfPrice as current buy price
//	 */
//	$price = $this->getRawProductPrice($product_code, $this->doc('doc_ratio'));
//	$price_self = $price['buy'] ? $price['buy'] : $price['sell'];
//	//$this->Base->StockOld->setEntrySelfPrice($product_code, $price_self);
//	return $price_self;
    }

}