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
    $btn='Importa';
    $form['import']['cancel'] = array(
					    '#type' => 'button',
					    '#value' => 'Cancella',
					    '#weight' =>1,
					    );
    $form['imported'] = array('#value' =>$flag,'#weight' => 2);
  } else {
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
    $query="INSERT INTO ".SALDO_VERSAMENTI." (vuid,vsaldo,vlastduid) VALUES ";
    $count = 0;
    foreach ($_SESSION['pay_import_table']['table'] as $usaldo) {
      $query .= "(".$usaldo[0].",".$usaldo[3].",".$suser->duid."),";
      $count += 1;
    }
    $query = rtrim($query,',');
    unset($_SESSION['pay_import_table']);
    if (db_query($query)) {
      drupal_set_message($count." versamenti importati correttamente.");
      log_gas("Tesoriere: Importazione versamenti utente");
    } else {
      drupal_set_message("Errore importazione versamenti",'error');
    }
  }
}