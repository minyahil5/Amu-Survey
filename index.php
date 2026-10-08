<?php

$page_title = "AMU Survey System - Your Voice, Our Future"; 
$body_class = "homepage";
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;
$username = $_SESSION['username'] ?? 'Guest';
?>

<style>
    .homepage .hero-section-split {
        display: flex;
        align-items: center;
        padding: 60px 0;
        background-color: #f8f9fa;
        min-height: 70vh;
    }

    .homepage .hero-content {
        flex: 1;
        padding-right: 40px;
        padding-left: 20px;
    }

    .homepage .hero-content .sub-headline {
        font-size: 1.1em;
        color: #0056b3; 
        font-weight: 600;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .homepage .hero-content h1 {
        font-size: 2.8em; 
        color: #003366; 
        margin-bottom: 20px;
        font-weight: 700;
        line-height: 1.2;
    }

    .homepage .hero-content p.lead {
        font-size: 1.15em;
        color: #454545;
        margin-bottom: 30px;
        line-height: 1.7;
    }

    .homepage .hero-image-container {
        flex: 1;
        text-align: center;
        padding-left: 40px;
        padding-right: 20px;
    }

    .homepage .hero-image-container img {
        max-width: 100%;
        height: auto;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .homepage .hero-section-split .cta-buttons .button {
        margin-top: 10px;
        margin-right: 15px;
        padding: 12px 30px;
        font-size: 1.05em;
        border-radius: 25px;
        text-transform: uppercase;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
     .homepage .hero-section-split .cta-buttons .button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }


    .homepage .hero-section-split .cta-buttons .button-primary {
        background-color: #f39c12; 
        color: #003366;
        border: none;
    }
    .homepage .hero-section-split .cta-buttons .button-primary:hover {
        background-color: #e08e0b;
    }

    .homepage .hero-section-split .cta-buttons .button-register {
        background-color: #0056b3; 
        color: white;
        border: none;
    }
    .homepage .hero-section-split .cta-buttons .button-register:hover {
        background-color: #00447c; 
    }


    .homepage .features-section {
        padding: 70px 20px;
        text-align: center;
        background-color: #ffffff;
    }
    .homepage .features-section h2 {
        font-size: 2.4em;
        margin-bottom: 50px;
        color: #333;
        position: relative;
        display: inline-block;
    }
    .homepage .features-section h2::after {
        content: '';
        display: block;
        width: 80px;
        height: 4px;
        background-color: #f39c12; 
        margin: 10px auto 0;
    }

    .homepage .feature-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        justify-content: center;
    }
    .homepage .feature-item {
        background-color: #f8f9fa; 
        padding: 30px;
        border-radius: 8px;
        border: 1px solid #e7e7e7;
        flex-basis: calc(25% - 30px);
        min-width: 280px;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
     .homepage .feature-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .homepage .feature-item .feature-icon {
        font-size: 3em;
        color: #00447c;
        margin-bottom: 20px;
        display: inline-block;
    }
    .homepage .feature-item h3 {
        font-size: 1.3em;
        color: #0056b3;
        margin-bottom: 10px;
    }
    .homepage .feature-item p {
        font-size: 0.95em;
        color: #555;
        line-height: 1.6;
    }

    .homepage .next-steps-section {
        background-color: #00447c;
        color: white;
        padding: 70px 20px;
        text-align: center;
    }
     .homepage .next-steps-section h2 {
        font-size: 2.4em;
        margin-bottom: 25px;
        color: white;
    }
    .homepage .next-steps-section p {
        font-size: 1.15em;
        margin-bottom: 30px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        opacity: 0.9;
    }
    .homepage .next-steps-section .button-primary {
        background-color: #f39c12;
        color: #003366;
        padding: 14px 35px;
        font-size: 1.1em;
    }
     .homepage .next-steps-section .button-primary:hover {
        background-color: #e08e0b;
    }
    .homepage .next-steps-section .button-secondary {
        background-color: transparent;
        color: white;
        border: 2px solid white;
        padding: 12px 33px; 
        font-size: 1.1em;
    }
    .homepage .next-steps-section .button-secondary:hover {
        background-color: white;
        color: #00447c;
    }


    @media (max-width: 992px) {
        .homepage .hero-section-split {
            flex-direction: column;
            text-align: center;
            padding: 40px 20px;
        }
        .homepage .hero-content {
            padding-right: 0;
            padding-left: 0;
            margin-bottom: 40px;
        }
        .homepage .hero-image-container {
            padding-left: 0;
            max-width: 400px;
            margin: 0 auto;
        }
        .homepage .hero-content h1 { font-size: 2.4em; }
        .homepage .hero-content p.lead { font-size: 1.1em; }
    }
    @media (max-width: 768px) {
        .homepage .cta-buttons .button { margin-bottom: 10px; display:block; width: 90%; margin-left:auto; margin-right:auto;}
        .homepage .hero-section-split .cta-buttons .button {
            display: block;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 15px;
        }
        .homepage .hero-section-split .cta-buttons .button:last-child { margin-bottom: 0; }
        .homepage .feature-item { flex-basis: calc(50% - 20px); }
    }
     @media (max-width: 576px) {
        .homepage .feature-item { flex-basis: 100%; }
     }
</style>


<section class="hero-section-split">
    <div class="container" style="display: flex; flex-wrap: wrap; align-items: center;"> 
        <div class="hero-content">
            <p class="sub-headline">AMU Satisfaction Surveys</p>
            <h1>Your Voice Shapes Our University</h1>
            <p class="lead">
                Participate in targeted surveys to share your valuable feedback on academic life, campus facilities, and university services.
                Help AMU continuously improve and create a better environment for all.
            </p>
            <div class="cta-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'dashboard.php'); ?>" class="button button-primary">Go to Dashboard</a>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'respondent/available_surveys.php'); ?>" class="button button-register">View Available Surveys</a> 
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'login.php'); ?>" class="button button-primary">Login & Participate</a>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'register.php'); ?>" class="button button-register">Register Account</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-image-container">
            
            <img src="amu.jpg" alt="Arbaminch University ">
            
        </div>
    </div>
</section>


<section class="features-section">
    <div class="container">
        <h2>Key Benefits of Your Participation</h2>
        <div class="feature-grid">
            <div class="feature-item">
                <div class="feature-icon">📈</div> 
                <h3>Drive Improvement</h3>
                <p>Your feedback is instrumental in identifying areas for enhancement across academics, infrastructure, and student support services.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📢</div>
                <h3>Make Your Voice Heard</h3>
                <p>This is your direct channel to communicate your experiences and suggestions to the university administration.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🤝</div>
                <h3>Foster Community</h3>
                <p>By sharing diverse perspectives, we collectively contribute to a more inclusive and responsive university environment.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💡</div>
                <h3>Shape Policy</h3>
                <p>Data-driven insights from surveys help inform decision-making and shape future policies at AMU.</p>
            </div>
        </div>
    </div>
</section>


 

<?php
require_once __DIR__ . '/includes/footer.php';
?>