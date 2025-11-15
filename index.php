<?php
// index.php - Landing Page with Video + Login/Register + Bottom-right Mute/Unmute Toggle (Option C)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpiceCeylon - Welcome</title>

    <!-- Link to external landing CSS -->
    <link rel="stylesheet" href="assets/css/landing.css">
</head>

<body>

    <!-- Background Video -->
    <video id="bgVideo" class="video-bg" autoplay muted loop playsinline>
        <source src="assets/videos/landing-video.mp4" type="video/mp4">
    </video>

    <!-- Center Content -->
    <div class="content">
        <h1>Welcome to SpiceCeylon</h1>
        <p>Fresh Sri Lankan Spices — From Farmers to Your Kitchen</p>

        <a href="auth/login.php" class="btn">Login</a>
        <a href="auth/register.php" class="btn">Register</a>
    </div>

    <!-- Bottom-right Mute/Unmute Toggle -->
    <div id="muteToggle" onclick="toggleMute()">🔇 Unmute</div>

    <script>
        const video = document.getElementById("bgVideo");
        const muteToggle = document.getElementById("muteToggle");

        // Bottom-right toggle
        function toggleMute() {
            video.muted = !video.muted;
            muteToggle.innerHTML = video.muted ? "🔇 Unmute" : "🔊 Mute";
        }
    </script>

</body>
</html>
