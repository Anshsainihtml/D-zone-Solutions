<?php
require_once __DIR__ . '/includes/auth_helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>D-zone solution</title>
    <link rel="stylesheet" href="css/style.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .main nav img{
         position: relative;
        top: 10px;
        }
    </style>
</head>
<body>
    <header class="main">
        <nav>
            <a href="index.php" class="logo">
                <img src="./images/logo2.png" alt="">
            </a>

            <ul class="menu">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="./dashboard/user/courses.php">Courses</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="dashboard/user/">Dashboard</a></li>
                    <?php if (isAdmin()): ?><li><a href="dashboard/admin/">Admin</a></li><?php endif; ?>
                    <li><a href="auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="auth/login.php">Login</a></li>
                    <li><a href="auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="main-heading">
            <h1>Bharat’s <span>Trusted & Affordable</span> <br>Educational Platform</h1>
            <p>Unlock your potential by signing up with Physics Wallah-The most affordable learning solution</p>
            <a class="main-btn" href="#">Contact</a>
        </div>
    </header>
    
  <section class="stats-section">
        <div class="stats-header">
            <h2>INDIA'S MOST LOVED CODING COMMUNITY 💖</h2>
        </div>
        <div class="stats-container">
           <div class="stat-box">
                <div class=stat-box-in>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>7,000,000+</h3>
                    </div>
                </div>
                <p>HAPPY LEARNERS</p>
            </div>

           <div class="stat-box">
                <div class=stat-box-in>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-content">
                        <h3>2 CRORE+</h3>
                    </div>
                </div>
                <p>MONTHLY VIEWS</p>
            </div>

            <div class="stat-box">
                <div class=stat-box-in>
                    <div class="stat-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-content">
                        <h3>100,000+</h3>
                    </div>
                </div>
                <p>NEW MONTHLY SUBSCRIBERS</p>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="feature-container">

            <div class="feature-box">
                <div class="f-img">
                    <img src="images/info-icon1.png"/>
                </div>
                <div class="f-text">
                    <h4>Web Development</h4>
                    <p>Lorem ipsum dolor sit amet.</p>
                    <a href="#" class="main-btn">Check</a>
                </div>
            </div>

            <div class="feature-box">
                <div class="f-img">
                    <img src="images/info-icon2.png"/>
                </div>
                <div class="f-text">
                    <h4>Software Development</h4>
                    <p>Lorem ipsum dolor sit amet.</p>
                    <a href="#" class="main-btn">Check</a>
                </div>
            </div>

            <div class="feature-box">
                <div class="f-img">
                    <img src="images/info-icon3.png"/>
                </div>
                <div class="f-text">
                    <h4>App Development</h4>
                    <p>Lorem ipsum dolor sit amet.</p>
                    <a href="#" class="main-btn">Check</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Course Preview Card -->
   

    <section class="about" id="about">
        <div class="about-img">
            <img src="images/about.png">
        </div>
        <div class="about-text">
                <h2>Start Tracking Your Course Statistics</h2>
            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Ad cumque eum quia magni, voluptatibus nesciunt vero. Reprehenderit fugiat soluta ullam praesentium, omnis autem voluptatibus quae.</p>
            <button class="main-btn">Read More</button>
        </div>
    </section>

    <footer class="contact" id="contact">
        <div class="contact-heading">
            <h1>Contact Us</h1>
            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>
        </div>
        <form action="userinformation.php" method="post">
            <input type="text" name="user" placeholder="Your Full Name"/>
            <input type="email" name="email" placeholder="Your E-Mail"/>
            <textarea name="message" placeholder="Type Your Message Here.........."></textarea>
            <button class="main-btn contact-btn" type="submit">Continue</button>
        </form>
    </footer>
</body>
</html>