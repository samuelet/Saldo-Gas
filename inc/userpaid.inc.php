<?php
function user_paid_form() {
  $form['#redirect'] = FALSE;
  $form['explain']=array(
			 '#value'=>'<p>Questa &egrave il tuo saldo attuale. Puoi inoltre visualizzare lo <strong><em>storico</em></strong> dettagliato dei tuoi movimenti utilizzando i filtri sottostanti.</p>'
			 );
  $form['search'] = array(
				'#type' => 'fieldset',
				'#title' => 'Filtri',
				'#collapsible' => TRUE,
				'#collapsed' => !($_POST['sdate'] || $_POST['edate'] || $_POST['fid'] || $_POST['vonly'] || $_POST['details']), 
				);
  $form['search']['sdate'] = array(
				   '#type' => 'textfield',
				   '#title' => 'Data Inizio',
				   '#attributes' => array('class' => 'jscalendar'),
				   '#jscalendar_ifFormat' => '%d/%m/%Y',
				   '#jscalendar_showsTime' => 'false',
				   '#size' => 10,
				   '#maxlength' => 10,
				   '#prefix' => '<span class="container-inline">',
				   '#suffix' => '<div>/</div>',
				   );
  $form['search']['edate'] = array(
				   '#type' => 'textfield',
				   '#title' => 'Data Fine',
				   '#attributes' => array('class' => 'jscalendar'),
				   '#jscalendar_ifFormat' => '%d/%m/%Y',
				   '#jscalendar_showsTime' => 'false',
				   '#size' => 10,
				   '#maxlength' => 10,
				   '#suffix' => '</span>',
				   );

  $form['search']['date_desc'] = array('#value' => '<div class="description">Inserire la data iniziale e facoltativamente quella finale del periodo cui si vuole  visualizzare i movimenti. Formato gg/mm/aaaa</div>');

  $fids = array('Tutti') + get_fids();
  $form['search']['filters']['vonly'] = array('#type' => 'select',
					      '#title' => 'Tipo di movimento',
					      '#description' => 'Filtra per tipo di movimento.',
					      '#default_value'=>0,
					      '#options' => array(0 => 'Tutto',
								  1 => 'Ordini',
								  2 => 'Versamenti',
								  3 => 'Debiti/Crediti'),
					      );

  $form['search']['filters']['fid'] = array(
					     '#type' => 'select',
					     '#title' => 'Fornitore',
					     '#description' => 'Filtra ordini per fornitore.',
					     '#options' => $fids,
					     );
  
  $form['search']['details'] = array(
					'#type' => 'checkbox',
					'#title' => 'Dettagli',
					);
  $form['search']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Cerca',
					  );
  $form['user_paid']['result'] = array('#value' =>_paid_list($fids));
  
  return $form;
  }

function _paid_list($fids) {
  global $suser;
  $ulist=implode(",",array_keys($suser->uid));
  $vonly=(is_numeric($_POST['vonly'])) ? $_POST['vonly'] : 0;
  $msg="Movimenti di cassa";
  $headers= array(
		  'Data',
		  'Tipo',
		  'Saldo',
		  );
  $where="";
  if ($fid=check_plain($_POST['fid'])) {
    $where=" AND ofid=".$fid;
    $msg .= " (fornitore ".$fids[$fid].")";
  }
  if (!$sdate=datevalid($_POST['sdate'])) {
    $sdate=date('Y-m-d', strtotime("-7 days"));
  }
  $msg.=" dal ".datemysql($sdate,"-","/");
  if ($edate=datevalid($_POST['edate'])) {
    if (saldo_greaterDate($sdate,$edate)) {
      form_set_error('edate','La data di fine periodo non deve essere anteriore a quella di inizio periodo.');
      return "";
    }
    $msg.=" al ".datemysql($edate,"-","/");
  }

  if ($_POST['details']) {
    $aquery[] = "(SELECT odata as mydate,(SELECT fnome FROM ".SALDO_FORNITORI." WHERE ofid=fid),-osaldo as saldo,olock as mylock FROM ".SALDO_ORDINI." WHERE odata >= '".$sdate."'".(($edate) ? " AND odata <= '".$edate."'": '')." AND ouid IN (".$ulist.")".$where.")";
    $aquery[] = "(SELECT ltime as mydate,'VERSAMENTO',vsaldo as saldo,1 as mylock FROM ".SALDO_VERSAMENTI." WHERE ltime >= '".$sdate."'".(($edate) ? " AND ltime <= '".$edate."'": '')." AND vuid IN (".$ulist."))";
    $aquery[] = "(SELECT sltime as mydate,concat('<fieldset class=\'collapsible collapsed\'><legend>',IF(stype=0,'DEBITO','CREDITO'),' UTENTE</legend><div class=\'saldo_note\'>',snote,'</div></fieldset>'),IF(stype=0,-ssaldo,ssaldo) as saldo,1 as mylock FROM ".SALDO_DEBITO_CREDITO." WHERE sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '')." AND suid IN (".$ulist."))";
  } else {
    $aquery[] = "(SELECT odata as mydate,'ORDINE',-SUM(osaldo) as saldo,MIN(olock) as mylock FROM ".SALDO_ORDINI." WHERE odata >= '".$sdate."'".(($edate) ? " AND odata <= '".$edate."'": '')." AND ouid in (".$ulist.")".$where." GROUP BY mydate)";
    $aquery[] = "(SELECT ltime as mydate,'VERSAMENTO',SUM(vsaldo) as saldo,1 as mylock FROM ".SALDO_VERSAMENTI." WHERE ltime >= '".$sdate."'".(($edate) ? " AND ltime <= '".$edate."'": '')." AND vuid IN (".$ulist.") GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d'))";
    $aquery[] = "(SELECT sltime as mydate,'DEBITO UTENTE',-SUM(ssaldo) as saldo,1 as mylock FROM ".SALDO_DEBITO_CREDITO." WHERE sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '')." AND stype=0 AND suid IN (".$ulist.") GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d')) UNION ALL (SELECT sltime as mydate,'CREDITO UTENTE',SUM(ssaldo) as saldo,1 as mylock FROM ".SALDO_DEBITO_CREDITO." WHERE sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '')." AND stype=1 AND suid IN (".$ulist.") GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d'))";
  }
  if ($vonly) {
    $query = $aquery[$vonly-1];
  } else {
    $query = implode(" UNION ALL ",$aquery);
  }
  $query .=" ORDER by mydate DESC;";
  $headers= array(
		  'Data',
		  'Tipo',
		  'Saldo',
		  array('data'=>'Chiuso','class'=>'noprint'),
		  );
  $out = drupal_render($form);
  $out .= saldo_table($query,$headers,array('saldo'=>array('<strong>Saldo movimenti selezionati</strong>')));
  drupal_set_message($msg);
  
  $query="SELECT SUM(t) FROM ((SELECT -IFNULL(SUM(osaldo),0) as t FROM ".SALDO_ORDINI." WHERE ouid IN (".$ulist.")) ";
  $query .="UNION ALL ( SELECT IFNULL(SUM(vsaldo),0) as t FROM ".SALDO_VERSAMENTI." WHERE vuid IN (".$ulist.")) ";
  $query .="UNION ALL ( SELECT -IFNULL(SUM(ssaldo),0) as t FROM ".SALDO_DEBITO_CREDITO." WHERE stype=0 AND suid IN (".$ulist.")) ";
  $query .="UNION ALL ( SELECT IFNULL(SUM(ssaldo),0) as t FROM ".SALDO_DEBITO_CREDITO." WHERE stype=1 AND suid IN (".$ulist.")) ";
  $query .=") as v";
  $headers=array('<em>Situazione Saldo Totale (in Euro)</em>');
  $out.=saldo_table($query,$headers);
  return $out;
}
