<?php
function fids_cash_form($admin=false) {
  global $suser;
  $form['#redirect'] = FALSE;
  $form['search'] = array(
			  '#type' => 'fieldset',
			  '#title' => 'Storico',
			  '#collapsible' => true,
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
  $form['search']['details'] = array(
				     '#type' => 'checkbox',
				     '#title' => 'Dettagli',
				     '#description' => 'Dettagli dei movimenti Ordini.',
				     );

  $form['search']['filters'] = array(
				     '#type' => 'fieldset',
				     '#title' => 'Filtri',
				     '#collapsible' => TRUE,
				     '#collapsed' => !($_POST['fid'] || $_POST['vonly'] || $_POST['user']),
				     );

  $form['search']['filters']['vonly'] = array('#type' => 'select',
					      '#title' => 'Tipo di movimento',
					      '#description' => 'Filtra per tipo di movimento.',
					      '#default_value'=>0,
					      '#options' => array(0 => 'Tutto',
								  1 => 'Pagamenti Fornitore',
								  2 => 'Ordini'),
					      );

  $fids=array('Tutti');
  if (!$admin) {
    $fids += get_fids(array_keys($suser->fids));
  } else {
    $fids += get_fids();
  }
  $form['search']['filters']['fid'] = array(
					     '#type' => 'select',
					     '#title' => 'Fornitore',
					     '#description' => 'Filtra per fornitore.',
					     '#options' => $fids,
					     );

  $users=array('Tutti')+get_users();
  $form['search']['filters']['user'] = array('#type' => 'select',
					     '#title' => 'Utente',
					     '#description' => 'Filtra i movimenti Ordini per utente.',
					     '#options' => $users,
					     '#attributes' => array('class' => 'select-filter-users'),
					     );
  
  $form['search']['submit'] = array(
				    '#type' => 'submit',
				    '#value' => 'Cerca',
				    );
  
  $form['fids_cash']['result'] = array('#value' =>_fids_paid_list($admin,$fids,$users));
  
  return $form;
  }


function _fids_paid_list($admin,$fids,$users) {
  global $suser;
  $vonly=(is_numeric($_POST['vonly'])) ? $_POST['vonly'] : 0;
  $where="";
  if ($fid=check_plain($_POST['fid'])) {
    $where=" AND f.fid=".$fid;
    $msg = 'per <em>'.$fids[$fid].'</em>';
  }
  if (!$admin) {
    if ($fid) {
      if (!$suser->fids[$fid]) {
	return "";
      } 
    } else {
      $where=" AND f.fid IN (".implode(",",array_keys($suser->fids)).")";
    }
  }

  if (!$sdate=datevalid($_POST['sdate'])) {
    $sdate=date('Y-m-d', strtotime("-7 days"));
  }
  $msg.=" (dal ".datemysql($sdate,"-","/");
  if ($edate=datevalid($_POST['edate'])) {
    if (saldo_greaterDate($sdate,$edate)) {
      form_set_error('edate','La data di fine periodo non deve essere anteriore a quella di inizio periodo.');
      return "";
    }
    $msg.=" al ".datemysql($edate,"-","/");
  }
  $msg .= ")";
  $headers= array(
		  'Data',
		  'Utente/Fornitore',
		  'Tipo/Fornitore',
		  'Saldo',
		  'Chiuso',
		  );

  if (!$vonly || $vonly == 1) {
    //PAGAMENTI FORNITORI
    $msg = "Pagamenti fornitore ".$msg;
    $query1 = "SELECT fp.fpltime as mydate,NULL as uid,IF(fp.fpnote<>'',concat('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>',f.fnome,'</legend><div class=\'saldo_note\'>',fp.fpnote,'</div></fieldset>'),f.fnome) as unome,'PAGAMENTO FORNITORE',fp.fpsaldo as saldo,1 as mylock";
    $query1.=" FROM ".SALDO_FIDPAGAMENTO." as fp";
    $query1.=" JOIN ".SALDO_FORNITORI." as f on f.fid=fp.fpfid WHERE fp.fpltime >= '".$sdate."'".(($edate) ? " AND fp.fpltime <= '".$edate."'": '').$where;
  }
  if (!$vonly || $vonly == 2) {
    if ($vonly <> 1 && $wuser=check_plain($_POST['user'])) {
      $where.=" AND o.ouid=".$wuser;
      $msg = " di <em>".$users[$wuser]."</em> ".$msg;
    }
    $msg = "Ordini ".$msg;
    //ORDINI
    if ($_POST['details']) {
      //ORDINI DETTAGLIATI
      $query2="SELECT o.odata as mydate,d.uid,u.unome,(SELECT fnome FROM ".SALDO_FORNITORI." WHERE ofid=fid) as unome,-osaldo as saldo,olock as mylock FROM ".SALDO_ORDINI." as o";
      $query2.=" JOIN ".SALDO_FORNITORI." as f on f.fid=o.ofid";
      $query2.=" LEFT JOIN ".SALDO_UTENTI." as u ON u.uid=o.ouid";
      $query2.=" LEFT JOIN users as d on u.email=d.mail WHERE o.odata >= '".$sdate."'".(($edate) ? " AND o.odata <= '".$edate."'" : "").$where;
    } else {
      //ORDINI
      $query2="SELECT o.odata as mydate,NULL as uid,f.fnome as unome,'ORDINE',-SUM(osaldo) as saldo,MIN(olock) as mylock";
      $query2.= " FROM ".SALDO_ORDINI." as o";
      $query2.=" JOIN ".SALDO_FORNITORI." as f on f.fid=o.ofid WHERE o.odata >= '".$sdate."'".(($edate) ? " AND o.odata <= '".$edate."'": '').$where;
      $query2.=" GROUP BY mydate,unome";
    }
  }
  if ($query1 && $query2) {
    $query="(".$query1.") UNION ALL (".$query2.")";
  } else {
    $query = $query1.$query2;
  }
  $query.= " ORDER by mydate DESC,unome";
  $out=saldo_table($query,$headers,array('saldo'=>array('<strong>Saldo movimenti selezionati</strong>')));
  drupal_set_message($msg);
  return $out;
}
