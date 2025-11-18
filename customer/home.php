<?php
session_start();
include "header.php";

// Ensure user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}
?>

<link rel="stylesheet" href="../assets/css/home.css">
<script src="../assets/js/home.js" defer></script>

<div class="home-container">

    <!-- Video Banner -->
    <div class="video-banner">
        <video autoplay muted loop class="banner-video">
            <source src="../assets/videos/landing-video.mp4" type="video/mp4">
            Your browser does not support HTML5 video.
        </video>
        <div class="video-overlay">
            <h1>Welcome to SpiceCeylon</h1>
            <p>Discover the finest Sri Lankan spices at your fingertips.</p>
        </div>
    </div>

    <!-- SPICE VIEW PAGE MAPPING -->
    <?php

    // MAP EACH SPICE NAME TO ITS VIEW PAGE
    $viewPages = [
        "Cinnamon" => "view_cinnamon.php",
        "Cardamom" => "view_cardamom.php",
        "Cloves" => "view_cloves.php",
        "Nutmeg & Mace" => "view_nutmeg_mace.php",
        "Black Pepper" => "view_black_pepper.php",
        "Cumin Seeds" => "view_cumin_seeds.php",
        "Coriander Seeds" => "view_coriander_seeds.php",
        "Fennel Seeds" => "view_fennel_seeds.php",
        "Fenugreek Seeds" => "view_fenugreek_seeds.php",
        "Mustard Seeds" => "view_mustard_seeds.php",
        "Turmeric" => "view_turmeric.php",

        "Pandan Leaves" => "view_pandan_leaves.php",
        "Curry Leaves" => "view_curry_leaves.php",
        "Lemongrass" => "view_lemongrass.php",
        "Sri Lankan Ginger" => "view_sri_lankan_ginger.php",
        "Garlic" => "view_garlic.php",
        "Ceylon Citron / Lemon" => "view_ceylon_citron.php",

        "Ceylon Chili / Bird's Eye Chili" => "view_bird_eye_chili.php",
        "Chili Powder" => "view_chili_powder.php",
        "Black Mustard Seeds" => "view_black_mustard_seeds.php",

        "Goraka" => "view_goraka.php",
        "Tamarind" => "view_tamarind.php",
        "Screw Pine / Kewra" => "view_kewra.php",
        "Licorice Powder" => "view_licorice_powder.php",
        "Annatto Seeds" => "view_annatto.php",
        "Ajwain (Carom Seeds)" => "view_ajwain.php",
        "Dill" => "view_dill.php",
        "Sweet Flag" => "view_sweet_flag.php",

        "Roasted Curry Powder" => "view_roasted_curry_powder.php",
        "Unroasted Curry Powder" => "view_unroasted_curry_powder.php",
        "Chili Paste" => "view_chili_paste.php",
    ];

    // YOUR CATEGORIES
    $categories = [
        "Core Sri Lankan Spices" => [
            ["name"=>"Cinnamon","sinhala"=>"Kurundu - කුරුඳු","desc"=>"True Ceylon Cinnamon is a national treasure."],
            ["name"=>"Cardamom","sinhala"=>"Enasal - එනසාල්","desc"=>"Green cardamom gives an intense aroma."],
            ["name"=>"Cloves","sinhala"=>"Karabu Neti - කරාබු නැටි","desc"=>"Used in rice and meat dishes."],
            ["name"=>"Nutmeg & Mace","sinhala"=>"Sadikka / Wasawasee - සාදික්කා / වසාවාසි","desc"=>"Warm, sweet flavor for desserts/curries."],
            ["name"=>"Black Pepper","sinhala"=>"Gammiris - ගම්මිරිස්","desc"=>"Sri Lanka’s famous pepper."],
            ["name"=>"Cumin Seeds","sinhala"=>"Suduru - සුදුරු","desc"=>"Earthy spice used in curry powders."],
            ["name"=>"Coriander Seeds","sinhala"=>"Kottamalli - කොත්තමල්ලි","desc"=>"Base for Sri Lankan curry powders."],
            ["name"=>"Fennel Seeds","sinhala"=>"Maduru - මාදුරු","desc"=>"Sweet, aromatic spice."],
            ["name"=>"Fenugreek Seeds","sinhala"=>"Uluhal - උලුහාල්","desc"=>"Used sparingly for bitter flavor."],
            ["name"=>"Mustard Seeds","sinhala"=>"Aba - අබ","desc"=>"Used in tempering."],
            ["name"=>"Turmeric","sinhala"=>"Kaha - කහ","desc"=>"Colorful earthy spice."],
        ],

        "Fresh Herbs & Aromatics" => [
            ["name"=>"Pandan Leaves","sinhala"=>"Rampe - රම්පේ","desc"=>"Adds aroma to rice & curries."],
            ["name"=>"Curry Leaves","sinhala"=>"Karapincha - කරපිංචා","desc"=>"Used in almost every curry."],
            ["name"=>"Lemongrass","sinhala"=>"Sera - සේර","desc"=>"Strong citrus scent."],
            ["name"=>"Sri Lankan Ginger","sinhala"=>"Inguru - ඉඟුරු","desc"=>"Key curry base ingredient."],
            ["name"=>"Garlic","sinhala"=>"Sudulunu - සුදුලුනු","desc"=>"Used in tempering and pastes."],
            ["name"=>"Ceylon Citron / Lemon","sinhala"=>"Dehi - දෙහි / පැංගිරි","desc"=>"Strong citrus rind and juice."],
        ],

        "Chilies & Heat Elements" => [
            ["name"=>"Ceylon Chili / Bird's Eye Chili","sinhala"=>"Kochchi - කොච්චි","desc"=>"Very spicy small chili."],
            ["name"=>"Chili Powder","sinhala"=>"Kochchi Thool - මිරිස් ‌කුඩු","desc"=>"Ground dried chilies."],
            ["name"=>"Black Mustard Seeds","sinhala"=>"Aba Kalu - අබ කලු","desc"=>"Adds heat in paste form."],
        ],

        "Specialty & Regional Spices" => [
            ["name"=>"Goraka","sinhala"=>"Goraka - ගොරකා","desc"=>"Used for sourness in fish curries."],
            ["name"=>"Tamarind","sinhala"=>"Siyambala - සියඹලා","desc"=>"Sharp fruity sourness."],
            ["name"=>"Screw Pine / Kewra","sinhala"=>"Wathakesi - වතකෙසි","desc"=>"Used in sweets."],
            ["name"=>"Licorice Powder","sinhala"=>"Wadahal - වදහල්","desc"=>"Used in Ayurveda."],
            ["name"=>"Annatto Seeds","sinhala"=>"Kurkuman - කුර්කුමන්","desc"=>"Natural coloring spice."],
            ["name"=>"Ajwain (Carom Seeds)","sinhala"=>"Asamodagam - අසමෝදගම්","desc"=>"Strong herbal flavor."],
            ["name"=>"Dill","sinhala"=>"Endaru - එන්‌ඩරු","desc"=>"Used in sambols and salads."],
            ["name"=>"Sweet Flag","sinhala"=>"Wadakaha - වදකහ","desc"=>"Medicinal herb."],
        ],

        "Spice Blends" => [
            ["name"=>"Roasted Curry Powder","sinhala"=>"Badum Thel Kudu - බැදුම් තෙල් කුඩු","desc"=>"Dark, fragrant curry powder."],
            ["name"=>"Unroasted Curry Powder","sinhala"=>"Amu Thel Kudu - අමු තෙල් කුඩු","desc"=>"Lighter curry blend."],
            ["name"=>"Chili Paste","sinhala"=>"Kochchi Miris Hodi - කොච්චි මිරිස් හොදි","desc"=>"Base paste for curries."],
        ],
    ];

    // DISPLAY CARDS
    foreach ($categories as $category => $items) {
        echo "<h2 class='section-title'>$category</h2>";
        echo "<div class='spices-grid'>";

        foreach ($items as $spice) {

            $imageName = strtolower(str_replace(' ', '-', $spice['name']));
            $imagePath = "../assets/images/".$imageName.".jpg";

            // GET THE CORRECT VIEW PAGE
            $viewFile = $viewPages[$spice['name']] ?? null;

            echo '<div class="spice-card">';
            echo '<img src="'.$imagePath.'" alt="'.$spice['name'].'">';
            echo '<h3>'.$spice['name'].'</h3>';
            echo '<p><strong>'.$spice['sinhala'].'</strong></p>';
            echo '<p>'.$spice['desc'].'</p>';

            echo '<div class="card-controls">';
            if ($viewFile) {
                echo '<a href="products/'.$viewFile.'" class="btn-view">View</a>';
            } else {
                echo '<span class="btn-disabled">No Page</span>';
            }
            echo '</div>';

            echo '</div>';
        }

        echo "</div>";
    }
    ?>
</div>

<?php include "footer.php"; ?>
