CREATE VIEW `messages_view` AS
  SELECT 
    `name`,
    `message`,
    GROUP_CONCAT(`correlation_id` SEPARATOR ', ') AS `correlation_ids`,
    `user_agent`,
    COUNT(1) AS `messages_count`,
    MAX(`m`.`created_at`) AS `created_at_most_recent`
  FROM `messages` AS `m` 
  INNER JOIN `applications` AS `a` 
  ON `a`.`id` = `application_id`
  GROUP BY `application_id`, `class`, `function`, `file`, `line`, `message`, `user_agent`;
