<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function html_hero(): void
{

    echo '<div class="card primary">';
    html_border_pieces();

    echo '<section class="hero-marquee">';
    echo '<div class="panel">';

    echo '<div class="columns">';
    echo '<div class="center">';
    echo '<h1>Preserve Voices.</h1><h1>Share History.</h1>';
    echo '<hr>';
    echo '<p>';
    echo 'Dial in from any exhibit phone to leave a message,<br>';
    echo 'listen to stories, and experience the past<br>';
    echo 'through the sound of a simpler time.';
    echo '</p>';
    echo '<a class="button" href="#">';
    echo '<span class="icon microphone"></span>';
    echo 'Learn how it works</a>'; // .card-primary .hero-marquee .panel .columns .button
    echo '</div>'; // .card-primary .hero-marquee .panel .columns .center
    echo '<div class="hero-marquee-1"></div>';
    echo '</div>'; // .card-primary .hero-marquee .panel .columns

    echo '</div>'; // .card-primary .hero-marquee .panel
    echo '</section>'; // .card-primary .hero-marquee

    echo '</div>'; // .card.primary
}
function html_recent(): void
{
    echo '<div class="card secondary">';
    html_border_pieces();
    echo '<h1 class="center">';
    html_span("icon microphone");
    echo 'Recent Contributions</h1>';
    echo '<hr>';

    echo '<div id="recent_contributions" class="table-of-contents"></div>';
    echo '<script type="text/JavaScript">show_recent_contributions("recent_contributions");</script>';

    echo '<hr class="split">';
    echo '</div>'; // .card.secondary
}
html_header(APP_NAME);
$user = current_user();
html_hero();
html_recent();

    echo '<div class="card secondary">';
    html_border_pieces();
    echo get_site_content('homepage_callout');
    echo '</div>'; // .card.secondary

html_footer(); 
?>