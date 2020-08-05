<?php

    function getAll( $comp ) {
        $all ="$comp->company_name";
        $all.=$comp->company_tax_id?", ИНН/КПП:{$comp->company_tax_id}/{$comp->company_tax_id2}":'';
        $all.=$comp->company_jaddress?", $comp->company_jaddress":'';
        $all.=$comp->company_phone?", тел.:{$comp->company_phone}":'';
        return $all;
    }
    
    
    if( isset($this->view->doc_view->extra->reciever_company_id) ){
        $this->Hub->load_model("Company");
        $this->view->reciever=$this->Hub->Company->companyGet($this->view->doc_view->extra->reciever_company_id);
    } else {
        $this->view->reciever=$this->view->buyer;
    }
    if( isset($this->view->doc_view->extra->supplier_company_id) ){
        $this->Hub->load_model("Company");
        $this->view->supplier=$this->Hub->Company->companyGet($this->view->doc_view->extra->supplier_company_id);
    } else {
        $this->view->supplier=$this->view->seller;
    }
    
    $this->view->seller->all=getAll($this->view->seller);
    $this->view->buyer->all=getAll($this->view->buyer);
    $this->view->supplier->all=getAll($this->view->supplier);
    $this->view->reciever->all=getAll($this->view->reciever);
    
    if( isset($this->view->doc_view->extra->reason_date) ){
        $this->view->doc_view->extra->reason_date=todmy( $this->view->doc_view->extra->reason_date );
    }
    if( isset($this->view->doc_view->extra->product_transport_bill_date) ){
        $this->view->doc_view->extra->product_transport_bill_date=todmy( $this->view->doc_view->extra->product_transport_bill_date );
    }
    if( isset($this->view->doc_view->extra->delivery_date) ){
        $this->view->doc_view->extra->delivery_date=todmy( $this->view->doc_view->extra->delivery_date );
    }
    
    function todmy( $iso ){
       $ymd= explode('-', $iso);
       return "$ymd[2].$ymd[1].$ymd[0]";
   }






$okei = [
    'шт' => '796',
    'руб'=>'383',
    '1000 руб'=>'384',
    'компл'=>'839',
    'л'=>'112',
    'усл. ед'=>'876',
    'кг'=>'166',
    'т'=>'168',
    'ч'=>'356',
    'м' => '006',
    'м2'=>'055',
    'пог. м'=>'018',
    'упак'=>'778'
];
$this->view->common_rows = [];
if( $this->out_ext=='.doc' ){
    $this->landscape_orientation=true;
    $this->view->tables = [$this->view->rows];
    $this->view->tables_count = 1;
} else {
    $this->view->tables = [array_splice($this->view->rows, 0, 5)];
    $this->view->tables = array_merge($this->view->tables, array_chunk($this->view->rows, 19));
    $this->view->tables_count = count($this->view->tables);
}

$this->view->footer->total_qty=0;
$this->view->footer->vatless=0;
$this->view->footer->vat=0;
$vat_percent=0.18;
$i = 0;
foreach ($this->view->tables as &$table) {
    $subcount = 0;
    $subvatless = 0;
    $subvat = 0;
    $subtotal = 0;
    foreach ($table as &$row) {
        $row->i = ++$i;
        $row->product_sum_vat = $row->product_sum_total-$row->product_sum_vatless;
        $row->product_unit_code = $okei[$row->product_unit];
        $subcount+=$row->product_quantity;
        $subvatless+=$row->product_sum_vatless;
        $subvat+=$row->product_sum_vat;
        $subtotal+=$row->product_sum_total;
        $this->view->common_rows[] = $row;
    }
    $table['subcount'] = $subcount;
    $table['subvatless'] = format($subvatless);
    $table['subvat'] = format($subvat);
    $table['subtotal'] = format($subtotal);
    $this->view->footer->total_qty+=$subcount;
    $this->view->footer->vatless+=$subvatless;
    $this->view->footer->vat+=$subvat;
}

$this->view->total_pages = num2str($this->view->tables_count + 1, true);
$this->view->total_rows = num2str($i, true);
$this->view->doc_view->total_spell = num2str($this->view->footer->total);
$this->view->doc_view->total_coins = explode('.', $this->view->footer->total)[1];
$this->view->doc_view->date_spell = daterus($this->view->doc_view->date_dot);


$this->setPageOrientation( "landscape" );

if( $this->view->doc_view->extra->goods_reciever_okpo ){
    $this->view->goods_reciever_okpo=$this->view->doc_view->extra->goods_reciever_okpo;
}
if( $this->view->doc_view->extra->goods_reciever ){
    $this->view->goods_reciever=$this->view->doc_view->extra->goods_reciever;
}


$this->view->doc_view->tstamp = dateexplode($this->view->doc_view->tstamp);













function format($num){
    return number_format($num, 2,'.','');
}


function daterus($dmy) {
    $dmy = explode('.', $dmy);
    $months = array('ноября', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
    return ' <' . $dmy[0] . '> ' . $months[$dmy[1] * 1] . ' ' . $dmy[2] . ' года';
}

function dateexplode($dmy){
    $date_exploded = explode('-', explode(' ', $dmy)[0]);
    $result = new stdClass();
    $result->day = $date_exploded[2];
    $result->month = $date_exploded[1];
    $result->year = $date_exploded[0];
    return $result;
}

/**
 * Возвращает сумму прописью
 * @author runcore
 * @uses morph(...)
 */
function num2str($num, $only_number = false) {
    $nul = 'ноль';
    $ten = array(
	array('', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
	array('', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
    );
    $a20 = array('десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать');
    $tens = array(2 => 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто');
    $hundred = array('', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот');
    $unit = array(// Units
	array('копейка', 'копейки', 'копеек', 1),
	array('рубль', 'рубля', 'рублей', 0),
	array('тысяча', 'тысячи', 'тысяч', 1),
	array('миллион', 'миллиона', 'миллионов', 0),
	array('миллиард', 'милиарда', 'миллиардов', 0),
    );
    //
    list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($num)));
    $out = array();
    if (intval($rub) > 0) {
	foreach (str_split($rub, 3) as $uk => $v) { // by 3 symbols
	    if (!intval($v))
		continue;
	    $uk = sizeof($unit) - $uk - 1; // unit key
	    $gender = $unit[$uk][3];
	    list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
	    // mega-logic
	    $out[] = $hundred[$i1]; # 1xx-9xx
	    if ($i2 > 1)
		$out[] = $tens[$i2] . ' ' . $ten[$gender][$i3];# 20-99
	    else
		$out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3];# 10-19 | 1-9
	    // units without rub & kop
	    if ($uk > 1)
		$out[] = morph($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
	} //foreach
    } else {
	$out[] = $nul;
    }
    if ($only_number) {
	return join(' ', $out);
    }
    $out[] = morph(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
    return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
}

/**
 * Склоняем словоформу
 * @ author runcore
 */
function morph($n, $f1, $f2, $f5) {
    $n = abs(intval($n)) % 100;
    if ($n > 10 && $n < 20)
	return $f5;
    $n = $n % 10;
    if ($n > 1 && $n < 5)
	return $f2;
    if ($n == 1)
	return $f1;
    return $f5;
}