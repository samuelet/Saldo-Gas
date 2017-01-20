<?php
function users_import_form() {
  $flag=users_import_form_checkcsv();
  $form['users_import'] = array(
				'#type' => 'fieldset',
				'#title' => 'Importa utenti da economia solidale',
				);

  //Csv gia' caricato. Da importare
  if ($flag) {
    $btn='Importa';
    $form['users_import']['cancel'] = array(
					    '#type' => 'button',
					    '#value' => 'Cancella',
					    '#weight' =>1,
					    );
    $form['imported'] = array('#value' =>$flag,'#weight' => 2);
  } else {
    //Il csv deve ancora essere caricato
    $btn='Controlla';
    $form['users_import']['upload'] = array(
					    '#type' => 'file',
					    '#title' => 'Importa csv',
					    '#size' => 40,
					    '#description'  => "Carica il file csv degli utenti",
					    );
    $form['#attributes'] = array("enctype" => "multipart/form-data");
    $form['es_import'] = array(
			       '#type' => 'fieldset',
			       '#description' => "Gli utenti verranno scaricati direttamente da Economia Solidale. Potrebbe richiedere qualche minuto di attesa.",
			       '#title' => 'Importa utenti da economia solidale',
			       );

    $form['es_import']['submit'] = array(
					 '#type' => 'submit',
					 '#value' => 'Scarica',
					 );  

  }
  
  $form['users_import']['act']=array(
				     '#type' => 'hidden',
				     '#value' => 'csvusers',
				     );

  $form['users_import']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => $btn,
					  );
  
  return $form;
  }

function users_import_form_checkcsv() {
  //controllo se ho gia' importato il cvs e formatto il suo output 
  if ($rows=$_SESSION['users_import_table']) {
    if (isset($rows['headers']) && isset($rows['table'])) {
      $output = theme('table',$rows['headers'],$rows['table']);
    }
  }
  return $output;
}

function users_import_form_validate($form_id, $form_values) {
  $op = $_POST['op'];
  switch ($op) {
  case 'Cancella':
    unset($_SESSION['users_import_table']);
    drupal_goto($_GET['q'],'act=csvusers');
    break;
  case 'Scarica':
    if ($csvstr=get_csv(2)){
      $file->filepath=file_save_data($csvstr,'/tmp/referenti_ms.csv',FILE_EXISTS_REPLACE);
    } else {
      drupal_set_message('Download del file da Economia Solidale non riuscito!','error');
      return;
      break;
    }
  case 'Controlla':
    $rows = array();
    if (!$file->filepath) $file = file_check_upload();
    if (!$file) {
      form_set_error('users_import','Errore nel caricamento del file');
      return;
    }
    $handle = fopen($file->filepath, "r");
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
      # Fix Codice a barre
      $user_id = $data[0];
      $user_id = substr($data[0], 3);
      $user_id = substr($user_id, 0, -1);
      $rows[]=array((int)$user_id, utf8_encode($data[2]." ".$data[3]), $data[4]);
    }
    $rows[0][0]="User ID";
    fclose($handle);
    file_delete($file->filepath);
    //Salvo l'array che poi importero' nella sessione.
    $_SESSION['users_import_table']['headers']=$rows[0];
    //Rimuovo header
    unset($rows[0]);
    //Rimuovo riga inutilizzata
    array_shift($rows);
    drupal_set_message('Trovati <em>'.count($rows).'</em> utenti.');
    $_SESSION['users_import_table']['table']=$rows;
    break;
  case 'Importa':
    $mycsv=$_SESSION['users_import_table'];
    if (!is_array($mycsv)) {
      form_set_error('users_import][upload','Errore interno. Prova a reimportare il file');
      unset($_SESSION['users_import_table']);
      drupal_goto($_GET['q'],'act=csvusers');
    }
  default:
  }
}

function users_import_form_submit($form_id, $form_values) {
  $op=$_POST['op'];
  if ($op=='Importa') {
    $query="INSERT INTO ".SALDO_UTENTI." (uid,unome,email) VALUES ";
    foreach ($_SESSION['users_import_table']['table'] as $usr) {
      //La prima riga contiene numero incrementale
      $query.="(".$usr[0].",'".addslashes($usr[1])."','".$usr[2]."'),";
    }
    $query=rtrim($query,",")." ON DUPLICATE KEY UPDATE uid=VALUES(uid),email=VALUES(email),unome=VALUES(unome);";
    unset($_SESSION['users_import_table']);
    if (db_query($query) && $aff=db_affected_rows() > 0) {
      drupal_set_message("Sono stati importati o aggiornati <em>".$aff."</em> utenti");
      log_gas("Tesoriere: Importazione utenti");
    } else {
      drupal_set_message("Errore nell'importazione degli utenti!");
    }
  }
}
?>
