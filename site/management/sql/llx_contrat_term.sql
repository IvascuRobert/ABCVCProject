CREATE TABLE  `llx_contrat_term` (
	`rowid`			INT( 11 ) NOT NULL AUTO_INCREMENT ,
	`tms`			TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ,
	`datec`			DATETIME DEFAULT NULL ,
	`fk_contrat`	INT( 11 ) DEFAULT NULL ,
	`datedeb`		DATETIME DEFAULT NULL ,
	`datefin`		DATETIME DEFAULT NULL ,
	`note`			TEXT,
	`fk_status`		SMALLINT NOT NULL DEFAULT 0,
PRIMARY KEY (  `rowid` ) ,
UNIQUE KEY  `uk_contrat_term` (  `fk_contrat` ,  `datedeb` )
) ENGINE = INNODB DEFAULT CHARSET = latin1;