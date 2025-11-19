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
            ["name"=>"Cinnamon","sinhala"=>"Kurundu - කුරුඳු","desc"=>"True Ceylon Cinnamon is a national treasure.", "price"=>4500],
            ["name"=>"Cardamom","sinhala"=>"Enasal - එනසාල්","desc"=>"Green cardamom gives an intense aroma.","price"=>7800],
            ["name"=>"Cloves","sinhala"=>"Karabu Neti - කරාබු නැටි","desc"=>"Used in rice and meat dishes.","price"=>7800],
            ["name"=>"Nutmeg & Mace","sinhala"=>"Sadikka / Wasawasee - සාදික්කා / වසාවාසි","desc"=>"Warm, sweet flavor for desserts/curries.","price"=>7800],
            ["name"=>"Black Pepper","sinhala"=>"Gammiris - ගම්මිරිස්","desc"=>"Sri Lanka’s famous pepper.","price"=>7800],
            ["name"=>"Cumin Seeds","sinhala"=>"Suduru - සුදුරු","desc"=>"Earthy spice used in curry powders.","price"=>7800],
            ["name"=>"Coriander Seeds","sinhala"=>"Kottamalli - කොත්තමල්ලි","desc"=>"Base for Sri Lankan curry powders.","price"=>7800],
            ["name"=>"Fennel Seeds","sinhala"=>"Maduru - මාදුරු","desc"=>"Sweet, aromatic spice.","price"=>7800],
            ["name"=>"Fenugreek Seeds","sinhala"=>"Uluhal - උලුහාල්","desc"=>"Used sparingly for bitter flavor.","price"=>7800],
            ["name"=>"Mustard Seeds","sinhala"=>"Aba - අබ","desc"=>"Used in tempering.","price"=>7800],
            ["name"=>"Turmeric","sinhala"=>"Kaha - කහ","desc"=>"Colorful earthy spice.","price"=>7800],
        ],

        "Fresh Herbs & Aromatics" => [
            ["name"=>"Pandan Leaves","sinhala"=>"Rampe - රම්පේ","desc"=>"Adds aroma to rice & curries.","price"=>7800],
            ["name"=>"Curry Leaves","sinhala"=>"Karapincha - කරපිංචා","desc"=>"Used in almost every curry.","price"=>7800],
            ["name"=>"Lemongrass","sinhala"=>"Sera - සේර","desc"=>"Strong citrus scent.","price"=>7800],
            ["name"=>"Sri Lankan Ginger","sinhala"=>"Inguru - ඉඟුරු","desc"=>"Key curry base ingredient.","price"=>7800],
            ["name"=>"Garlic","sinhala"=>"Sudulunu - සුදුලුනු","desc"=>"Used in tempering and pastes.","price"=>7800],
            ["name"=>"Ceylon Citron / Lemon","sinhala"=>"Dehi - දෙහි / පැංගිරි","desc"=>"Strong citrus rind and juice.","price"=>7800],
        ],

        "Chilies & Heat Elements" => [
            ["name"=>"Ceylon Chili / Bird's Eye Chili","sinhala"=>"Kochchi - කොච්චි","desc"=>"Very spicy small chili.","price"=>7800],
            ["name"=>"Chili Powder","sinhala"=>"Kochchi Thool - මිරිස් ‌කුඩු","desc"=>"Ground dried chilies.","price"=>7800],
            ["name"=>"Black Mustard Seeds","sinhala"=>"Aba Kalu - අබ ‌කුඩු","desc"=>"Adds heat in paste form.","price"=>7800],
        ],

        "Specialty & Regional Spices" => [
            ["name"=>"Goraka","sinhala"=>"Goraka - ගොරකා","desc"=>"Used for sourness in fish curries.","price"=>7800],
            ["name"=>"Tamarind","sinhala"=>"Siyambala - සියඹලා","desc"=>"Sharp fruity sourness.","price"=>7800],
            ["name"=>"Screw Pine / Kewra","sinhala"=>"Wathakesi - වතකෙසි","desc"=>"Used in sweets.","price"=>7800],
            ["name"=>"Licorice Powder","sinhala"=>"Walmee Kudu - වැල්මී කුඩු","desc"=>"Used in Ayurveda.","price"=>7800],
            ["name"=>"Annatto Seeds","sinhala"=>"Kurkuman - කුර්කුමන්","desc"=>"Natural coloring spice.","price"=>7800],
            ["name"=>"Ajwain (Carom Seeds)","sinhala"=>"Asamodagam - අසමෝදගම්","desc"=>"Strong herbal flavor.","price"=>7800],
            ["name"=>"Dill","sinhala"=>"Endaru - එන්‌ඩරු","desc"=>"Used in sambols and salads.","price"=>7800],
            ["name"=>"Sweet Flag","sinhala"=>"Wadakaha - වදකහ","desc"=>"Medicinal herb.","price"=>7800],
        ],

        "Spice Blends" => [
            ["name"=>"Roasted Curry Powder","sinhala"=>"Badapu Thunapaha Kudu - බැදපු ‌තුනපහ කුඩු","desc"=>"Dark, fragrant curry powder.","price"=>7800],
            ["name"=>"Unroasted Curry Powder","sinhala"=>"Amu Thuna Paha Kudu - අමු ‌තුනපහ කුඩු","desc"=>"Lighter curry blend.","price"=>7800],
            ["name"=>"Chili Paste","sinhala"=>"Kochchi Miris Hodi - කොච්චි මිරිස් හොදි","desc"=>"Base paste for curries.","price"=>7800],
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
            echo '<p class="price"><strong>1kg Price:</strong> Rs. '.$spice['price'].'.00</p>';


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
