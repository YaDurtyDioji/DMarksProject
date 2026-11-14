<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Services.php';
require_once __DIR__ . '/Gallery.php';
require_once __DIR__ . '/Testimonials.php';
require_once __DIR__ . '/AboutPage.php';
require_once __DIR__ . '/ContactPage.php';
require_once __DIR__ . '/Messages.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'services':    jexit(Services::publicList());
        case 'gallery':     jexit(Gallery::publicGallery());
        case 'testimonials':jexit(Testimonials::publicList());
        case 'about':       jexit(AboutPage::publicData());
        case 'contact_page':jexit(ContactPage::publicData());
        case 'contact_submit':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(['ok'=>false,'error'=>'POST only']);
            $in = jbody();
            if (!empty($in['website'])) jexit(['ok'=>true]); // honeypot
            if (empty($in['name']) || empty($in['email']) || empty($in['message'])) jexit(['ok'=>false,'error'=>'Missing fields']);
            Messages::create(
                substr($in['name'], 0, 255),
                substr($in['email'], 0, 255),
                substr($in['subject'] ?? '', 0, 255),
                substr($in['message'], 0, 5000)
            );
            jexit(['ok'=>true]);
    }
    jexit(['ok'=>false,'error'=>'Unknown action']);
} catch (Exception $e) {
    jexit(['ok'=>false,'error'=>$e->getMessage()]);
}