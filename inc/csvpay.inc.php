<?php
function pay_impexp_form_checkcsv() {
  //controllo se ho gia' importato il cvs e formatto il suo output 
  if ($rows=$_SESSION['pay_import_table']) {
    if (isset($rows['headers']) && isset($rows['table'])) {
      $output = theme('table',$rows['headers'],$rows['table']);
    }
  }
  return $output;
}

function pay_impexp_form() {
  $flag=pay_impexp_form_checkcsv();
  $form['import'] = array(
			  '#type' => 'fieldset',
			  '#title' => 'Importa versamenti',
			  );
  
  //Csv gia' caricato. Da importare
  if ($flag) {
    $form['import']['date'] = array(
				    '#type' => 'textfield',
				    '#title' => 'Data del versamento',
				    '#attributes' => array('class' => 'jscalendar'),
				    '#description' => 'Inserire la data nel formato gg/mm/aaaa . La data verr&agrave applicata a tutti i versamenti.',
				    '#jscalendar_ifFormat' => '%d/%m/%Y',
				    '#jscalendar_showsTime' => 'false',
				    '#default_value' => date("d/m/Y"),
				    '#size' => 10,
				    '#maxlength' => 10,
				    '#required' => TRUE,
				    );

    $btn='Importa';
    $form['import']['cancel'] = array(
					    '#type' => 'button',
					    '#value' => 'Cancella',
					    '#weight' =>1,
					    );
    $form['imported'] = array('#value' =>$flag,'#weight' => 2);
  } else {
    $form['help'] = array('#type' => 'fieldset',
				'#weight' => -1,
				'#title' => 'Aiuto',
				'#collapsible'=>true,
				'#collapsed'=>true,
				'#value' => "<div>Questa funzionalit&agrave; permette di velocizzare l'inserimento dei versamenti utente.<br /><ul>
					<li>Utilizzare il pulsante <strong>Esporta</strong> per creare un file csv Excel con tutti gli utenti e salvarlo sul proprio pc.</li>
					<li>Aprire Excel e, tramite <strong>File->Apri</strong>, aprire il file creato.
					<strong>ATTENZIONE!</strong> Non aprire il file semplicemente cliccandoci sopra, ma seguire le istruzioni precedenti o la successiva procedura Importazione Guidata Testo potrebbe non essere visualizzata.</li>
					<li>Seguire la procedura di <strong>Importazione Guidata Testo</strong>,
					verificando che nelle varie schermate siano selezionate le opzioni:
						<ul><li><strong>Tipo di File:</strong> delimitato</li><li><strong>Inizia ad importare dalla riga:</strong> 1</li><li><strong>Delimitatore:</strong> testo</li><li><strong>Qualificatore di testo:</strong> \"\" </li></ul></li>
					Sar&agrave ora possibile compilare il campo versamento nel file excel per gli utenti voluti, ad esempio durante la consegna, e successivamente importarlo nel Gestionale tramite il sottostante <strong>Importa csv</strong>.
					</ul></div>",
		       );

    $form['export'] = array(
			    '#type' => 'fieldset',
			    '#title' => 'Esporta versamenti',
			    );

    $form['export']['esubmit'] = array(
				      '#description' => 'Esporta in un file excel la lista degli utenti in modo da potere in seguito compilarlo con i versamenti ed importartarlo.',
				      '#type' => 'submit',
				      '#value' => 'Esporta',
				    );

    //Il csv deve ancora essere caricato
    $btn='Controlla';
    $form['import']['upload'] = array(
					    '#type' => 'file',
					    '#title' => 'Importa csv',
					    '#size' => 40,
					    '#description'  => "Carica il file csv dei versamenti utente.",
					    );
    $form['#attributes'] = array("enctype" => "multipart/form-data");
  }

  $form['users_import']['act']=array(
				     '#type' => 'hidden',
				     '#value' => 'csvpay',
				     );
 
  $form['import']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => $btn,
					  );

  return $form;
  }

function pay_impexp_form_validate($form_id, $form_values) {
  $op = $_POST['op'];
  switch ($op) {
  case 'Cancella':
    unset($_SESSION['pay_import_table']);
    drupal_goto($_GET['q'],'act=csvpay');
    break;
  case 'Controlla':
    $rows = array();
    $file = file_check_upload();
    if (!$file) {
      form_set_error('import][upload','Errore nel caricamento del file');
      return;
    }
    $handle = fopen($file->filepath, "r");
    $i=0;
    while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) {
      if (empty($data[3])) continue;
      if (is_numeric($data[3]) || $i==0) {
	$rows[]=array(trim(utf8_encode($data[0]),'"'),trim($data[1],'"'),trim(utf8_encode($data[2]),'"'),trim(utf8_encode($data[3]),'"'));
      } else {
	form_set_error($data[0],'Dati versamento errati. Ricompilare la scheda versamenti: '.implode(" ",$data));
      }
      $i++;
    }
    fclose($handle);
    file_delete($file->filepath);
    //Salvo l'array che poi importero' nella sessione.
    $_SESSION['pay_import_table']['headers']=$rows[0];
    //Rimuovo header
    unset($rows[0]);
    $_SESSION['pay_import_table']['table']=$rows;
    drupal_set_message('Trovati <em>'.count($rows).'</em> versamenti.');
    break;
  case 'Importa':
    $mycsv=$_SESSION['pay_import_table'];
    if (!datevalid($form_values['date'])) {
      form_set_error('date',$form_values['date']. " non &egrave una data valida!"); 
  }
    if (saldo_greaterDate($form_values['date'],date('d/m/Y'))) {
      form_set_error('date',$form_values['date']. " &egrave una data futura!"); 
    }
    if (!is_array($mycsv)) {
      form_set_error('import][upload','Errore interno. Prova a reimportare il file');
      unset($_SESSION['pay_import_table']);
      drupal_goto($_GET['q'],'act=csvpay');
    }
    $result = db_query("SELECT * FROM ".SALDO_UTENTI);
    $users=array();
    while ($user = db_fetch_array($result)) {
      $users[$user['uid']]=array($user['unome'],$user['email']);
    }
    foreach ($mycsv['table'] as $user) {
      //Non controllo il campo versamento
      $akey=$users[$user[0]];
      unset($user[3]);
      $id=array_shift($user);
      if (!is_array($user) || !is_array($akey) || count(array_diff($user,$akey)) > 0) {
	form_set_error($id,'Errore integrit&agrave; per: <em>'.implode(",",$user).'</em>. Ricompila il foglio versamenti con un csv aggiornato.');
      }
    }
  default:
  }

}

function pay_impexp_form_submit($form_id, $form_values) {
  if ($_POST['op'] == 'Esporta') {
    $cols=array("uid","unome","email","versamento");
    if ( !$data=saldo_exportcsv("SELECT uid,unome,email,NULL as versamento from ".SALDO_UTENTI." ORDER BY unome",$cols))
      {
	drupal_set_message("Nessun utente trovato. Per poter utilizzare questa funzione, devi prima importare degli ".l('utenti',$_GET['q'],array(),'act=csvusers'),"error");
	return;
      }

  } elseif ($_POST['op']=='Importa') {
    global $suser;
    $date = datevalid($form_values['date']);
    $query="INSERT INTO ".SALDO_VERSAMENTI." (vuid,vsaldo,vlastduid,ltime) VALUES ";
    $extra = '';
    $count = 0;
    foreach ($_SESSION['pay_import_table']['table'] as $usaldo) {
      $query .= "(".$usaldo[0].",".$usaldo[3].",".$suser->duid.",'".$date."'),";
      $extra .= implode("",get_users($usaldo[0])). " ";
      $count += 1;
    }
    $query = rtrim($query,',');
    unset($_SESSION['pay_import_table']);
    if (db_query($query)) {
      drupal_set_message($count." versamenti importati correttamente alla data ".datemysql($form_values['date'],"-","/"));
      log_gas("Tesoriere: Importazione versamenti utente",$date, $extra);
    } else {
      drupal_set_message("Errore importazione versamenti",'error');
    }
  }
}