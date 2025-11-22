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
        "Annatto Seeds" => "view_annatto_seeds.php",
        "Ajwain (Carom Seeds)" => "view_ajwain.php",
        "Dill" => "view_dill.php",
        "Sweet Flag" => "view_sweet_flag.php",

        "Roasted Curry Powder" => "view_roasted_curry_powder.php",
        "Unroasted Curry Powder" => "view_unroasted_curry_powder.php",
        "Chili Paste" => "view_chili_paste.php",

        // NEW SPICES
        "Vanilla" => "view_vanilla.php",
        "Sesame Seeds" => "view_sesame_seeds.php",
        "Bay Leaves" => "view_bay_leaves.php",
        "Star Anise" => "view_star_anise.php",
        "Asafoetida" => "view_asafoetida.php",
        "Celery Seeds" => "view_celery_seeds.php",
        "Saffron" => "view_saffron.php",
        "Poppy Seeds" => "view_poppy_seeds.php",
        "Caraway Seeds" => "view_caraway_seeds.php",
        "Juniper Berries" => "view_juniper_berries.php",
        "Sumac" => "view_sumac.php"
    ];

    // CATEGORIES
    $categories = [
        "Core Sri Lankan Spices" => [
            ["name"=>"Cinnamon","sinhala"=>"Kurundu - කුරුඳු","desc"=>"True Ceylon Cinnamon is a national treasure.", "price"=> 3200],
            ["name"=>"Cardamom","sinhala"=>"Enasal - එනසාල්","desc"=>"Green cardamom gives an intense aroma.(pods - very premium)","price"=>15000],
            ["name"=>"Cloves","sinhala"=>"Karabu Neti - කරාබු නැටි","desc"=>"Used in rice and meat dishes.","price"=>6000],
            ["name"=>"Nutmeg & Mace","sinhala"=>"Sadikka / Wasawasee - සාදික්කා / වසාවාසි","desc"=>"Warm, sweet flavor for desserts/curries.(nutmeg powder)","price"=> 8000],
            ["name"=>"Black Pepper","sinhala"=>"Gammiris - ගම්මිරිස්","desc"=>"Sri Lanka's famous pepper.","price"=>3800],
            ["name"=>"Cumin Seeds","sinhala"=>"Suduru - සුදුරු","desc"=>"Earthy spice used in curry powders.","price"=>1800],
            ["name"=>"Coriander Seeds","sinhala"=>"Kottamalli - කොත්තමල්ලි","desc"=>"Base for Sri Lankan curry powders.","price"=>1600],
            ["name"=>"Fennel Seeds","sinhala"=>"Maduru - මාදුරු","desc"=>"Sweet, aromatic spice.","price"=>2000],
            ["name"=>"Fenugreek Seeds","sinhala"=>"Uluhal - උලුහාල්","desc"=>"Used sparingly for bitter flavor.","price"=>2200],
            ["name"=>"Mustard Seeds","sinhala"=>"Aba - අබ","desc"=>"Used in tempering.","price"=>1400],
            ["name"=>"Turmeric","sinhala"=>"Kaha - කහ","desc"=>"Colorful earthy spice.","price"=>3000],
            // NEW ADDITIONS
            ["name"=>"Vanilla","sinhala"=>"Vanila - වැනිලා","desc"=>"Premium vanilla beans with rich aroma and flavor.","price"=>25000],
            ["name"=>"Sesame Seeds","sinhala"=>"Tala - තල","desc"=>"Nutty seeds used in sweets and savory dishes.","price"=>1800],
        ],

        "Fresh Herbs & Aromatics" => [
            ["name"=>"Pandan Leaves","sinhala"=>"Rampe - රම්පේ","desc"=>"Adds aroma to rice & curries.","price"=>800],
            ["name"=>"Curry Leaves","sinhala"=>"Karapincha - කරපිංචා","desc"=>"Used in almost every curry.","price"=> 600],
            ["name"=>"Lemongrass","sinhala"=>"Sera - සේර","desc"=>"Strong citrus scent.","price"=>1500],
            ["name"=>"Sri Lankan Ginger","sinhala"=>"Inguru - ඉඟුරු","desc"=>"Key curry base ingredient.(fresh)","price"=>1000],
            ["name"=>"Garlic","sinhala"=>"Sudulunu - සුදුලුනු","desc"=>"Used in tempering and pastes.(fresh)","price"=>650],
            ["name"=>"Ceylon Citron / Lemon","sinhala"=>"Dehi - දෙහි / පැංගිරි","desc"=>"Strong citrus rind and juice.(fresh, seasonal price)","price"=> 320],
            // NEW ADDITIONS
            ["name"=>"Bay Leaves","sinhala"=>"Bae Kolaya - බේ කොළ","desc"=>"Aromatic leaves for soups and rice dishes.","price"=>900],
            ["name"=>"Star Anise","sinhala"=>"Kaha Kudu - කහ කුඩු","desc"=>"Star-shaped spice with licorice flavor.","price"=>2200],
            ["name"=>"Asafoetida","sinhala"=>"Perungayam - පෙරුන්ගායම්","desc"=>"Strong aromatic resin for digestive dishes.","price"=>3500],
            ["name"=>"Celery Seeds","sinhala"=>"Asamodagam - අසමෝදගම්","desc"=>"Tiny seeds with intense celery flavor.","price"=>1900],
        ],

        "Chilies & Heat Elements" => [
            ["name"=>"Ceylon Chili / Bird's Eye Chili","sinhala"=>"Kochchi - කොච්චි","desc"=>"Very spicy small chili.(dried)","price"=> 1400],
            ["name"=>"Chili Powder","sinhala"=>"Kochchi Thool - මිරිස් ‌කුඩු","desc"=>"Ground dried chilies.","price"=> 1600],
            ["name"=>"Black Mustard Seeds","sinhala"=>"Aba Kalu - අබ ‌කුඩු","desc"=>"Adds heat in paste form.","price"=> 1400],
        ],

        "Specialty & Regional Spices" => [
            ["name"=>"Goraka","sinhala"=>"Goraka - ගොරකා","desc"=>"Used for sourness in fish curries.(dried)","price"=>1700],
            ["name"=>"Tamarind","sinhala"=>"Siyambala - සියඹලා","desc"=>"Sharp fruity sourness.(pulp)","price"=>1200],
            ["name"=>"Screw Pine / Kewra","sinhala"=>"Wathakesi - වතකෙසි","desc"=>"Used in sweets.(essence equivalent)","price"=>4000],
            ["name"=>"Licorice Powder","sinhala"=>"Walmee Kudu - වැල්මී කුඩු","desc"=>"Used in Ayurveda.(premium medicinal)","price"=> 5500],
            ["name"=>"Annatto Seeds","sinhala"=>"Kurkuman - කුර්කුමන්","desc"=>"Natural coloring spice.","price"=> 3000],
            ["name"=>"Ajwain (Carom Seeds)","sinhala"=>"Asamodagam - අසමෝදගම්","desc"=>"Strong herbal flavor.","price"=>2000],
            ["name"=>"Dill","sinhala"=>"Endaru - එන්‌ඩරු","desc"=>"Used in sambols and salads.","price"=>1800],
            ["name"=>"Sweet Flag","sinhala"=>"Wadakaha - වදකහ","desc"=>"Medicinal herb.(medicinal rarity)","price"=>4000],
            // NEW ADDITIONS
            ["name"=>"Saffron","sinhala"=>"Kunkuma - කුංකුම","desc"=>"The world's most precious spice for color and flavor.","price"=>85000],
            ["name"=>"Poppy Seeds","sinhala"=>"Gas Kasa - ගස් කාසා","desc"=>"Tiny blue seeds with nutty flavor for baking.","price"=>2800],
            ["name"=>"Caraway Seeds","sinhala"=>"Kala Jeera - කලා ජීර","desc"=>"Aromatic seeds with anise-like flavor for breads.","price"=>2400],
            ["name"=>"Juniper Berries","sinhala"=>"Juniper - ජුනිපර්","desc"=>"Aromatic berries with pine flavor for meats.","price"=>3200],
            ["name"=>"Sumac","sinhala"=>"Sumac - සුමැක්","desc"=>"Tangy crimson spice with lemony flavor.","price"=>2900],
        ],

        "Spice Blends" => [
            ["name"=>"Roasted Curry Powder","sinhala"=>"Badapu Thunapaha Kudu - බැදපු ‌තුනපහ කුඩු","desc"=>"Dark, fragrant curry powder.","price"=> 1400],
            ["name"=>"Unroasted Curry Powder","sinhala"=>"Amu Thuna Paha Kudu - අමු ‌තුනපහ කුඩු","desc"=>"Lighter curry blend.","price"=>1200],
            ["name"=>"Chili Paste","sinhala"=>"Kochchi Miris Hodi - කොච්චි මිරිස් හොදි","desc"=>"Base paste for curries.","price"=>1700],
        ],
    ];

    // DISPLAY CARDS
    foreach ($categories as $category => $items) {
        echo "<h2 class='section-title'>$category</h2>";
        echo "<div class='spices-grid'>";

        foreach ($items as $spice) {

            $imageMappings = [
                'Ajwain (Carom Seeds)' => 'Ajwain-(Carom Seeds)',
                'Ceylon Citron / Lemon' => 'Ceylon -Citron-&-Lemon', 
                'Ceylon Chili / Bird\'s Eye Chili' => 'Ceylon-Chili',
                'Screw Pine / Kewra' => 'Screw-Pine-Kewra'
            ];

            if (isset($imageMappings[$spice['name']])) {
                $imagePath = "../assets/images/".$imageMappings[$spice['name']].".jpg";
            } else {
                $imageName = strtolower(str_replace(' ', '-', $spice['name']));
                $imagePath = "../assets/images/".$imageName.".jpg";
            }

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