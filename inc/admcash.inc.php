<?php
function admin_cash_form($admin) {
  $type=check_plain($_GET['type']);
  $ok=check_plain($_POST['ok']);
  $form['#redirect'] = FALSE;
  if (!$admin || empty($type) || $ok) {
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
    
    $form['search']['details'] = array('#type' => 'checkbox',
				       '#title' => 'Dettagli',
				       '#description' => 'Visualizza i dettagli dei movimenti'.(($admin) ? '.' : ', tranne quelli che riguardano gli utenti.'),
				       );
    $form['search']['filters'] = array(
				       '#type' => 'fieldset',
				       '#title' => 'Filtri',
				       '#collapsible' => TRUE,
				       '#collapsed' => !($_POST['user'] || $_POST['vonly']), 
				       );
    $form['search']['filters']['vonly'] = array(
						'#type' => 'select',
						'#title' => 'Tipo di movimento',
						'#description' => 'Filtra per tipo di movimento.',
						'#default_value'=>0,
						'#options' => array(0 => 'Tutto',
								    1 => 'Versamenti utente',
								    2 => 'Debiti utente',
								    3 => 'Crediti utente',
								    4 => 'Pagamenti Fornitore',
								    5 => 'Spese Gas',
								    6 => 'Entrate Gas',
								    7 => 'Ordini'),
						);
    if ($admin) {
      $users=array('Tutti')+get_users();
      $form['search']['filters']['user'] = array('#type' => 'select',
						 '#title' => 'Utente',
						 '#description' => 'Filtra per utente.',
						 '#options' => $users,
						 '#attributes' => array('class' => 'select-filter-users'),
						 );
    }
    $form['search']['submit'] = array(
				      '#type' => 'submit',
				      '#value' => 'Cerca',
				      );

    $form['admin_cash']['result'] = array('#value' =>_paid_list($admin,$users));
  } else {
    $date=(isset($_GET['date']) && is_numeric($_GET['date'])) ? $_GET['date'] : false;
    $id=(isset($_GET['id']) && is_numeric($_GET['id'])) ? $_GET['id'] : false;
    $fid=(isset($_GET['fid']) && is_numeric($_GET['fid'])) ? $_GET['fid'] : false;
    if (!$list=admin_cash_list($type,$date,$id,$fid)) {
      drupal_goto($_GET['q'],'act=admcash');
    }
    $form['delete'] = array(
			    '#type' => 'fieldset',
			    '#title' => 'Eliminazione movimenti',
			    );
    $form['delete']['msg']=array (
				  '#value' => 'Vuoi eliminare definitivamente i movimenti di cassa nella tabella sottostante?<div class="messages error">L\'operazione &egrave <strong>IRREVERSIBILE!.</strong></div>'
				  );
    $form['delete']['ok'] = array(
				  '#type' => 'hidden',
				  '#value' => '1',
				  );
    $form['delete']['type'] = array(
				    '#type' => 'hidden',
				    '#value' => $type,
				    );
    $form['delete']['date'] = array(
				    '#type' => 'hidden',
				    '#value' => $date,
				    );
    $form['delete']['id'] = array(
				  '#type' => 'hidden',
				  '#value' => $id,
				  );
    $form['delete']['fid'] = array(
				   '#type' => 'hidden',
				   '#value' => $fid,
				   );

    $form['delete']['submit'] = array(
				      '#type' => 'submit',
				      '#value' => 'Elimina',
				      );
    $form['delete']['cancel'] = array(
				      '#type' => 'button',
				      '#value' => 'Annulla',
				      );

    $form['delete']['list']=array (
				   '#value' => $list
				   );

  }
  return $form;
  }

function _paid_list($admin,$users) {
  $msg="Movimenti di cassa ";
  if ($wuser=check_plain($_POST['user'])) {    
    $msg .= "dell' utente <em>".$users[$wuser]."</em> ";
  }

  if (!$sdate=datevalid($_POST['sdate'])) {
    $sdate=date('Y-m-d', strtotime("-7 days"));
  }
  $msg.="dal ".datemysql($sdate,"-","/");
  if ($edate=datevalid($_POST['edate'])) {
    if (saldo_greaterDate($sdate,$edate)) {
      form_set_error('edate','La data di fine periodo non deve essere anteriore a quella di inizio periodo.');
      return "";
    }
    $msg.=" al ".datemysql($edate,"-","/");
  }

  if ($admin && $_POST['op'] == 'Elimina' && $_POST['ok']) {
    if (!$out=admin_cash_list($_POST['type'],$_POST['date'],$_POST['id'],$_POST['fid'],true)) {
      drupal_set_message('Si &egrave verificato un errore!. Riprova','error');
    } else {
      drupal_set_message($out);
    }
    drupal_goto($_GET['q'],'act=admcash');
  } elseif ($_POST['op'] == 'Annulla'){
    drupal_set_message('Azione annullata.');
    drupal_goto($_GET['q'],'act=admcash');
  } else {
    $vonly=(is_numeric($_POST['vonly'])) ? $_POST['vonly'] : 0;
    $headers= array(
		    'Data',
		    'Utente/Fornitore',
		    'Tipo',
		    'Saldo',
		    array('data'=>'Chiuso','class'=>'noprint'),
		    );
    if ($admin) {
      $headers[]='Operazioni';
    }
    if ($_POST['details']) {
      switch ($vonly) {
	//TUTTO
      case 0:
	//VERSAMENTI
      case 1:
	if ($admin) {
	  $query1="SELECT v.ltime as mydate,d.uid,u.unome,'VERSAMENTO',v.vsaldo as saldo,1 as mylock";
	  $query1.=",CONCAT('Elimina%act=admcash&type=v&id=',vid) as myextra";
	  $query1.=" FROM ".SALDO_VERSAMENTI." as v LEFT JOIN ".SALDO_UTENTI." as u ON u.uid=v.vuid LEFT JOIN users as d on u.email=d.mail WHERE v.ltime >= '".$sdate."'".(($edate) ? " AND v.ltime <= '".$edate."'": '');
	  if ($wuser) {
	    $query1.=" AND v.vuid=".$wuser;
	  }
	  
	  if ($vonly =="1") {
	    $query.=$query1." ORDER by mydate DESC";
	    break;
	  } else {
	    $query="(".$query1.") UNION ALL";
	  }
	}
	//DEBITI
      case 2:
	if ($admin) {
	  $query2 = "SELECT sltime as mydate,NULL as uid,IF(snote<>'',CONCAT('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>',(SELECT unome FROM ".SALDO_UTENTI." WHERE uid=suid),'</legend><div class=\'saldo_note\'>',snote,'</div></fieldset>'),(SELECT unome FROM ".SALDO_UTENTI." WHERE uid=suid)),'DEBITO UTENTE',-ssaldo as saldo,1 as mylock";
	  $query2.=",CONCAT('Elimina%act=admcash&type=d&id=',sid) as myextra";
	  $query2.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=0 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	  if ($wuser) {
	    $query2.=" AND suid=".$wuser;
	  }
	  if ($vonly =="2") {
	    $query.=$query2." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query2.") UNION ALL";
	  }
	}
	//CREDITI
      case 3:
	if ($admin) {
	  $query3 = "SELECT sltime as mydate,NULL as uid,IF(snote<>'',CONCAT('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>',(SELECT unome FROM ".SALDO_UTENTI." WHERE uid=suid),'</legend><div class=\'saldo_note\'>',snote,'</div></fieldset>'),(SELECT unome FROM ".SALDO_UTENTI." WHERE uid=suid)),'CREDITO UTENTE',ssaldo as saldo,1 as mylock";
	  $query3.=",CONCAT('Elimina%act=admcash&type=c&id=',sid) as myextra";
	  $query3.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=1 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	  if ($wuser) {
	    $query3.=" AND suid=".$wuser;
	  }
	  if ($vonly =="3") {
	    $query.=$query3." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query3.") ";
	    if (!$wuser) $query .= "UNION ALL ";
	  }
	}
	//PAGAMENTI FORNITORI
      case 4:
	if (!$wuser) {
	  $query4 = "SELECT fpltime as mydate,NULL as uid,IF(fpnote<>'',CONCAT('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>',(SELECT fnome FROM ".SALDO_FORNITORI." WHERE fid=fpfid),'</legend><div class=\'saldo_note\'>',fpnote,'</div></fieldset>'),(SELECT fnome FROM ".SALDO_FORNITORI." WHERE fid=fpfid)),'PAGAMENTO FORNITORE',-fpsaldo as saldo,1 as mylock";
	  if ($admin) {
	    $query4.=",CONCAT('Elimina%act=admcash&type=p&id=',fpid) as myextra";
	  }
	  $query4.=" FROM ".SALDO_FIDPAGAMENTO." WHERE fpltime >= '".$sdate."'".(($edate) ? " AND fpltime <= '".$edate."'": '');
	  if ($vonly =="4") {
	    $query.=$query4." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query4.") UNION ALL";
	  }
	} else if ($vonly=="4") {
	  $query ="SELECT NULL,NULL,NULL";
	  break;
	}
	//SPESE
      case 5:
	if (!$wuser) {
	  $query5 = "SELECT sltime as mydate,NULL as uid,concat('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>Note</legend><div class=\'saldo_note\'>',snote,'</div></fieldset>'),'SPESA GAS',-ssaldo as saldo,1 as mylock";
	  if ($admin) {
	    $query5.=",CONCAT('Elimina%act=admcash&type=s&id=',sid) as myextra";
	  }
	  $query5.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=2 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	  if ($vonly =="5") {
	    $query.=$query5." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query5.") UNION ALL";
	  }
	} else if ($vonly=="5") {
	  $query ="SELECT NULL,NULL,NULL";
	  break;
	}
	//ENTRATE
      case 6:
	if (!$wuser) {
	  $query6 = "SELECT sltime as mydate,NULL as uid,concat('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>Note</legend><div class=\'saldo_note\'>',snote,'</div></fieldset>'),'ENTRATA GAS',ssaldo as saldo,1 as mylock";
	  if ($admin) {
	    $query6.=",CONCAT('Elimina%act=admcash&type=e&id=',sid) as myextra";
	  }
	  $query6.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=3 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	  if ($vonly =="6") {
	    $query.=$query6." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query6.") ";
	  }
	} else if ($vonly=="6") {
	  $query ="SELECT NULL,NULL,NULL";
	  break;
	}
	//ORDINI
      default:
	$query7="SELECT odata as mydate,NULL as uid,(SELECT fnome FROM ".SALDO_FORNITORI." WHERE ofid=fid) as unome,'ORDINE',-SUM(osaldo) as saldo,MIN(olock) as mylock";
	if ($admin) {
	  $query7.=",IF(MAX(ovalid)=1,'',CONCAT('Elimina%act=admcash&type=o&date=',UNIX_TIMESTAMP(odata)".(($wuser) ? ",'&id=',ouid" : "").",'&fid=',ofid)) as myextra";
	}
	$query7.=" FROM ".SALDO_ORDINI." WHERE odata >= '".$sdate."'".(($edate) ? " AND odata <= '".$edate."'": '');
	if ($wuser) {
	  $query7.=" AND ouid=".$wuser;
	}
	$query7.=" GROUP BY mydate,unome";
	if ($vonly >0) {
	  $query=$query7." ORDER by mydate DESC,unome";
	  break;
	} else {
	  $query="(".$query7.") UNION ALL ".$query." ORDER by mydate DESC,unome";
	}
      }
    } else {
      unset($headers[1]);
      switch ($vonly) {
      case 0:
      case 1:
	$query1="SELECT ltime as mydate,'VERSAMENTO',SUM(vsaldo) as saldo,1 as mylock";
	if ($admin) {
	  $query1.=",CONCAT('Elimina%act=admcash&type=v&date=',UNIX_TIMESTAMP(ltime)".(($wuser) ? ",'&id=',vuid" : "").") as myextra";
	}
	$query1.=" FROM ".SALDO_VERSAMENTI." WHERE ltime >= '".$sdate."'".(($edate) ? " AND ltime <= '".$edate."'": '');
	if ($wuser) {
	  $query1.=" AND vuid=".$wuser;
	}
	$query1.=" GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d')";
	  
	if ($vonly =="1") {
	  $query.=$query1." ORDER by mydate DESC";
	  break;
	} else {
	  $query="(".$query1.") UNION ALL ";
	}
      case 2:
	$query2 = "SELECT sltime as mydate,'DEBITO UTENTE',-SUM(ssaldo) as saldo,1 as mylock";
	if ($admin) {
	  $query2.=",CONCAT('Elimina%act=admcash&type=d&date=',UNIX_TIMESTAMP(sltime)".(($wuser) ? ",'&id=',suid" : "").") as myextra";
	}
	$query2.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=0 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	if ($wuser) {
	  $query2.=" AND suid=".$wuser;
	}
	$query2.=" GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d')";
	
	if ($vonly =="2") {
	  $query.=$query2." ORDER by mydate DESC";
	  break;
	} else {
	  $query.="(".$query2.") ";
	  $query .="UNION ALL ";
	}
      case 3:
	$query3 = "SELECT sltime as mydate,'CREDITO UTENTE',SUM(ssaldo) as saldo,1 as mylock";
	if ($admin) {
	  $query3.=",CONCAT('Elimina%act=admcash&type=c&date=',UNIX_TIMESTAMP(sltime)".(($wuser) ? ",'&id=',suid" : "").") as myextra";
	}
	$query3.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=1 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	if ($wuser) {
	  $query3.=" AND suid=".$wuser;
	}
	$query3.=" GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d')";
	
	if ($vonly =="3") {
	  $query.=$query3." ORDER by mydate DESC";
	  break;
	} else {
	  $query.="(".$query3.") ";
	  if (!$wuser) $query .="UNION ALL ";
	}
      case 4:
	if (!$wuser) {
	  $query4="SELECT fpltime as mydate,'PAGAMENTO FORNITORE',-SUM(fpsaldo) as saldo,1 as mylock";
	  if ($admin) {
	    $query4.=",CONCAT('Elimina%act=admcash&type=p&date=',UNIX_TIMESTAMP(fpltime)) as myextra";
	  }
	  $query4.=" FROM ".SALDO_FIDPAGAMENTO." WHERE fpltime >= '".$sdate."'".(($edate) ? " AND fpltime <= '".$edate."'": '');
	  $query4.=" GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d')";
	  
	  if ($vonly =="4") {
	    $query.=$query4." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query4.") UNION ALL";
	  }
	} else if ($vonly=="4") {
	  $query ="SELECT NULL,NULL,NULL";
	  break;
	}
      case 5:
	if (!$wuser) {
	  $query5 = "SELECT sltime as mydate,'SPESA GAS',-SUM(ssaldo) as saldo,1 as mylock";
	  if ($admin) {
	    $query5.=",CONCAT('Elimina%act=admcash&type=s&date=',UNIX_TIMESTAMP(sltime)) as myextra";
	  }
	  $query5.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=2 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	  $query5.=" GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d')";

	  if ($vonly =="5") {
	    $query.=$query5." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query5.") UNION ALL";
	  }
	} else if ($vonly=="5") {
	  $query ="SELECT NULL,NULL,NULL";
	  break;
	}
      case 6:
	if (!$wuser) {
	  $query6 = "SELECT sltime as mydate,'ENTRATA GAS',SUM(ssaldo) as saldo,1 as mylock";
	  if ($admin) {
	    $query6.=",CONCAT('Elimina%act=admcash&type=e&date=',UNIX_TIMESTAMP(sltime)) as myextra";
	  }
	  $query6.=" FROM ".SALDO_DEBITO_CREDITO." WHERE stype=3 AND sltime >= '".$sdate."'".(($edate) ? " AND sltime <= '".$edate."'": '');
	  $query6.=" GROUP BY DATE_FORMAT(mydate,'%Y-%m-%%d')";

	  if ($vonly =="6") {
	    $query.=$query6." ORDER by mydate DESC";
	    break;
	  } else {
	    $query.="(".$query6.") ";
	  }
	} else if ($vonly=="6") {
	  $query ="SELECT NULL,NULL,NULL";
	  break;
	}
      default:
	$query7="SELECT odata as mydate,'ORDINE',-SUM(osaldo) as saldo,MIN(olock) as mylock";
	if ($admin) {
	  $query7.=",CONCAT('Gestisci%act=admorders&date=',odata,'&op=Cerca&closed=',MIN(olock),IF(MAX(ovalid)=1,'',CONCAT('|Elimina%act=admcash&type=o&date=',UNIX_TIMESTAMP(odata)".(($wuser) ? ",'&id=',ouid" : "")."))) as myextra";
	}
	$query7.= " FROM ".SALDO_ORDINI." WHERE odata >= '".$sdate."'".(($edate) ? " AND odata <= '".$edate."'": '');
	if ($wuser) {
	  $query7.=" AND ouid=".$wuser;
	}
	$query7.=" GROUP BY mydate";
	if ($vonly >0) {
	  $query=$query7." ORDER by mydate DESC";
	  break;
	} else {
	  $query="(".$query7.") UNION ALL ".$query." ORDER by mydate DESC";
	}
      }
    }
    $out=saldo_table($query,$headers,array('saldo'=>array('<strong>Saldo movimenti selezionati</strong>')));
    drupal_set_message($msg);
  }

  if ($wuser) {
    $query="SELECT SUM(t) FROM ((SELECT -IFNULL(SUM(osaldo),0) as t FROM ".SALDO_ORDINI." WHERE ouid=".$wuser.") ";
    $query .="UNION ALL ( SELECT IFNULL(SUM(vsaldo),0) as t FROM ".SALDO_VERSAMENTI." WHERE vuid=".$wuser.") ";
    $query .="UNION ALL ( SELECT -IFNULL(SUM(ssaldo),0) as t FROM ".SALDO_DEBITO_CREDITO." WHERE stype=0 AND suid=".$wuser.") ";
    $query .="UNION ALL ( SELECT IFNULL(SUM(ssaldo),0) as t FROM ".SALDO_DEBITO_CREDITO." WHERE stype=1 AND suid=".$wuser.") ";
    $query .=") as v";
    $headers=array('Saldo Totale Utente <em>'.$users[$wuser].'</em> (in Euro)');
  } else {
    $query=" SELECT SUM(t) FROM ((SELECT -IFNULL(SUM(fpsaldo),0) as t FROM ".SALDO_FIDPAGAMENTO.") UNION ALL ( SELECT IFNULL(SUM(vsaldo),0) as t FROM ".SALDO_VERSAMENTI.") UNION ALL (SELECT IFNULL(SUM(IF(stype=2,-ssaldo,ssaldo)),0) as t FROM ".SALDO_DEBITO_CREDITO." WHERE stype>1) ) as v;";
    $headers=array('<em>Totale Saldo Cassa</em>');
  }
  $out.=saldo_table($query,$headers);
  return $out;
}

function admin_cash_list($type,$date,$id,$fid,$del=false) {
  if (!$date && !$id) {
    return false;
  }
  if ($del) {
    $query="DELETE ";
  } else {
    $headers= array(
		    'Data',
		    'Utente/Fornitore',
		    'Saldo',
		    'Valido',
		    );
  }
  switch ($type) {
  case 'o':
    if (!$del) {
      drupal_set_message("Eliminare ORDINI utente?");
      $uqfid="(SELECT fnome FROM ".SALDO_FORNITORI." WHERE ofid=fid)";
      if ($id) $uqfid="CONCAT((SELECT unome FROM ".SALDO_UTENTI." WHERE ouid=uid),' / ',".$uqfid.")";
      $query="SELECT odata as mydate,".$uqfid." as fnome,-SUM(osaldo) as saldo,MAX(ovalid) as mylock";
    }
    $query.= " FROM ".SALDO_ORDINI." WHERE odata = FROM_UNIXTIME(".$date.")";
    if ($id) {
      $query.=" AND ouid=".$id;
    }
    if ($fid) {
      $query.=" AND ofid=".$fid;
    }
    if ($del) {
      $query .= " AND ovalid=0 AND olock=0";
      $logact="ordini utente";
    } else {
      $query.=" GROUP BY fnome HAVING MAX(ovalid)=0";
    }
    break;
  case 'p':
    if (!$del) {
      drupal_set_message("Eliminare PAGAMENTI fornitore?");
      $uqfid="(SELECT fnome FROM ".SALDO_FORNITORI." WHERE fpfid=fid)";
      $query="SELECT fpltime as mydate,".$uqfid." as fnome,-SUM(fpsaldo) as saldo,1 as mylock ";
    }
    $query .= "FROM ".SALDO_FIDPAGAMENTO;
    if ($date) {
      $query.=" WHERE DATE_FORMAT(fpltime,'%Y-%m-%%d') = DATE_FORMAT(FROM_UNIXTIME(".$date."),'%Y-%m-%%d')";
    } elseif ($id) {
      $query.=" WHERE fpid=".$id;
      $logquery="SELECT CONCAT('Fornitore: ',fnome) FROM ".SALDO_FORNITORI." JOIN ".SALDO_FIDPAGAMENTO." ON fpfid=fid WHERE fpid=".$id;
    }
    if ($del) {
      $logact="pagamenti fornitore";
    } else {
      $query.=" GROUP BY fnome";
    }
    break;
  case 'v':
    if (!$del) {
      drupal_set_message("Eliminare VERSAMENTI utente?");
      $uqfid="(SELECT unome FROM ".SALDO_UTENTI." WHERE vuid=uid)";
      $query="SELECT ltime as mydate,".$uqfid." as unome,SUM(vsaldo) as saldo,1 as mylock ";
    }
    $query .= "FROM ".SALDO_VERSAMENTI;
    if ($date) {
      $query.=" WHERE DATE_FORMAT(ltime,'%Y-%m-%%d') = DATE_FORMAT(FROM_UNIXTIME(".$date."),'%Y-%m-%%d')";
      if ($id) {
	$query.=" AND vuid=".$id;
      }
    } elseif ($id) {
      $query.=" WHERE vid=".$id;
      $logquery="SELECT CONCAT(unome,' (',email,')') myextra FROM ".SALDO_UTENTI." JOIN ".SALDO_VERSAMENTI." ON vuid=uid WHERE vid=".$id;
    }
    if ($del) {
      $logact="versamenti utente";
    } else {
      $query.=" GROUP BY unome";
    }
    break;
  case 's':
    if (!$del) {
      drupal_set_message("Eliminare SPESA Gas?");
      $query = "SELECT sltime as mydate,concat('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>Spesa</legend><div class=\'saldo_note\'>',snote,'</div></fieldset>'),-SUM(ssaldo) as saldo,1 as mylock ";
    }
    $query .= "FROM ".SALDO_DEBITO_CREDITO." WHERE stype=2";
    if ($date) {
      $query.=" AND DATE_FORMAT(sltime,'%Y-%m-%%d') = DATE_FORMAT(FROM_UNIXTIME(".$date."),'%Y-%m-%%d')";
    } elseif ($id) {
      $query.=" AND sid=".$id;
    }
    if ($del) {
      $logact="spesa Gas";
    } else {
      $query.=" GROUP BY sid";
    }
    break;
  case 'e':
    if (!$del) {
      drupal_set_message("Eliminare Entrata Gas?");
      $query = "SELECT sltime as mydate,concat('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>Entrata</legend><div class=\'saldo_note\'>',snote,'</div></fieldset>'),SUM(ssaldo) as saldo,1 as mylock ";
    }
    $query .= "FROM ".SALDO_DEBITO_CREDITO." WHERE stype=3";
    if ($date) {
      $query.=" AND DATE_FORMAT(sltime,'%Y-%m-%%d') = DATE_FORMAT(FROM_UNIXTIME(".$date."),'%Y-%m-%%d')";
    } elseif ($id) {
      $query.=" AND sid=".$id;
    }
    if ($del) {
      $logact="Entrata Gas";
    } else {
      $query.=" GROUP BY sid";
    }
    break;
  case 'd':
    if (!$del) {
      drupal_set_message("Eliminare DEBITO utente?");
      $uqfid="(SELECT unome FROM ".SALDO_UTENTI." WHERE suid=uid)";
      $query = "SELECT sltime as mydate,".$uqfid." as unome,-SUM(ssaldo) as saldo,1 as mylock ";
    }
    $query .= "FROM ".SALDO_DEBITO_CREDITO." WHERE stype=0";
    if ($date) {
      $query.=" AND DATE_FORMAT(sltime,'%Y-%m-%%d') = DATE_FORMAT(FROM_UNIXTIME(".$date."),'%Y-%m-%%d')";
      if ($id) {
	$query.=" AND suid=".$id;
      }
    } elseif ($id) {
      $query.=" AND sid=".$id;
      $logquery="SELECT CONCAT(unome,' (',email,' )') as myextra FROM ".SALDO_UTENTI." JOIN ".SALDO_DEBITO_CREDITO." ON suid=uid WHERE stype=0 AND sid=".$id;
    }
    if ($del) {
      $logact="debito utente";
    } else {
      $query.=" GROUP BY unome";
    }
    break;
  case 'c':
    if (!$del) {
      drupal_set_message("Eliminare CREDITO utente?");
      $uqfid="(SELECT unome FROM ".SALDO_UTENTI." WHERE suid=uid)";
      $query = "SELECT sltime as mydate,".$uqfid." as unome,SUM(ssaldo) as saldo,1 as mylock ";
    }
    $query .= "FROM ".SALDO_DEBITO_CREDITO." WHERE stype=1";
    if ($date) {
      $query.=" AND DATE_FORMAT(sltime,'%Y-%m-%%d') = DATE_FORMAT(FROM_UNIXTIME(".$date."),'%Y-%m-%%d')";
      if ($id) {
	$query.=" AND suid=".$id;
      }
    } elseif ($id) {
      $query.=" AND sid=".$id;
      $logquery="SELECT CONCAT(unome,' (',email,' )') as myextra FROM ".SALDO_UTENTI." JOIN ".SALDO_DEBITO_CREDITO." ON suid=uid WHERE stype=1 AND sid=".$id;
    }
    if ($del) {
      $logact="credito utente";
    } else {
      $query.=" GROUP BY unome";
    }
    break;
  default:
    return false;
  }
  //Eliminazione
  if ($del) {
    $lextra=array();
    //Informazioni aggiuntive per il log
    if ($logquery) {
      $lextra[]=db_result(db_query($logquery));
    } elseif ($id) {
      $logfu=get_users($id);
      $lextra[]=implode(" - ",$logfu);
    }
    if ($fid) {
      $logfu=get_fids(array($fid));
      $lextra[]="Fornitore: ".implode(" - ",$logfu);
    }
    if ($out=db_query($query)) {
      if ($num=db_affected_rows() > 0) {
	$ldate=($date) ? date('Y-m-d',$date) : 'NULL';
	$llextra=implode(",",$lextra);
	log_gas("Tesoriere: Eliminazione ".$logact,$ldate,$llextra);
	$out = "Eliminazione <em>".$logact.(($date) ? " del ".date('d-m-Y',$date) : "");
	$out .= (($llextra) ? " per  ". $llextra : "")."</em> avvenuta con successo!";
      } else {
	$out="";
	drupal_set_message("Non &egrave stato trovato nessun movimento di cassa eliminabile. Nel caso si abbia selezioniato degli ordini, prima di eliminarli, controllare che non siano validati ed eventualmente ".l('sbloccarli',$_GET['q'],array(),'act=admorders').".",'error');
      }
    }
  } else {
    //Visualizzazione
    $query.=" ORDER by mydate DESC";
    $out=saldo_table($query,$headers,array('saldo'=>array('<strong>Saldo movimenti selezionati</strong>')));
  }
  return $out;
}
