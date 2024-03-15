ALTER TABLE `errors` 
  ADD `object` text NULL AFTER `line`,
  ADD `type` varchar(255) NULL AFTER `object`,
  ADD `args` text NULL AFTER `type`,
  ADD `details` text NULL AFTER `message`,
  CHANGE `method` `function` VARCHAR(255) NOT NULL;
