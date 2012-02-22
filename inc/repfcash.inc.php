<?php
function rep_fcash_form(&$form_state, $ref=false) {
  $form['#redirect'] = FALSE;
  $form['repcash'] = array(
				'#type' => 'fieldset',
				'#title' => 'Riepilogo per Consegna',
				);
  $form['repcash']['date'] = saldo_date_list();
  $form['repcash']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Riepilogo',
					  );
  $form['result'] = array('#value' =>_rep_fcash($ref));
  return $form;
  }

function _rep_fcash($ref) {
  global $suser,$saldo_req;
 
  $headers=array('Fornitore','Debito/Credito (in Euro)',array('data'=>'Stato','class'=>'noprint'));
  $qsum="SUM(s) as saldo ,IF(SUM(s)<0,0,1) as mylock ";
  $qdate="";
  if ($ref) {
    if (count($suser->fids)==0) return "";
    $where="WHERE f in (".implode(",",array_keys($suser->fids)).") ";
  }
  if ($date=datemysql($_REQUEST['date'])) {
    $cdate=datemysql($_REQUEST['date'],"-","/");
    $headers=array('Fornitore','Spesa consegna ('.$cdate.')','Saldo Cassa(in Euro)',array('data'=>'Stato','class'=>'noprint'));
    $qsum="sumf.fsaldo,".$qsum;
    $qdate="INNER JOIN (SELECT SUM(osaldo) as fsaldo,ofid FROM ".SALDO_ORDINI." where odata='".$date."' GROUP BY ofid) as sumf ON sumf.ofid=f ";
    drupal_set_message('Riepilogo fornitori per consegna del <strong>'.$cdate.'</strong>');
  }
  $query="SELECT fnome,".$qsum."FROM ((SELECT ofid as f,-osaldo as s FROM ".SALDO_ORDINI.") UNION ALL ( SELECT fpfid as f,fpsaldo as s FROM ".SALDO_FIDPAGAMENTO.")) as v LEFT JOIN ".SALDO_FORNITORI." on fid=f ".$qdate.$where."GROUP BY f ORDER BY fnome;";
  $out.=saldo_table($query,$headers,array("fsaldo"=>array(),"saldo"=>array("<strong>Totale</strong>")));
  return $out;
}
?>
