<?php
// claim.php now redirects to owner_portal.php
// This maintains backward compatibility
header('Location: /owner_portal.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;