
<?php
 $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$email = "contact@camdigit.com";
$phone = "(+237) 696 77 00 74";
include_once "./components/header.php";
require "Router.php";

$router = new Router();

$router->add("/", function () use (&$pageTitle) {
    $pageTitle = "Home";
    include_once "./components/home.php";
});
$router->add("/domain", function () {
    include_once "./components/domain.php";
});
$router->add("/shared-hosting", function () {
    include_once "./components/shared-hosting.php";
});
$router->add("/dedicated-hosting", function () {
    include_once "./components/dedicated-hosting.php";
});
$router->add("/reseller-hosting", function () {
    include_once "./components/reseller-hosting.php";
});
$router->add("/vps-hosting", function () {
    include_once "./components/vps-hosting.php";
});
$router->add("/wordpress-hosting", function () {
    include_once "./components/wordpress-hosting.php";
});
$router->add("/cloud-hosting", function () {
    include_once "./components/cloud-hosting.php";
});
$router->add("/about-us", function () {
    include_once "./components/about.php";
});
$router->add("/contact-us", function () {
    include_once "./components/contact.php";
});
$router->add("/faq", function () {
    include_once "./components/faq.php";
});
$router->add("/support", function () {
    include_once "./components/support.php";
});
$router->add("/pricing", function () {
    include_once "./components/pricing.php";
});
$router->add("/news", function () {
    include_once "./components/news.php";
});
$router->add("/news-details", function () {
    include_once "./components/news-details.php";
});
$router->dispatch($path);

include_once "./components/footer.php";
