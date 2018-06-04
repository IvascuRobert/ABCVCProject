ALTER TABLE llx_fichinterdet ADD `fk_unit` int(11) NULL DEFAULT NULL;

--
ALTER TABLE llx_projet_task ADD `billingmode` int(11) NULL DEFAULT NULL;
ALTER TABLE llx_projet_task ADD `fk_product` int(11) NULL DEFAULT NULL;
ALTER TABLE llx_projet_task ADD `average_thm` DOUBLE(24,8) NULL DEFAULT NULL;

--- a replacer dans le bon ordre plus tard 
ALTER TABLE llx_projet_task_time ADD `date_pause` datetime NULL DEFAULT NULL;
ALTER TABLE llx_projet_task_time ADD `date_start` datetime NULL DEFAULT NULL;
ALTER TABLE llx_projet_task_time ADD `date_end` datetime NULL DEFAULT NULL;

--
ALTER TABLE llx_fichinter ADD `datee` datetime NULL DEFAULT NULL;
ALTER TABLE llx_fichinter ADD `dateo` datetime NULL DEFAULT NULL;
ALTER TABLE llx_fichinter ADD `fulldayevent` smallint(6) NULL DEFAULT NULL;
ALTER TABLE llx_fichinter ADD `total_ht` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinter ADD `total_ttc` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinter ADD `total_tva` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinter ADD `total_localtax1` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinter ADD `total_localtax2` DOUBLE(24,8) NULL DEFAULT NULL;
--
ALTER TABLE llx_fichinterdet ADD `total_ht` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `subprice` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `fk_parent_line` int(11) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `fk_product` int(11) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `label` varchar(255) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `tva_tx` DOUBLE(6,3) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `localtax1_tx` DOUBLE(6,3) NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `localtax1_type` VARCHAR(1) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `localtax2_tx` DOUBLE(6,3) NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `localtax2_type` VARCHAR(1) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `qty` double NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `remise_percent` double NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `remise` double NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `fk_remise_except` int(11) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `price` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `total_tva` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `total_localtax1` DOUBLE(24,8) NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `total_localtax2` DOUBLE(24,8) NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `total_ttc` DOUBLE(24,8) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `product_type` INT(11) NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `date_start` datetime NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `date_end` datetime NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `info_bits` INT(11) NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `buy_price_ht` DOUBLE(24,8) NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `fk_product_fournisseur_price` int(11) NULL DEFAULT NULL;
ALTER TABLE llx_fichinterdet ADD `fk_code_ventilation` int(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `fk_export_commpta` int(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `special_code` int(10) UNSIGNED NULL DEFAULT 0;
ALTER TABLE llx_fichinterdet ADD `import_key` varchar(14) NULL DEFAULT NULL;