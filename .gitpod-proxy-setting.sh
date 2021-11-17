cat <<PROXY > web/sites/default/settings.php

$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_REAL_IP']];

PROXY

