<?php
function rep_cash_form() {
  $form['#redirect'] = FALSE;
  $form['repcash'] = array(
				'#type' => 'fieldset',
				'#title' => 'Riepilogo per Consegna',
				);
  $form['repcash']['date'] = saldo_date_list(FALSE,TRUE);
  $form['repcash']['export'] = array('#type' => 'checkbox',
  	 			       '#title' => 'Esporta',
				       '#description' => 'Esporta i movimenti sottostanti in un file CVS (formato Excel).',
				       );
  $form['repcash']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Riepilogo',
					  );
  $form['result'] = array('#value' =>_rep_cash());
  return $form;
  }

function _rep_cash() {
  $headers=array('Utente');
  $qdate="";
  $tdates=array();
  $sum_row=array("saldo"=>array("<strong>Totale".((empty($tdates))?":":" (".implode(", ",$tdates).")")."</strong>"));
  if (is_array($_REQUEST['date'])) {
    $dates=array();
    $i=0;
    foreach ($_REQUEST['date'] as $udate) {
      if (!$date=datevalid($udate,TRUE)) {
	continue;
      }
      $dates[]=$udate;
      $headers[]=$tdates[]="<em>".$date."</em>";
      $qsum .="ABS(SUM(if(dt='".$udate."',s,0))) as saldo".$i.",";
      $where .= "saldo".$i." <> 0 OR ";
      $sum_row["saldo".$i]=array();
      $i++;
    }
  }
  $headers[]='Saldo Cassa';
  $headers[]=array('data'=>'Stato','class'=>'noprint');
  $qsum.="SUM(s) as saldo";
  if (!$_POST['export']) {
	$qsum.=",IF(SUM(s)<0,0,1) as mylock";
  }
  
  if ($_POST['export']) {
    $query="SELECT unome,replace(saldo,'.',',') FROM (SELECT unome,".$qsum." FROM ((SELECT ouid as u,odata as dt, -osaldo as s FROM ".SALDO_ORDINI.") UNION ALL ( SELECT vuid as u,NULL,vsaldo as s1 FROM ".SALDO_VERSAMENTI.") UNION ALL ( SELECT suid,NULL,IF(stype=0,-ssaldo,ssaldo) as s1 FROM ".SALDO_DEBITO_CREDITO." WHERE stype < 2)) as v LEFT JOIN ".SALDO_UTENTI." on uid=u LEFT JOIN users as d on email=d.mail GROUP BY u ORDER BY unome) as p".(($where) ? " WHERE ". substr($where,0,-4) : "");
	@array_unshift($dates,"Utente");
	$dates[]=date('d-m-Y',time());
	if ( !$data=saldo_exportcsv($query, $dates, array('filename' => 'gas_riepilogo_'.date('d-m-Y',time())))) {
	  drupal_set_message("Errore nell'esportazione del riepilogo","error");
	  return;
    }
  } else {
    $query="SELECT * FROM (SELECT d.uid,unome,".$qsum." FROM ((SELECT ouid as u,odata as dt, -osaldo as s FROM ".SALDO_ORDINI.") UNION ALL ( SELECT vuid as u,NULL,vsaldo as s1 FROM ".SALDO_VERSAMENTI.") UNION ALL ( SELECT suid,NULL,IF(stype=0,-ssaldo,ssaldo) as s1 FROM ".SALDO_DEBITO_CREDITO." WHERE stype < 2)) as v LEFT JOIN ".SALDO_UTENTI." on uid=u LEFT JOIN users as d on email=d.mail GROUP BY u ORDER BY unome) as p".(($where) ? " WHERE ". substr($where,0,-4) : "");
    if (!empty($tdates)) {
       drupal_set_message('Riepilogo utente per consegna del <strong>'.implode(", ",$tdates).'</strong>');
    }
    $out.=saldo_table($query,$headers,$sum_row);
    return $out;
  }
}
?>
