<?php
session_start();


$websiteUrl = "http://localhost:8000";


$whatsappNumber = "254718537598";

$whatsappMessage = "Hello, I visited the Campus Hostel Booking System website. Please can you help me accomodate?";

$whatsappLink = "https://wa.me/" . $whatsappNumber
    . "?text=" . rawurlencode($whatsappMessage);

$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data="
    . urlencode($websiteUrl);

$socialLinks = [
    "facebook" => "https://www.facebook.com/profile.php?id=61559866246353",
    "instagram" => "https://www.instagram.com/errickorashi/",
    "tiktok" => "https://www.tiktok.com/@errickorashy",
    "x" => "https://x.com/Ousmatar",
    "youtube" => "https://youtube.com/@rauldon-x5g"
];

$isLoggedIn = !empty($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? '';

$dashboardUrl = '#';

if ($isLoggedIn) {
    if ($userRole === 'student') {
        $dashboardUrl = 'student/dashboard.php';
    } elseif ($userRole === 'officer') {
        $dashboardUrl = 'officer/dashboard.php';
    } elseif ($userRole === 'caretaker') {
        $dashboardUrl = 'caretaker/dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Campus Hostel Booking System</title>

    <meta name="description"
          content="Campus Hostel Booking System - Apply for accommodation, manage hostel bookings and access student accommodation services.">

    <style>

        :root {
            --navy: #071525;
            --navy-light: #102638;
            --green: #70e000;
            --white: #ffffff;
            --text: #172334;
            --muted: #64748b;
            --light: #f4f7fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--light);
            color: var(--text);
        }

        a {
            text-decoration: none;
        }


        nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            min-height: 82px;
            padding: 12px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--navy);
            border-bottom: 2px solid var(--green);
            box-shadow: 0 5px 20px rgba(0,0,0,.25);
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }

        .brand-logo {
            width: 55px;
            height: 55px;
            flex-shrink: 0;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .brand-text strong {
            font-size: 21px;
            letter-spacing: .2px;
        }

        .brand-text span {
            color: var(--green);
            font-size: 13px;
            margin-top: 4px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-links a {
            color: #e8eef5;
            font-size: 15px;
            transition: .3s;
        }

        .nav-links a:hover {
            color: var(--green);
        }

        .nav-links .register-link {
            background: var(--green);
            color: var(--navy);
            padding: 12px 23px;
            font-weight: 700;
            border: 1px solid var(--green);
        }

        .nav-links .register-link:hover {
            background: transparent;
            color: var(--green);
        }

        .menu-toggle {
            display: none;
            color: white;
            font-size: 28px;
            background: none;
            border: none;
            cursor: pointer;
        }


        .hero {
            min-height: 680px;
            padding: 145px 7% 90px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(
                    90deg,
                    rgba(7,21,37,.97) 0%,
                    rgba(7,21,37,.87) 45%,
                    rgba(7,21,37,.28) 100%
                ),
                url("https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=1900&q=85");
            background-position: center;
            background-size: cover;
        }

        .hero-content {
            max-width: 700px;
            color: white;
            position: relative;
            z-index: 2;
        }

        .hero-label {
            display: inline-block;
            color: var(--green);
            border: 1px solid rgba(112,224,0,.6);
            padding: 9px 15px;
            margin-bottom: 25px;
            font-size: 13px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .hero h1 {
            font-size: clamp(42px, 6vw, 72px);
            line-height: 1.08;
            margin-bottom: 23px;
            font-weight: 800;
        }

        .hero h1 span {
            color: var(--green);
        }

        .hero p {
            max-width: 580px;
            color: #d9e3ed;
            font-size: 19px;
            line-height: 1.7;
            margin-bottom: 35px;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn {
            display: inline-block;
            padding: 15px 27px;
            border: 1px solid var(--green);
            font-size: 15px;
            font-weight: 700;
            transition: .3s;
        }

        .btn-primary {
            background: var(--green);
            color: var(--navy);
        }

        .btn-primary:hover {
            background: #5dbd00;
        }

        .btn-secondary {
            background: #000;
            color: white;
        }

        .btn-secondary:hover {
            background: #182b3c;
            color: var(--green);
        }


        .features {
            padding: 85px 7%;
            background: white;
        }

        .section-heading {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 48px;
        }

        .section-heading span {
            color: #4c9c00;
            text-transform: uppercase;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .section-heading h2 {
            color: var(--navy);
            font-size: 36px;
            margin: 12px 0;
        }

        .section-heading p {
            color: var(--muted);
            line-height: 1.7;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
        }

        .feature-card {
            background: white;
            padding: 32px 23px;
            border: 1px solid #e2e8f0;
            border-bottom: 4px solid var(--green);
            box-shadow: 0 7px 22px rgba(0,0,0,.06);
            transition: .3s;
        }

        .feature-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 12px 28px rgba(0,0,0,.12);
        }

        .feature-icon {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #effbe6;
            color: #397d00;
            font-size: 27px;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            color: var(--navy);
            margin-bottom: 12px;
            font-size: 19px;
        }

        .feature-card p {
            color: var(--muted);
            line-height: 1.6;
            font-size: 14px;
        }

        /* HOW IT WORKS */

        .how-it-works {
            padding: 85px 7%;
            background: #edf2f6;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .step {
            background: white;
            padding: 32px;
            border: 1px solid #dce5ed;
        }

        .step-number {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--navy);
            color: var(--green);
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .step h3 {
            color: var(--navy);
            margin-bottom: 12px;
        }

        .step p {
            color: var(--muted);
            line-height: 1.7;
        }


        .qr-section {
            padding: 75px 7%;
            background: white;
        }

        .qr-container {
            max-width: 1050px;
            margin: auto;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            background: var(--navy);
            color: white;
            border-left: 6px solid var(--green);
        }

        .qr-text {
            max-width: 580px;
        }

        .qr-text h2 {
            font-size: 30px;
            margin-bottom: 16px;
        }

        .qr-text h2 span {
            color: var(--green);
        }

        .qr-text p {
            color: #cbd5e1;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .qr-note {
            color: var(--green);
            font-size: 13px;
        }

        .qr-card {
            background: white;
            color: var(--navy);
            padding: 22px;
            text-align: center;
            min-width: 245px;
        }

        .qr-card h3 {
            margin-bottom: 7px;
        }

        .qr-card p {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 15px;
        }

        .qr-card img {
            width: 190px;
            height: 190px;
            object-fit: contain;
            display: block;
            margin: auto;
        }

        .qr-card small {
            display: block;
            margin-top: 12px;
            color: var(--muted);
            overflow-wrap: anywhere;
        }


        .cta {
            padding: 75px 7%;
            background: #eaf4e2;
            text-align: center;
        }

        .cta h2 {
            color: var(--navy);
            font-size: 34px;
            margin-bottom: 15px;
        }

        .cta p {
            color: var(--muted);
            margin-bottom: 25px;
            line-height: 1.7;
        }

        /* FOOTER */

        footer {
            background: var(--navy);
            color: white;
            padding: 50px 7% 20px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            gap: 35px;
            flex-wrap: wrap;
            padding-bottom: 30px;
        }

        .footer-about {
            max-width: 390px;
        }

        .footer-about p {
            color: #aebdca;
            line-height: 1.7;
            margin-top: 15px;
            font-size: 14px;
        }

        .footer-links h3 {
            color: var(--green);
            margin-bottom: 15px;
        }

        .footer-links a {
            display: block;
            color: #cbd5e1;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .footer-links a:hover {
            color: var(--green);
        }


        .social-section {
            max-width: 1100px;
            margin: 10px auto 30px;
            padding: 30px 0;
            border-top: 1px solid #314354;
            border-bottom: 1px solid #314354;
        }

        .social-section h3 {
            color: white;
            text-align: center;
            font-size: 23px;
            margin-bottom: 10px;
        }

        .social-section > p {
            color: #aebdca;
            text-align: center;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .social-icons {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .social-link {
            width: 52px;
            height: 52px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #182e41;
            border: 1px solid #3a5061;
            color: white;
            transition: .3s;
        }

        .social-link svg {
            width: 25px;
            height: 25px;
            fill: currentColor;
        }

        .social-link:hover {
            background: var(--green);
            color: var(--navy);
            border-color: var(--green);
            transform: translateY(-5px);
        }

        .social-link.whatsapp:hover {
            background: #25D366;
            border-color: #25D366;
        }

        .social-link.facebook:hover {
            background: #1877F2;
            border-color: #1877F2;
            color: white;
        }

        .social-link.instagram:hover {
            background: #E4405F;
            border-color: #E4405F;
            color: white;
        }

        .social-link.tiktok:hover {
            background: #000000;
            border-color: white;
            color: white;
        }

        .social-link.youtube:hover {
            background: #FF0000;
            border-color: #FF0000;
            color: white;
        }

        .social-link.x-social:hover {
            background: #000000;
            border-color: white;
            color: white;
        }

        .social-link:focus-visible,
        .whatsapp-float:focus-visible {
            outline: 3px solid var(--green);
            outline-offset: 4px;
        }

        .copyright {
            padding-top: 20px;
            text-align: center;
            color: #aebdca;
            font-size: 13px;
        }


        .whatsapp-float {
            position: fixed;
            left: 25px;
            bottom: 25px;
            width: 60px;
            height: 60px;
            background: #25D366;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1100;
            border-radius: 50%;
            box-shadow: 0 5px 20px rgba(0,0,0,.3);
            transition: .3s;
        }

        .whatsapp-float:hover {
            transform: scale(1.1);
            background: #1ebe5d;
        }

        .whatsapp-float svg {
            width: 33px;
            height: 33px;
            fill: currentColor;
        }

        .whatsapp-tooltip {
            position: absolute;
            left: 72px;
            background: var(--navy);
            color: white;
            padding: 9px 13px;
            white-space: nowrap;
            font-size: 13px;
            opacity: 0;
            visibility: hidden;
            transition: .3s;
        }

        .whatsapp-float:hover .whatsapp-tooltip {
            opacity: 1;
            visibility: visible;
        }


        .chat-button {
            position: fixed;
            right: 25px;
            bottom: 25px;
            width: 60px;
            height: 60px;
            border: none;
            background: var(--green);
            color: var(--navy);
            font-size: 27px;
            cursor: pointer;
            z-index: 1100;
            box-shadow: 0 5px 20px rgba(0,0,0,.3);
            border-radius: 50%;
        }

        .chat-window {
            display: none;
            position: fixed;
            right: 25px;
            bottom: 100px;
            width: 340px;
            max-width: calc(100% - 30px);
            height: 440px;
            background: white;
            box-shadow: 0 8px 35px rgba(0,0,0,.25);
            z-index: 1100;
            flex-direction: column;
            border: 1px solid #dce5ed;
        }

        .chat-header {
            background: var(--navy);
            color: white;
            padding: 17px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header strong {
            color: var(--green);
        }

        .chat-header button {
            background: none;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
        }

        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f5f7fa;
        }

        .bot-message,
        .user-message {
            padding: 11px 13px;
            margin-bottom: 12px;
            max-width: 88%;
            font-size: 13px;
            line-height: 1.5;
        }

        .bot-message {
            background: white;
            border: 1px solid #e2e8f0;
            color: var(--text);
        }

        .user-message {
            background: var(--navy);
            color: white;
            margin-left: auto;
        }

        .chat-form {
            display: flex;
            padding: 12px;
            border-top: 1px solid #e2e8f0;
            gap: 8px;
        }

        .chat-form input {
            flex: 1;
            min-width: 0;
            padding: 11px;
            border: 1px solid #cbd5e1;
            outline: none;
        }

        .chat-form button {
            padding: 0 16px;
            border: none;
            background: var(--green);
            color: var(--navy);
            font-weight: 700;
            cursor: pointer;
        }


        @media (max-width: 950px) {
            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .nav-links {
                gap: 15px;
            }
        }

        @media (max-width: 750px) {

            nav {
                padding: 12px 5%;
            }

            .menu-toggle {
                display: block;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 82px;
                left: 0;
                width: 100%;
                background: var(--navy);
                padding: 20px 6%;
                flex-direction: column;
                align-items: flex-start;
                border-bottom: 2px solid var(--green);
            }

            .nav-links.active {
                display: flex;
            }

            .hero {
                min-height: 600px;
                padding: 140px 6% 75px;
                background-position: center;
            }

            .hero h1 {
                font-size: 45px;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .qr-container {
                flex-direction: column;
                align-items: flex-start;
                padding: 28px;
            }

            .qr-card {
                align-self: center;
                width: 100%;
            }
        }

        @media (max-width: 500px) {

            .brand-text strong {
                font-size: 16px;
            }

            .brand-text span {
                font-size: 11px;
            }

            .brand-logo {
                width: 45px;
                height: 45px;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .section-heading h2 {
                font-size: 28px;
            }

            .hero h1 {
                font-size: 39px;
            }

            .hero p {
                font-size: 16px;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn {
                text-align: center;
            }

            .chat-window {
                right: 15px;
                bottom: 90px;
            }

            .chat-button {
                right: 15px;
                bottom: 15px;
            }

            .whatsapp-float {
                left: 15px;
                bottom: 15px;
                width: 54px;
                height: 54px;
            }

            .social-icons {
                gap: 12px;
            }

            .social-link {
                width: 47px;
                height: 47px;
            }
        }

    </style>

</head>

<body>


<nav>

    <a href="index.php" class="brand">


       <img src="campus-hostel-logo.png"
     alt="Campus Hostel Logo"
     class="brand-logo">

        <div class="brand-text">
            <strong>Campus Hostel</strong>
            <span>BOOKING SYSTEM</span>
        </div>
        

    </a>

    <button class="menu-toggle"
            id="menuToggle"
            aria-label="Toggle navigation">☰</button>

    <div class="nav-links" id="navLinks">

        <a href="index.php">Home</a>
        <a href="#features">Features</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#contact">Contact</a>

        <?php if ($isLoggedIn): ?>

            <a href="<?php echo htmlspecialchars($dashboardUrl); ?>">
                Dashboard
            </a>

            <a href="auth/logout.php">Logout</a>

        <?php else: ?>

            <a href="auth/login.php">Login</a>

            <a href="auth/register.php" class="register-link">
                Register
            </a>

        <?php endif; ?>

    </div>

</nav>



<section class="hero">

    <div class="hero-content">

        <span class="hero-label">
            Your Campus Accommodation Partner
        </span>

        <h1>
            Find Your Home<br>
            on <span>Campus.</span>
        </h1>

        <p>
            Book your hostel easily, manage your accommodation,
            submit requests and enjoy a better campus living experience.
        </p>

        <div class="hero-buttons">

            <?php if ($isLoggedIn): ?>

                <a href="<?php echo htmlspecialchars($dashboardUrl); ?>"
                   class="btn btn-primary">
                    Open Dashboard →
                </a>

            <?php else: ?>

                <a href="auth/register.php" class="btn btn-primary">
                    Get Started →
                </a>

            <?php endif; ?>

            <a href="#features" class="btn btn-secondary">
                Explore System
            </a>

        </div>

    </div>

</section>



<section class="features" id="features">

    <div class="section-heading">

        <span>What We Offer</span>

        <h2>Everything You Need in One Place</h2>

        <p>
            A convenient platform designed to simplify hostel
            accommodation management for students and university staff.
        </p>

    </div>

    <div class="feature-grid">

        <div class="feature-card">

            <div class="feature-icon">⌂</div>

            <h3>Easy Booking</h3>

            <p>
                Submit hostel applications and follow your
                accommodation application status online.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">✓</div>

            <h3>Secure Access</h3>

            <p>
                Secure login and role-based access for students,
                accommodation officers and caretakers.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">♧</div>

            <h3>Student Friendly</h3>

            <p>
                Manage your room allocation, profile, transfers
                and vacation requests conveniently.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">☏</div>

            <h3>Support Assistant</h3>

            <p>
                Get quick guidance on applications, accommodation
                and the main services available in the system.
            </p>

        </div>

    </div>

</section>



<section class="how-it-works" id="how-it-works">

    <div class="section-heading">

        <span>Simple Process</span>

        <h2>How It Works</h2>

        <p>
            Getting your campus accommodation is simple.
            Follow these three steps.
        </p>

    </div>

    <div class="steps">

        <div class="step">

            <div class="step-number">01</div>

            <h3>Create Your Account</h3>

            <p>
                Register as a student and securely access your
                personal hostel booking dashboard.
            </p>

        </div>

        <div class="step">

            <div class="step-number">02</div>

            <h3>Apply for Accommodation</h3>

            <p>
                Submit your hostel application and select
                your preferred accommodation hall.
            </p>

        </div>

        <div class="step">

            <div class="step-number">03</div>

            <h3>Get Your Allocation</h3>

            <p>
                Follow your application and view your allocated
                room once the officer completes the process.
            </p>

        </div>

    </div>

</section>



<section class="qr-section" id="qr-access">

    <div class="qr-container">

        <div class="qr-text">

            <h2>
                Access Campus Hostel<br>
                <span>Anywhere, Anytime.</span>
            </h2>

            <p>
                Scan the QR code using your smartphone camera
                to open the Campus Hostel Booking System.
                Access the platform quickly without typing
                the website address.
            </p>

            <p class="qr-note">
                QR destination:
                <?php echo htmlspecialchars($websiteUrl); ?>
            </p>

        </div>

        <div class="qr-card">

            <h3>Scan to Access</h3>

            <p>Open the system on your phone</p>

            <img
                src="<?php echo htmlspecialchars($qrCodeUrl); ?>"
                alt="QR code for Campus Hostel Booking System"
                loading="lazy"
            >

            <small>Campus Hostel Booking System</small>

        </div>

    </div>

</section>



<section class="cta">

    <h2>Ready to Find Your Campus Home?</h2>

    <p>
        Join the Campus Hostel Booking System and manage
        your accommodation with ease.
    </p>

    <?php if ($isLoggedIn): ?>

        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>"
           class="btn btn-primary">
            Go to Dashboard
        </a>

    <?php else: ?>

        <a href="auth/register.php" class="btn btn-primary">
            Register Now →
        </a>

    <?php endif; ?>

</section>



<footer id="contact">

    <div class="footer-content">

        <div class="footer-about">

            <a href="index.php" class="brand">

                <svg class="brand-logo"
                     viewBox="0 0 100 100"
                     xmlns="http://www.w3.org/2000/svg"
                     aria-label="Campus Hostel logo">

                    <path d="M8 43 L50 8 L92 43"
                          fill="none"
                          stroke="#70e000"
                          stroke-width="8"/>

                    <path d="M19 42 V86 H81 V42"
                          fill="none"
                          stroke="#ffffff"
                          stroke-width="7"/>

                    <rect x="39" y="54" width="22" height="32"
                          fill="#70e000"/>

                    <path d="M5 91 H95"
                          stroke="#70e000"
                          stroke-width="5"/>

                </svg>

                <div class="brand-text">
                    <strong>Campus Hostel</strong>
                    <span>BOOKING SYSTEM</span>
                </div>

            </a>

            <p>
                Making campus accommodation easier through
                a simple, secure and convenient hostel
                booking and management platform.
            </p>

        </div>


        <div class="footer-links">

            <h3>Quick Links</h3>

            <a href="index.php">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="auth/login.php">Login</a>
            <a href="auth/register.php">Register</a>

        </div>


        <div class="footer-links">

            <h3>Student Services</h3>

            <a href="auth/login.php">Hostel Application</a>
            <a href="auth/login.php">Room Allocation</a>
            <a href="auth/login.php">Transfer Request</a>
            <a href="auth/login.php">Vacation Request</a>

        </div>

    </div>



    <div class="social-section">

        <h3>Connect With Us</h3>

        <p>
            Follow us on social media or contact us directly.
            We would love to hear from you!
        </p>

        <div class="social-icons">

            <!-- WHATSAPP -->

            <a href="<?php echo htmlspecialchars($whatsappLink); ?>"
               class="social-link whatsapp"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="Contact us on WhatsApp"
               title="WhatsApp">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20.52 3.48A11.8 11.8 0 0 0 12.1 0C5.55 0 .22 5.33.22 11.88c0 2.09.55 4.13 1.59 5.93L.12 24l6.35-1.66a11.86 11.86 0 0 0 5.63 1.43h.01c6.55 0 11.88-5.33 11.88-11.88 0-3.17-1.23-6.15-3.47-8.41ZM12.1 21.75a9.85 9.85 0 0 1-5.02-1.38l-.36-.21-3.77.99 1.01-3.67-.24-.38a9.82 9.82 0 0 1-1.51-5.22c0-5.45 4.44-9.89 9.9-9.89 2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.89-9.89 9.89Zm5.43-7.4c-.3-.15-1.78-.88-2.06-.98-.28-.1-.48-.15-.68.15-.2.3-.78.98-.96 1.18-.18.2-.35.22-.65.07-.3-.15-1.26-.47-2.4-1.48-.89-.8-1.49-1.78-1.67-2.08-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.68-1.64-.93-2.24-.24-.58-.49-.5-.68-.51l-.58-.01c-.2 0-.52.07-.8.37-.27.3-1.05 1.03-1.05 2.52s1.08 2.92 1.23 3.12c.15.2 2.12 3.24 5.13 4.54.72.31 1.28.5 1.72.64.72.23 1.37.2 1.89.12.58-.09 1.78-.73 2.03-1.44.25-.7.25-1.31.17-1.44-.08-.12-.28-.2-.58-.35Z"/>
                </svg>

            </a>



            <a href="<?php echo htmlspecialchars($socialLinks['facebook']); ?>"
               class="social-link facebook"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="Visit our Facebook profile"
               title="Facebook">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M13.5 21v-8.2h2.76l.41-3.2H13.5V7.56c0-.93.26-1.56 1.6-1.56h1.7V3.14c-.29-.04-1.3-.14-2.48-.14-2.46 0-4.14 1.5-4.14 4.26V9.6H7.4v3.2h2.78V21h3.32Z"/>
                </svg>

            </a>



            <a href="<?php echo htmlspecialchars($socialLinks['instagram']); ?>"
               class="social-link instagram"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="Visit our Instagram profile"
               title="Instagram">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm5.25-3.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z"/>
                </svg>

            </a>



            <a href="<?php echo htmlspecialchars($socialLinks['tiktok']); ?>"
               class="social-link tiktok"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="Visit our TikTok profile"
               title="TikTok">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.35V2h-3.6v13.67a2.9 2.9 0 0 1-2.9 2.75 2.9 2.9 0 0 1-2.9-2.9 2.9 2.9 0 0 1 2.9-2.9c.3 0 .6.05.87.14V9.1a6.5 6.5 0 0 0-.87-.06A6.5 6.5 0 1 0 15.8 15.5V8.55a8.4 8.4 0 0 0 4.9 1.57V6.53a4.9 4.9 0 0 1-1.11.16Z"/>
                </svg>

            </a>



            <a href="<?php echo htmlspecialchars($socialLinks['x']); ?>"
               class="social-link x-social"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="Visit our X profile"
               title="X">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18.9 2H22l-6.78 7.75L23.2 22h-6.25l-4.9-7.56L5.43 22H2.3l7.25-8.29L1.8 2h6.4l4.43 6.96L18.9 2Zm-1.1 18h1.73L7.27 3.9H5.4L17.8 20Z"/>
                </svg>

            </a>



            <a href="<?php echo htmlspecialchars($socialLinks['youtube']); ?>"
               class="social-link youtube"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="Visit our YouTube channel"
               title="YouTube">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M23.5 6.2a3 3 0 0 0-2.12-2.13C19.51 3.55 12 3.55 12 3.55s-7.51 0-9.38.52A3 3 0 0 0 .5 6.2C0 8.08 0 12 0 12s0 3.92.5 5.8a3 3 0 0 0 2.12 2.13c1.87.52 9.38.52 9.38.52s7.51 0 9.38-.52a3 3 0 0 0 2.12-2.13C24 15.92 24 12 24 12s0-3.92-.5-5.8ZM9.55 15.55v-7.1L15.82 12l-6.27 3.55Z"/>
                </svg>

            </a>

        </div>

    </div>


    <div class="copyright">

        &copy; <?php echo date('Y'); ?>
        Campus Hostel Booking System.
        All Rights Reserved.

        <br><br>

        Stay Smart. Live Better.

    </div>

</footer>



<a href="<?php echo htmlspecialchars($whatsappLink); ?>"
   class="whatsapp-float"
   target="_blank"
   rel="noopener noreferrer"
   aria-label="Chat with us on WhatsApp">

    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M20.52 3.48A11.8 11.8 0 0 0 12.1 0C5.55 0 .22 5.33.22 11.88c0 2.09.55 4.13 1.59 5.93L.12 24l6.35-1.66a11.86 11.86 0 0 0 5.63 1.43h.01c6.55 0 11.88-5.33 11.88-11.88 0-3.17-1.23-6.15-3.47-8.41ZM12.1 21.75a9.85 9.85 0 0 1-5.02-1.38l-.36-.21-3.77.99 1.01-3.67-.24-.38a9.82 9.82 0 0 1-1.51-5.22c0-5.45 4.44-9.89 9.9-9.89 2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.89-9.89 9.89Zm5.43-7.4c-.3-.15-1.78-.88-2.06-.98-.28-.1-.48-.15-.68.15-.2.3-.78.98-.96 1.18-.18.2-.35.22-.65.07-.3-.15-1.26-.47-2.4-1.48-.89-.8-1.49-1.78-1.67-2.08-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.68-1.64-.93-2.24-.24-.58-.49-.5-.68-.51l-.58-.01c-.2 0-.52.07-.8.37-.27.3-1.05 1.03-1.05 2.52s1.08 2.92 1.23 3.12c.15.2 2.12 3.24 5.13 4.54.72.31 1.28.5 1.72.64.72.23 1.37.2 1.89.12.58-.09 1.78-.73 2.03-1.44.25-.7.25-1.31.17-1.44-.08-.12-.28-.2-.58-.35Z"/>
    </svg>

    <span class="whatsapp-tooltip">Chat with us on WhatsApp</span>

</a>



<button class="chat-button"
        id="chatButton"
        aria-label="Open Campus Hostel Assistant">
    ☎
</button>

<div class="chat-window" id="chatWindow">

    <div class="chat-header">

        <strong>Campus Hostel Assistant</strong>

        <button id="closeChat"
                aria-label="Close chatbot">×</button>

    </div>

    <div class="chat-messages" id="chatMessages">

        <div class="bot-message">

            Hello! Welcome to Campus Hostel Booking System.
            How can I help you today?

            <br><br>

            You can ask about applications, rooms, transfers,
            vacation requests or registration.

        </div>

    </div>

    <form class="chat-form" id="chatForm">

        <input
            type="text"
            id="chatInput"
            placeholder="Type your question..."
            autocomplete="off"
            required
        >

        <button type="submit">Send</button>

    </form>

</div>


<script>


    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');

    menuToggle.addEventListener('click', function () {
        navLinks.classList.toggle('active');
    });

    document.querySelectorAll('.nav-links a').forEach(function (link) {

        link.addEventListener('click', function () {
            navLinks.classList.remove('active');
        });

    });



    const chatButton = document.getElementById('chatButton');
    const chatWindow = document.getElementById('chatWindow');
    const closeChat = document.getElementById('closeChat');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatMessages = document.getElementById('chatMessages');


    chatButton.addEventListener('click', function () {

        chatWindow.style.display =
            chatWindow.style.display === 'flex' ? 'none' : 'flex';

    });


    closeChat.addEventListener('click', function () {

        chatWindow.style.display = 'none';

    });


    function addMessage(message, sender) {

        const messageDiv = document.createElement('div');

        messageDiv.className =
            sender === 'user' ? 'user-message' : 'bot-message';

        messageDiv.textContent = message;

        chatMessages.appendChild(messageDiv);

        chatMessages.scrollTop = chatMessages.scrollHeight;

    }


    function getBotResponse(question) {

        const text = question.toLowerCase();


        if (text.includes('hello') || text.includes('hi')) {

            return 'Hello! Welcome to Campus Hostel. How can I assist you?';

        }


        if (text.includes('register') || text.includes('account')) {

            return 'To create an account, select Register on the homepage and complete the registration form.';

        }


        if (text.includes('login') || text.includes('sign in')) {

            return 'Select Login and enter your registered email address and password.';

        }


        if (text.includes('apply') || text.includes('application')) {

            return 'First register or log in as a student. Open your dashboard and select Apply for Hostel.';

        }


        if (text.includes('room') || text.includes('allocation')) {

            return 'Room allocations are processed by the accommodation officer. Students can view their allocation from the student dashboard.';

        }


        if (text.includes('transfer')) {

            return 'Log in to your student account, open Transfer Request and submit your preferred hall and reason.';

        }


        if (text.includes('vacation')) {

            return 'Log in to your student dashboard and submit a vacation request with your departure and return dates.';

        }


        if (text.includes('caretaker') || text.includes('maintenance')) {

            return 'The caretaker manages room inspections and records room damage or maintenance issues.';

        }


        if (text.includes('officer')) {

            return 'The accommodation officer manages applications, room allocation, students and accommodation requests.';

        }


        if (text.includes('help') || text.includes('support')) {

            return 'You can log in to your student account and visit the Help section for more information.';

        }


        if (text.includes('thank')) {

            return 'You are welcome! Enjoy using Campus Hostel Booking System.';

        }


        return 'I can help with registration, login, hostel applications, room allocation, transfers, vacation requests and support. Please ask about one of these services.';

    }


    chatForm.addEventListener('submit', function (event) {

        event.preventDefault();

        const question = chatInput.value.trim();

        if (question === '') {
            return;
        }

        addMessage(question, 'user');

        chatInput.value = '';

        setTimeout(function () {

            addMessage(getBotResponse(question), 'bot');

        }, 400);

    });

</script>

</body>
</html>
