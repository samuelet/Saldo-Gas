#DROP TABLE IF EXISTS mg_ordine_gas;
#DROP TABLE IF EXISTS mg_versamento_gas;
#DROP TABLE IF EXISTS mg_credito_gas;
#DROP TABLE IF EXISTS mg_fidpagamento_gas;
#DROP TABLE IF EXISTS utente_gas;
#DROP TABLE IF EXISTS mg_fornitore_gas;
#DROP TABLE IF EXISTS mg_log_gas;
#DROP TABLE IF EXISTS mg_subreferente_gas;
#DROP TABLE IF EXISTS mg_roles_gas;
#DROP TABLE IF EXISTS mg_pwds_gas;

CREATE TABLE IF NOT EXISTS mg_ordine_gas (
       oid INT(10) unsigned NOT NULL auto_increment,
       odata DATE NOT NULL,
       ouid INT(10) unsigned NOT NULL,
       ofid INT(10) unsigned NOT NULL,
       -- Spesa (teorico importato da economia solidale) o versamento
       osaldo DECIMAL(6,2) NOT NULL DEFAULT 0,
       -- Validazione (a cura del cassiere e tesoriere)
       ovalid BOOLEAN NOT NULL DEFAULT 0,
       -- Lock (a cura del tesoriere)
       olock BOOLEAN NOT NULL DEFAULT 0,
       -- Ultimo utente ad aver modificato il campo 
       lastduid INT(10) unsigned NOT NULL,
       otime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (oid),
       UNIQUE KEY (odata,ouid,ofid),
       KEY (ouid,odata,osaldo),
       KEY (ofid),
       KEY (ouid),
       KEY (otime)
);

CREATE TABLE IF NOT EXISTS mg_versamento_gas (
       vid INT(10) unsigned NOT NULL auto_increment,
       vuid INT(10) unsigned,
       vsaldo DECIMAL(6,2) NOT NULL DEFAULT 0,
       vlastduid INT(10) unsigned NOT NULL,
       ltime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (vid),
       KEY (vuid,ltime),
       KEY (vuid,vsaldo)
);

CREATE TABLE IF NOT EXISTS mg_subreferente_gas (
       ruid INT(10) unsigned NOT NULL,
       rfid INT(10) unsigned NOT NULL,
       KEY (rfid)
);

CREATE TABLE IF NOT EXISTS mg_fidpagamento_gas (
       fpid INT(10) unsigned NOT NULL auto_increment,
       -- Da controllare
       fpfid INT(10) unsigned,
       fpsaldo DECIMAL(6,2) NOT NULL DEFAULT 0,
       fplastduid INT(10) unsigned NOT NULL,
       fpnote VARCHAR(255),
       fpltime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (fpid),
       KEY (fpltime,fpnote,fpsaldo),
       KEY (fpfid,fpltime)
);

CREATE TABLE IF NOT EXISTS mg_fornitore_gas (
       fid INT(10) unsigned NOT NULL auto_increment,
       fnome VARCHAR(255),
       frefer INT(10) unsigned DEFAULT 0,
       PRIMARY KEY (fid),
       UNIQUE KEY (fnome),
       key (frefer)
);

CREATE TABLE IF NOT EXISTS utente_gas (
       -- Utilizzo l'id di economia solidale
       uid INT(10) unsigned NOT NULL,
       unome VARCHAR(255) DEFAULT '',
       email VARCHAR(255) NOT NULL,
       urole VARCHAR(255) NOT NULL DEFAULT '',
       ugroup VARCHAR(255) NOT NULL DEFAULT '',
       PRIMARY KEY (uid),
       KEY (email),
       KEY (unome)
);

CREATE TABLE IF NOT EXISTS mg_log_gas (
       lid INT(10) unsigned NOT NULL auto_increment,
       drupalid INT(10) unsigned NOT NULL,
       -- 1 Versamento, 2 Modifica ordine, 3 Inserimeto fuori ordine, 4 Importazione utenti, 5 Importazione fornitori, 6 Importazione ordine (punto consegna), 7 Impostazione Referente, 8 Spesa fornitore, 9 Pagamento fornitore, 10 Eliminazione movimento, 11 Impostazione Referenti secondari;
       lact VARCHAR(255) DEFAULT '',
       ldate DATE DEFAULT NULL,
       -- Note extra. In caso di assegnazione Referenti: id del Fornitore modificato. In caso di eliminazione: 1 versamento, 2 ordine, 8 Spesa, 9 Pagamento
       lextra TEXT,
       ltime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (lid),
       key (ltime)
);

CREATE TABLE IF NOT EXISTS mg_roles_gas (
       ruid INT(10) unsigned NOT NULL,
       rrole INT(2) unsigned NOT NULL,
       KEY(ruid)
);

CREATE TABLE IF NOT EXISTS mg_pwds_gas (
       -- 1 importazione ordini (punto consegna), 2 importazione utenti
       ptype INT(2) unsigned NOT NULL DEFAULT 1,
       puser VARCHAR(255) DEFAULT '',
       ppwd VARCHAR(255) DEFAULT '',
       PRIMARY KEY(ptype)
);

CREATE TABLE IF NOT EXISTS mg_credito_gas (
       sid INT(10) unsigned NOT NULL auto_increment,
       -- Da controllare
       suid INT(10) unsigned,
       ssaldo DECIMAL(6,2) NOT NULL DEFAULT 0,
       stype INT(2) unsigned NOT NULL DEFAULT 1,
       snote VARCHAR(255),
       slastduid INT(10) unsigned NOT NULL,
       sltime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (sid),
       KEY (stype),
       KEY (suid,sltime)
);

