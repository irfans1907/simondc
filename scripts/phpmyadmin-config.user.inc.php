<?php

/**
 * Extra phpMyAdmin configuration for the local Docker stack.
 *
 * Mounted to /etc/phpmyadmin/config.user.inc.php by compose.yaml.
 * phpMyAdmin only listens on localhost:8080, so allowing the
 * DROP DATABASE statement from the UI is acceptable here.
 */
$cfg['AllowUserDropDatabase'] = true;
