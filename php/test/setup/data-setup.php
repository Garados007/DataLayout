<?php namespace Test\TestEnvironment\Setup;

require_once __DIR__ . '/../../../lib/script/db.php';

//create tables and add foreign keys
\DB::getMultiResult( ''
    . 'CREATE TABLE IF NOT EXISTS `test_ObjectA` (' . PHP_EOL
    . '    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' . PHP_EOL
    . '    , `par1` INT UNSIGNED NOT NULL DEFAULT 0 UNIQUE' . PHP_EOL
    . '    , `par2` INT UNSIGNED NOT NULL DEFAULT 0 UNIQUE' . PHP_EOL
    . '    , `par3` TEXT' . PHP_EOL
    . '    , `json` JSON NOT NULL' . PHP_EOL
    . '    , `created` DATETIME NOT NULL' . PHP_EOL
    . '    , `parent` BIGINT UNSIGNED' . PHP_EOL
    . ');' . PHP_EOL
    . 'CREATE TABLE IF NOT EXISTS `test_ObjectB` (' . PHP_EOL
    . '    id BIGINT UNSIGNED NOT NULL PRIMARY KEY' . PHP_EOL
    . '    , `ext` BOOLEAN NOT NULL DEFAULT FALSE' . PHP_EOL
    . '    , `leaf` BIGINT UNSIGNED' . PHP_EOL
    . ');' . PHP_EOL
    . 'ALTER TABLE `test_ObjectA`' . PHP_EOL
    . '    ADD CONSTRAINT `test__FK_parent`' . PHP_EOL
    . '    FOREIGN KEY (`parent`)' . PHP_EOL
    . '    REFERENCES `test_ObjectA`(`id`)' . PHP_EOL
    . '    ON DELETE CASCADE;' . PHP_EOL
    . 'ALTER TABLE `test_ObjectB`' . PHP_EOL
    . '    ADD CONSTRAINT `test__FK_ID`' . PHP_EOL
    . '    FOREIGN KEY (id)' . PHP_EOL
    . '    REFERENCES `test_ObjectA`(id)' . PHP_EOL
    . '    ON DELETE CASCADE;' . PHP_EOL
    . 'ALTER TABLE `test_ObjectB`' . PHP_EOL
    . '    ADD CONSTRAINT `test__FK_leaf`' . PHP_EOL
    . '    FOREIGN KEY (`leaf`)' . PHP_EOL
    . '    REFERENCES `test_ObjectA`(`id`)' . PHP_EOL
    . '    ON DELETE CASCADE;' . PHP_EOL
)->executeAll();
