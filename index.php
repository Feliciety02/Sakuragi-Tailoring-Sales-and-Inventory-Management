<?php
require_once __DIR__ . '/config/session_handler.php';

if (is_logged_in()) {
    redirect_if_logged_in();
} else {
    require __DIR__ . '/public/landing_page.php';
}
