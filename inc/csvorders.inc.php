<?php
function orders_import_form(&$form_state, $admin=FALSE) {
  global $suser;
  $flag=orders_import_form_checkcsv();

  $form['orders_import'] = array(
				 '#type' => 'fieldset',
				 '#title' => 'Importa ordine da file csv',
				 );

  //Csv gia' caricato. Da importare
  if ($flag) {
    $btn='Importa';
	$form['orders_import']['dated'] = array(
						'#type' => 'textfield',
						'#title' => 'Data default consegna ordine',
						'#attributes' => array('class' => 'jscalendar'),
						'#description' => 'Inserire giorno di consegna nel formato gg/mm/aaaa',
						'#jscalendar_ifFormat' => '%d/%m/%Y',
						'#jscalendar_showsTime' => 'false',
						'#size' => 10,
						'#maxlength' => 10,
						);
	
    $form['orders_import']['override'] = array(
					       '#type' => 'checkbox',
					       '#title' => 'Forza aggiornamento',
					       '#description' => 'Se non selezionata, verr&agrave; richiesta un\' ulteriore conferma nel caso nel gestionale siano gi&agrave; presenti degli ordini per la <strong>data consegna ordine</strong> impostata. Se invece &egrave selezionata, verranno sovrascritti senza ulteriore conferma gli ordini precedentemente importati per gli utenti selezionati, ma solo se ancora aperti e non validati.',
					       );
    $form['orders_import']['cancel'] = array(
					     '#type' => 'button',
					     '#value' => 'Cancella',
					     '#weight' =>1,
					     );
    $form['imported'] = array('#value' =>$flag,'#weight' => 2);
  } else {
    if (defined('SALDO_REFCANIMPORT')) {
		if (saldo_check_role(ROLE_TREASURER)) {
			$fids = get_fids();
		} else {
			$fids = get_fids(array_keys($suser->fids));
		}
		$form['filter'] = array(
							'#type' => 'select',
							'#title' => 'Fornitore',
							'#description' => 'Seleziona il fornitore di cui importare gli ordini.',
							'#options' => $fids,
							'#weight' => -1,
							);
	}
    //Il csv deve ancora essere caricato
    $btn='Controlla';
    $form['orders_import']['upload'] = array(
					     '#type' => 'file',
					     '#title' => 'Importa csv',
					     '#size' => 40,
					     '#description'  => "Carica il file csv degli ordini",
					     );
    $form['#attributes'] = array("enctype" => "multipart/form-data");
    $form['es_import'] = array(
			       '#type' => 'fieldset',
			       '#description' => "L'ordine verr&agrave scaricato direttamente da Economia Solidale. Potrebbe richiedere qualche minuto di attesa.",
			       '#title' => 'Importa ordine da economia solidale',
			       );

    $form['es_import']['submit'] = array(
					 '#type' => 'submit',
					 '#value' => 'Scarica',
					 );  

  }
  
  $form['orders_import']['act']=array(
				      '#type' => 'hidden',
				      '#value' => ($admin) ? 'csvorders' : 'fcsvorders',
				      );

  $form['orders_import']['submit'] = array(
					   '#type' => 'submit',
					   '#value' => $btn,
					   );
  
  return $form;
  }

function orders_import_form_checkcsv() {
  $adate=array();
  //controllo se ho gia' importato il cvs e formatto il suo output
  if ($rows=$_SESSION['orders_import_table']) {
    if (isset($rows['headers']) && isset($rows['table'])) {
      $i=0;
	  if (!defined('SALDO_REFCANIMPORT')) {
      foreach ($rows['headers'] as $k) {
	//Salto le prime due colonne e non aumento $i in modo che l'array date parti da 0.
	if ($k == 'Utente' || $k == 'UserID') {
	  $datechk='';
	} else {
	  $form = array(
			'#type' => 'textfield',
			'#attributes' => array('class' => 'jscalendar'),
			'#description' => 'gg/mm/aaaa',
			'#jscalendar_ifFormat' => '%d/%m/%Y',
			'#jscalendar_showsTime' => 'false',
			'#size' => 10,
			'#maxlength' => 10,
			'#name' => 'date'.$i,
			'#id' => 'edit-date'.$i,
			'#parents' => array('orders_import_form'),
			'#value' =>$_POST['date'.$i],
			);	
	  $datechk=drupal_render($form);
	  $form = array(
			'#type' => 'hidden',
			'#name' => 'date'.$i.'_jscalendar[ifFormat]',
			'#value' => "%d/%m/%Y",
			'#id' => 'edit-date'.$i.'-jscalendar-ifFormat',
			'#parents' => array('orders_import_form'),
			);
	  $datechk.=drupal_render($form);
	  $form = array(
			'#type' => 'hidden',
			'#name' => 'date'.$i.'_jscalendar[showsTime]',
			'#value' => "false",
			'#id' => 'edit-date'.$i.'-jscalendar-showsTime',
			'#parents' => array('orders_import_form'),
			);
	  $datechck.=drupal_render($form);
	  //incremento solo nel caso di colonne con produttori
	  $i++;
	}
	$adate[$k]=$datechk;
      }
	  }
      array_unshift($rows['table'],$adate);
      $output = theme('table',$rows['headers'],$rows['table']);
    }
  }
  return $output;
}

function orders_import_form_validate($form, &$form_state) {
  $op = $_POST['op'];
  switch ($op) {
  case 'Cancella':
    unset($_SESSION['orders_import_table']);
    drupal_goto($_GET['q'],$form_state['values']['orders_import']['act']);
    break;
  case 'Scarica':
    if ($csvstr=get_csv()){
      $file->filepath=file_save_data($csvstr,'/tmp/Punto_consegna.csv',FILE_EXISTS_REPLACE);
    } else {
      drupal_set_message('Download del file da Economia Solidale non riuscito!','error');
      return;
      break;
    }
  case 'Controlla':
    $rows = array();
    if (!$file->filepath) $file = file_save_upload('upload');
    if (!$file) {
      form_set_error('orders_import','Errore nel caricamento del file');
      return;
    }
    $handle = fopen($file->filepath, "r");
    $i=0;
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
      $i++;
      //Salto prime righe.
      if ($i<3) continue;
      //GLi ordini Devono contenere almeno i campi utente + 1 ordine
      if ($i>4 && count($data)<6) continue; 
      //Rimuovo i campi Numero e Socio e quello finale della somma
      array_splice($data,2,2);
      array_shift($data);
      array_pop($data);
      array_walk($data,'_utf8_rows');
      //Agli ordini assegno chiave uguale all'header corrispondente
      if (isset($rows[0])) {
		$rows[]=array_combine($rows[0],$data);
      } else {
		//Forzo il case di alcuni valori per evitare future incompatibilità
		$data[0] = 'Utente';
		$data[1] = 'UserID';
		$rows[]=$data;
      }
    }
    fclose($handle);
    file_delete($file->filepath);
    //Rimuovo il campo della somma totale.
    array_pop($rows);
	
	if (defined('SALDO_REFCANIMPORT')) {
		$result=db_query("SELECT fnome FROM ".SALDO_FORNITORI." WHERE fid=".$form_state['values']['filter']);
		if (!$fidnome=db_result($result)) {
			form_set_error('filter','Errore interno sul fornitore. Prova a reimportare il file');
		    unset($_SESSION['orders_import_table']);
			return;
		}
		$_SESSION['orders_import_table']['headers'][0]=$rows[0][0];
		$_SESSION['orders_import_table']['headers'][1]=$rows[0][1];
		$_SESSION['orders_import_table']['headers'][2]=$fidnome;
		//Rimuovo headers
		//$ar_shifted = array_shift($rows);
		foreach($rows as $k => $fnomerow) {
			if ($k == 0) continue;
			if ($fnomerow[$fidnome]) {
				$_SESSION['orders_import_table']['table'][$k]['Utente']=$rows[$k]['Utente'];
				$_SESSION['orders_import_table']['table'][$k]['UserID']=$rows[$k]['UserID'];
				$_SESSION['orders_import_table']['table'][$k][$fidnome]=$fnomerow[$fidnome];
			}
		}
		if (empty($_SESSION['orders_import_table']['table'])) unset($_SESSION['orders_import_table']);
	} else {
		//Salvo l'array che poi importero' nella sessione.
		$_SESSION['orders_import_table']['headers']=$rows[0];
	}
	//Controllo se necessario inserire produttori
	$fornitori=array();
	$result=db_query("SELECT fnome from ".SALDO_FORNITORI.";");
	while ($fid = db_fetch_array($result)) {
		$fornitori[]=$fid['fnome'];;
	}
	//Rimuovo colonne Utente, UserID
	array_splice($rows[0],0,2);
	foreach ($rows[0] as $f) {
		if (!in_array($f,$fornitori)) {
			if (defined('SALDO_REFCANIMPORT')) {
				$msg = 'Trovato un nuovo fornitore da importare: <strong><em>'.$f. "</em></strong>. ";
				$msg .= (saldo_check_role(ROLE_TREASURER)) ? "Puoi importarlo andando alla pagina <em>".l('Importa Fornitori',$_GET['q'],array('query' => 'act=csvfids'))."</em>." : "Per favore, contatta il tuo tesoriere , si occuper&agrave lui stesso di importarlo nel gestionale in modo che sia possibile gestirne gli ordini.";
				drupal_set_message($msg,'warning');
			} else {
				drupal_set_message('Il fornitore <strong><em>'.$f. "</em></strong> non esiste! Verr&agrave; importato automaticamente.<br /><strong>ATTENZIONE!</strong> Nel caso invece il fornitore abbia cambiato nome su economia solidale, &egrave FONDAMENTALE cancellare l'importazione e modificare il nome in <em>Gestione fornitori</em>.");
				$_SESSION['orders_import_table']['fid'][]=$f;
			}
		}
	}
	if (!defined('SALDO_REFCANIMPORT')) {
		//Rimuovo headers
		array_shift($rows);
		$_SESSION['orders_import_table']['table']=$rows;
	}
	if (count($_SESSION['orders_import_table']['table']) == 0) {
		drupal_set_message('Al momento non ci sono ordini da importare'.(($fidnome) ? ' per il produttore "'.$fidnome.'".' : '.'));
	} else {
		drupal_set_message('Sono stati trovati ordini di <em>'.count($_SESSION['orders_import_table']['table']).'</em> utenti'.(($fidnome) ? ' per il produttore "'.$fidnome.'".' : '.'));
	}
    unset($rows);
    break;
  case 'Importa':
    $err_goto=false;
    $mycsv=$_SESSION['orders_import_table'];
    if (!is_array($mycsv)) {
      form_set_error('orders_import][upload','Errore interno. Prova a reimportare il file');
      unset($_SESSION['orders_import_table']);
      drupal_goto($_GET['q'],$form_state['values']['act']);
      return;
    }
    //Ottengo lista utenti esistenti
    $result = db_query("SELECT uid FROM ".SALDO_UTENTI);
    $users= array();
    while ($user = db_fetch_array($result)) {
      $users[]=$user['uid'];
    }
    $defdate=datevalid($_POST['dated']);
    if (!$defdate) form_set_error('dated',"La data di default <em>".$_POST['dated']."</em> non &egrave valida.");
    //Trovo i fornitori da controllare, rimuovendo le colonne inutili
    $infids=array_slice($mycsv['headers'],2);
    foreach ($mycsv['table'] as $o => $ordine) {
      //Controllo utenti non esistenti)
      if (!in_array($ordine['UserID'],$users)) {
	drupal_set_message("L' utente <em>".$ordine['Utente']."</em> id:".$ordine['UserID']." non esiste!",'error');
	$err_goto='csvusers';
      }
      $cst_date=array();
      foreach ($infids as $k=>$fkey) {
	//Controllo date valide
	if (empty($_POST['date'.$k])) {
	  //Data default
	  $cst_date[$k]=$defdate;
	} else {
	  //Data personalizzata
	  if (!$cst_date[$k]=datevalid($_POST['date'.$k])) {
	    form_set_error('date'.$k,"La data personalizzata <em>".$_POST['date'.$k]."</em> per <em>".$fkey."</em> non &egrave valida.");
	  }
	}
	if (!empty($ordine[$fkey]) && $cst_date[$k]) {
	  $query="SELECT olock,ovalid,oid from ".SALDO_ORDINI." where odata='".$cst_date[$k]."' AND ouid=".$ordine['UserID'];
	  $query .= " AND ofid=(SELECT fid FROM ".SALDO_FORNITORI." where fnome='".addcslashes($fkey,"'")."');";
	  $result=db_query($query);
	  while ($oids= db_fetch_array($result)) {
	    //Controllo che non esistano ordini gia' importati per le date di consegna impostate
	    if (!$_POST['override']) {
	      form_set_error('date'.$k,"Esiste gi&agrave; un ordine al fornitore <em>".$fkey."</em> per la data ".datemysql($cst_date[$k],"-","/").". Se vuoi sovrascriverlo, seleziona l'opzione <strong>Forza aggiornamento</strong>.<br />ATTENZIONE: verranno sovrascritti SOLO gli ordini degli utenti visualizzati nella tabella sottostante, eventuali ordini di altri utenti precedentemente importati per questo fornitore rimmarrano invariati.");
	    }
	    //Controllo lock e validate.
	    if ($oids['olock'] || $oids['ovalid']) {
	      drupal_set_message("L'ordine di <em>".$ordine['Utente']."</em> del <em>".$cst_date[$k]."</em> per <em>".$fkey."</em> &egrave bloccato e non sar&agrave importato!",'error');
	      unset($_SESSION['orders_import_table']['table'][$o][$fkey]);
	      break;
	    }
	  }
	}
      }
    }
    //In caso di errore critico, svuoto sessione e ridireziono alla pagina consigliata.
    if ($err_goto) {
      form_set_error('user',"Prima di importare gli ordini, devi importare gli utenti mancanti da questa pagina.");
      unset($_SESSION['orders_import_table']);
      drupal_goto($_GET['q'],"act=".$err_goto);
    }
  default:
  }
}

function orders_import_form_submit($form, &$form_state) {
  global $suser;
  $op=$_POST['op'];
  if ($op=='Importa') {
    $mycsv=$_SESSION['orders_import_table'];
    //Inserisci fornitori
    if (is_array($mycsv['fid'])) {
#DELAYED?
      $query="INSERT INTO ".SALDO_FORNITORI." (fnome) VALUES ";
      foreach ($mycsv['fid'] as $fid) {
	$query .= "('".addcslashes($fid,"'")."'),";
      }
      $query = rtrim($query,',')."ON DUPLICATE KEY UPDATE fnome=VALUES(fnome);";
      if (db_query($query)) {
	drupal_set_message("Sono stati importati <em>".db_affected_rows()."</em> fornitori. Ricordati di impostare i loro ".l('referenti',$_GET['q'],array('query' => 'act=admfids')));
	foreach ($mycsv['fid'] as $fid) {
	  log_gas("Tesoriere: Importazione fornitore","NULL",addcslashes($fid,"'"));
	}
      }
    }
    //Query per inserire ordine
    //Trovo i fornitori da controllare, rimuovendo le colonne inutili
    $infids=array_slice($mycsv['headers'],2);
    $row_aff=0;
    $i=0;
    $dtslog=array();
    foreach ($mycsv['table'] as $key=>$ordine) {
      foreach ($infids as $k=>$fid) {
	$date=(empty($_POST['date'.$k])) ? $_POST['dated']: $_POST['date'.$k];
	if (!empty($ordine[$fid])) {
	  $query="INSERT INTO ".SALDO_ORDINI." (odata,ouid,ofid,osaldo,lastduid) VALUES ";
	  $query.="('".datemysql($date)."',".$ordine['UserID'].",";
	  $query.="(SELECT fid FROM ".SALDO_FORNITORI." where fnome='".addcslashes($fid,"'")."'),";
	  $query.=str_replace(",",".",preg_replace('/[^0-9\,\.]/','',$ordine[$fid])).",".$suser->duid.") ON DUPLICATE KEY UPDATE osaldo=VALUES(osaldo),lastduid=VALUES(lastduid),otime=NOW();";
	  if (db_query($query)) {
	    if ($r_aff=db_affected_rows() > 0) {
	      $dtslog[$date][]=addcslashes($fid,"'");
	    }
	    $row_aff += $r_aff;
	    $i++;
	  }
	}
      }
    }
    foreach (array_keys($dtslog) as $dtlog) {
      log_gas("Tesoriere: Importazione ordini",datemysql($dtlog),implode(",",array_unique($dtslog[$dtlog])));
    }
    unset($_SESSION['orders_import_table']);
    drupal_set_message("Sono stati importati <em>". ($i - $row_aff + $i). "</em> ordini e aggiornati <em>".($row_aff-$i)."</em> ordini su un totale di $i ordini.");
  }
}
?>
