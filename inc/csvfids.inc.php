<?php
function fids_import_form() {
  global $suser;
  $flag=fids_import_form_checkcsv();

  $form['fids_import'] = array(
				 '#type' => 'fieldset',
				 '#title' => 'Importa ordine da file csv',
				 );

  //Csv gia' caricato. Da importare
  if ($flag) {
    $btn='Importa';
	$form['fids_import']['cancel'] = array(
					     '#type' => 'button',
					     '#value' => 'Cancella',
					     '#weight' =>1,
					     );
    $form['imported'] = array('#value' =>$flag,'#weight' => 2);
  } else {
    //Il csv deve ancora essere caricato
    $btn='Controlla';
    $form['fids_import']['upload'] = array(
					     '#type' => 'file',
					     '#title' => 'Importa csv',
					     '#size' => 40,
					     '#description'  => "Carica il file csv degli ordini esportato da Eonomia Solidale. I fornitori da importare verranno cercati in questo file.",
					     );
    $form['#attributes'] = array("enctype" => "multipart/form-data");
    $form['es_import'] = array(
			       '#type' => 'fieldset',
			       '#description' => "I Fornitori verranno scaricati direttamente da Economia Solidale. Potrebbe richiedere qualche minuto di attesa.",
			       '#title' => 'Importa Fornitori da economia solidale',
			       );

    $form['es_import']['submit'] = array(
					 '#type' => 'submit',
					 '#value' => 'Scarica',
					 );  

  }
  
  $form['fids_import']['act']=array(
				      '#type' => 'hidden',
				      '#value' => 'csvfids',
				      );

  $form['fids_import']['submit'] = array(
					   '#type' => 'submit',
					   '#value' => $btn,
					   );
  
  return $form;
  }

function fids_import_form_checkcsv() {
  $output=false;
  $rows=$_SESSION['fids_import_table']['fids'];
  //controllo se ho gia' importato il cvs e formatto il suo output
  if (isset($rows)) {
      $output = theme('table',array('Fornitori'),$rows);
  }
  return $output;
}

function fids_import_form_validate($form, &$form_state) {
  $op = $_POST['op'];
  switch ($op) {
	  case 'Cancella':
		unset($_SESSION['fids_import_table']);
		drupal_goto($_GET['q'],$form_state['values']['fids_import']['act']);
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
		  //Mi fermo ai Fornitori
		  if ($i>3) break;
		  //Rimuovo i campi Numero e Socio e quello finale della somma
		  array_splice($data,0,5);
		  array_pop($data);
		  array_walk($data,'_utf8_rows');
		  $rows=$data;
		}
		fclose($handle);
		file_delete($file->filepath);
		//Controllo se necessario inserire produttori
		$fornitori=array();
		$result=db_query("SELECT fnome from ".SALDO_FORNITORI.";");
		while ($fid = db_fetch_array($result)) {
			$fornitori[]=$fid['fnome'];;
		}
		$fidstoimport=array();
		foreach ($rows as $f) {
			if (!in_array($f,$fornitori)) {
				drupal_set_message('Il fornitore <strong><em>'.$f. "</em></strong> non esiste! Verr&agrave; importato automaticamente.<br /><strong>ATTENZIONE!</strong> Nel caso invece il fornitore abbia cambiato nome su economia solidale, &egrave <strong>FONDAMENTALE</strong> cancellare l'importazione e modificare il nome in <em>Gestione fornitori</em>.");
				$fidstoimport[]=array($f);
			}
		}

		if (count($fidstoimport) == 0) {
			drupal_set_message('Al momento non ci sono Fornitori da importare');
		} else {
			$_SESSION['fids_import_table']['fids'] = $fidstoimport;
		}
		break;
	  case 'Importa':
		$infids=$_SESSION['fids_import_table']['fids'];
		if (!is_array($infids)) {
		  form_set_error('fids_import][upload','Errore interno. Prova a reimportare il file');
		  unset($_SESSION['fids_import_table']);
		  drupal_goto($_GET['q'],$form_state['values']['act']);
		  return;
		}
	  default:
  }
}

function fids_import_form_submit($form, &$form_state) {
  global $suser;
  $op=$_POST['op'];
  if ($op=='Importa') {
    $mycsv=$_SESSION['fids_import_table']['fids'];
    //Inserisci fornitori
    if (is_array($mycsv)) {
#DELAYED?
      $query="INSERT INTO ".SALDO_FORNITORI." (fnome) VALUES ";
      foreach ($mycsv as $k => $fid) {
		$query .= "('".addcslashes($fid[0],"'")."'),";
      }
      $query = rtrim($query,',')." ON DUPLICATE KEY UPDATE fnome=VALUES(fnome);";
      if (db_query($query)) {
		drupal_set_message("Sono stati importati <em>".db_affected_rows()."</em> fornitori. Ricordati di impostare i loro ".l('referenti',$_GET['q'],array('query' => 'act=admfids')));
		foreach ($mycsv as $k => $fid) {
			log_gas("Tesoriere: Importazione fornitore","NULL",addcslashes($fid[0],"'"));
		}
      }
    }
    unset($_SESSION['fids_import_table']);
  }
}
?>
